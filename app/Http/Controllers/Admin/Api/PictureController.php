<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PictureRequest;
use App\Models\Picture;
use App\Services\UploadedFilesService;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PictureController extends Controller
{
    public function __construct(protected UploadedFilesService $uploadedFilesService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json(Picture::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PictureRequest $request): JsonResponse
    {
        try {
            $picture = $this->uploadedFilesService->storeAndOptimizeUploadedPicture($request->file('picture'));

            return response()->json($picture, Response::HTTP_CREATED);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $pictureId): JsonResponse
    {
        return response()->json(Picture::findOrFail($pictureId));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $pictureId): Response
    {
        Picture::findOrFail($pictureId)->delete();

        return response()->noContent();
    }

    /**
     * Force reoptimization of a picture
     */
    public function reoptimize(int $pictureId): JsonResponse
    {
        try {
            $picture = Picture::findOrFail($pictureId);

            // Check if original file exists
            if (! $picture->hasValidOriginalPath() || ! \Storage::disk('public')->exists($picture->path_original)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier original n\'existe pas',
                ], 422);
            }

            $picture->reoptimize();

            return response()->json([
                'success' => true,
                'message' => 'Recompression lancée avec succès',
                'picture_id' => $picture->id,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recompression: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a picture has invalid optimized versions (0 bytes files)
     */
    public function checkHealth(int $pictureId): JsonResponse
    {
        try {
            $picture = Picture::with('optimizedPictures')->findOrFail($pictureId);
            $hasInvalidFiles = $picture->hasInvalidOptimizedPictures();

            $invalidFiles = [];
            if ($hasInvalidFiles) {
                foreach ($picture->optimizedPictures as $optimized) {
                    if (\Storage::disk('public')->exists($optimized->path)) {
                        $size = \Storage::disk('public')->size($optimized->path);
                        if ($size === 0) {
                            $invalidFiles[] = [
                                'variant' => $optimized->variant,
                                'format' => $optimized->format,
                                'path' => $optimized->path,
                                'size' => $size,
                            ];
                        }
                    }
                }
            }

            return response()->json([
                'picture_id' => $picture->id,
                'filename' => $picture->filename,
                'has_invalid_files' => $hasInvalidFiles,
                'invalid_files' => $invalidFiles,
                'optimized_count' => $picture->optimized_pictures_count ?? $picture->optimizedPictures->count(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification: '.$e->getMessage(),
            ], 500);
        }
    }
}
