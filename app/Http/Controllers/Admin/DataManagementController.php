<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WebsiteExportService;
use App\Services\WebsiteImportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

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
    public function index()
    {
        return Inertia::render('dashboard/DataManagement', [
            'exportTables' => $this->exportService->getExportTables(),
            'importTables' => $this->importService->getImportTables(),
        ]);
    }

    /**
     * Export website data to a ZIP file.
     */
    public function export()
    {
        try {
            $zipPath = $this->exportService->exportWebsite();

            // Clean up old exports
            $this->exportService->cleanupOldExports();

            return response()->download($zipPath, basename($zipPath), [
                'Content-Type' => 'application/zip',
            ])->deleteFileAfterSend(true);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Export failed: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upload and validate an import file.
     */
    public function uploadImportFile(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:zip|max:102400', // 100MB max
        ]);

        $file = $request->file('import_file');
        $fileName = 'import-'.now()->format('Y-m-d_H-i-s').'.zip';
        $path = $file->storeAs('temp', $fileName);
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
    public function import(Request $request)
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
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $stats = $this->importService->importWebsite($fullPath);

            // Clean up the imported file
            Storage::delete($filePath);

            return response()->json([
                'message' => 'Import completed successfully',
                'stats' => $stats,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Import failed: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get import file metadata.
     */
    public function getImportMetadata(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string',
        ]);

        $filePath = $request->input('file_path');
        $fullPath = Storage::path($filePath);

        if (! Storage::exists($filePath)) {
            return response()->json([
                'message' => 'File not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $metadata = $this->importService->getImportMetadata($fullPath);

        if ($metadata === null) {
            return response()->json([
                'message' => 'Cannot read metadata from file',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($metadata);
    }

    /**
     * Cancel an import by deleting the uploaded file.
     */
    public function cancelImport(Request $request)
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
