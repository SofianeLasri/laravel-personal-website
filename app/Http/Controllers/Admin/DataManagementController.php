<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ExportWebsiteJob;
use App\Services\WebsiteExportService;
use App\Services\WebsiteImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

/**
 * Controller for managing website data export and import functionality.
 */
class DataManagementController extends Controller
{
    public function __construct(
        private readonly WebsiteExportService $exportService,
        private readonly WebsiteImportService $importService
    ) {}

    /**
     * Display the data management page.
     */
    public function index(): InertiaResponse
    {
        return Inertia::render('dashboard/DataManagement', [
            'exportTables' => $this->exportService->getExportTables(),
            'importTables' => $this->importService->getImportTables(),
        ]);
    }

    /**
     * Export website data to a ZIP file using background job.
     */
    public function export(): JsonResponse
    {
        $lockKey = 'data_export_lock';
        $requestId = Str::uuid()->toString();
        $cacheKey = "data_export_status_{$requestId}";

        // Check if an export is already in progress
        if (Cache::has($lockKey)) {
            $existingExport = Cache::get($lockKey);

            return response()->json([
                'status' => 'already_in_progress',
                'message' => 'An export is already in progress. Please wait for it to complete.',
                'request_id' => $existingExport['request_id'],
                'started_at' => $existingExport['started_at'],
            ], ResponseAlias::HTTP_CONFLICT);
        }

        // Set lock to prevent concurrent exports
        Cache::put($lockKey, [
            'request_id' => $requestId,
            'started_at' => now()->toISOString(),
        ], now()->addMinutes(20)); // Lock expires after 20 minutes

        // Initialize export status in cache
        Cache::put($cacheKey, [
            'status' => 'queued',
            'request_id' => $requestId,
            'queued_at' => now()->toISOString(),
        ], now()->addMinutes(20));

        // Dispatch background job
        ExportWebsiteJob::dispatch($cacheKey, $requestId);

        return response()->json([
            'status' => 'accepted',
            'message' => 'Export request accepted. Processing in background.',
            'request_id' => $requestId,
            'status_url' => route('dashboard.data-management.export-status', ['requestId' => $requestId]),
        ], ResponseAlias::HTTP_ACCEPTED);
    }

    /**
     * Get export status for a specific request.
     */
    public function exportStatus(string $requestId): JsonResponse
    {
        $cacheKey = "data_export_status_{$requestId}";
        $status = Cache::get($cacheKey);

        if (! $status) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Export request not found or expired.',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        // Release lock if export is completed or failed
        if (in_array($status['status'], ['completed', 'failed'])) {
            Cache::forget('data_export_lock');
        }

        return response()->json($status);
    }

    /**
     * Download exported file.
     */
    public function downloadExport(string $requestId): BinaryFileResponse|JsonResponse
    {
        $cacheKey = "data_export_status_{$requestId}";
        $status = Cache::get($cacheKey);

        if (! $status || $status['status'] !== 'completed') {
            return response()->json([
                'status' => 'not_ready',
                'message' => 'Export not ready for download.',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        $filePath = $status['file_path'];
        if (! Storage::exists($filePath)) {
            return response()->json([
                'status' => 'file_not_found',
                'message' => 'Export file not found or expired.',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        $fullPath = Storage::path($filePath);
        $fileName = "website-export-{$requestId}-".now()->format('Y-m-d_H-i-s').'.zip';

        return response()->download($fullPath, $fileName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Upload and validate an import file.
     */
    public function uploadImportFile(Request $request): JsonResponse
    {
        $request->validate([
            'import_file' => 'required|file|mimes:zip',
        ]);

        $file = $request->file('import_file');
        if (! $file) {
            throw ValidationException::withMessages([
                'import_file' => ['No file uploaded'],
            ]);
        }

        $fileName = 'import-'.now()->format('Y-m-d_H-i-s').'.zip';
        $path = $file->storeAs('temp', $fileName);
        if ($path === false) {
            throw ValidationException::withMessages([
                'import_file' => ['Failed to store file'],
            ]);
        }

        $fullPath = Storage::path($path);

        // Validate the import file
        $validation = $this->importService->validateImportFile($fullPath);

        if (! $validation['valid']) {
            Storage::delete($path);
            throw ValidationException::withMessages([
                'import_file' => $validation['errors'],
            ]);
        }

        return response()->json([
            'message' => 'File uploaded and validated successfully',
            'file_path' => $path,
            'metadata' => $validation['metadata'],
        ]);
    }

    /**
     * Import website data from uploaded file.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file_path' => 'required|string',
            'confirm_import' => 'required|boolean|accepted',
        ]);

        $filePath = $request->input('file_path');
        $fullPath = Storage::path($filePath);

        if (! Storage::exists($filePath)) {
            return response()->json([
                'message' => 'Import file not found',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        try {
            $stats = $this->importService->importWebsite($fullPath);

            Storage::delete($filePath);

            return response()->json([
                'message' => 'Import completed successfully',
                'stats' => $stats,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Import failed: '.$e->getMessage(),
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get import file metadata.
     */
    public function getImportMetadata(Request $request): JsonResponse
    {
        $request->validate([
            'file_path' => 'required|string',
        ]);

        $filePath = $request->input('file_path');
        $fullPath = Storage::path($filePath);

        if (! Storage::exists($filePath)) {
            return response()->json([
                'message' => 'File not found',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        $metadata = $this->importService->getImportMetadata($fullPath);

        if ($metadata === null) {
            return response()->json([
                'message' => 'Cannot read metadata from file',
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($metadata);
    }

    /**
     * Cancel an import by deleting the uploaded file.
     */
    public function cancelImport(Request $request): JsonResponse
    {
        $request->validate([
            'file_path' => 'required|string',
        ]);

        $filePath = $request->input('file_path');

        if (Storage::exists($filePath)) {
            Storage::delete($filePath);
        }

        return response()->json([
            'message' => 'Import cancelled successfully',
        ]);
    }
}
