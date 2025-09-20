<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PictureRequest;
use App\Models\Picture;
use App\Services\UploadedFilesService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Log;
use Storage;
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
            if (! $picture->hasValidOriginalPath() || $picture->path_original === null || ! Storage::disk('public')->exists($picture->path_original)) {
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
                    if (Storage::disk('public')->exists($optimized->path)) {
                        $size = Storage::disk('public')->size($optimized->path);
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

    /**
     * Rotate a picture by the specified angle
     */
    public function rotate(Request $request, int $pictureId): JsonResponse
    {
        $request->validate([
            'angle' => 'required|integer|in:90,180,270',
        ]);

        try {
            $picture = Picture::with('optimizedPictures')->findOrFail($pictureId);

            /** @var int $angle */
            $angle = $request->input('angle');

            // Check if original file exists
            if (! $picture->hasValidOriginalPath() || $picture->path_original === null || ! Storage::disk('public')->exists($picture->path_original)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier original n\'existe pas',
                ], 422);
            }

            // Determine which driver to use
            $driver = extension_loaded('imagick') ? new ImagickDriver : new GdDriver;
            $manager = new ImageManager($driver);

            // Rotate original image
            // At this point, we've already checked that path_original is not null
            $originalPath = Storage::disk('public')->path($picture->path_original);
            $image = $manager->read($originalPath);

            // Rotate counterclockwise (Intervention Image convention)
            $image->rotate(-$angle);

            // Save the rotated original
            $image->save($originalPath);

            // Update dimensions in database
            $newWidth = $image->width();
            $newHeight = $image->height();

            // Rotate all optimized versions
            foreach ($picture->optimizedPictures as $optimized) {
                if (Storage::disk('public')->exists($optimized->path)) {
                    try {
                        $optimizedPath = Storage::disk('public')->path($optimized->path);
                        $optimizedImage = $manager->read($optimizedPath);
                        $optimizedImage->rotate(-$angle);
                        $optimizedImage->save($optimizedPath);
                    } catch (Exception $e) {
                        // Log error but continue with other optimized versions
                        Log::error("Failed to rotate optimized picture {$optimized->id}: ".$e->getMessage());
                    }
                }
            }

            // Update original picture dimensions
            $picture->update([
                'width' => $newWidth,
                'height' => $newHeight,
            ]);

            // If CDN is configured, sync the rotated files
            $cdnDisk = config('app.cdn_disk');
            if ($cdnDisk && is_string($cdnDisk)) {
                try {
                    // Upload rotated original to CDN
                    // path_original is guaranteed to be non-null here
                    $content = Storage::disk('public')->get($picture->path_original);

                    if ($content !== null) {
                        Storage::disk($cdnDisk)->put($picture->path_original, $content);
                    }

                    // Upload rotated optimized versions to CDN
                    foreach ($picture->optimizedPictures as $optimized) {
                        if (Storage::disk('public')->exists($optimized->path)) {
                            $optimizedContent = Storage::disk('public')->get($optimized->path);
                            if ($optimizedContent !== null) {
                                Storage::disk($cdnDisk)->put($optimized->path, $optimizedContent);
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Log CDN sync error but don't fail the entire operation
                    Log::error('Failed to sync rotated images to CDN: '.$e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => "L'image a été tournée de {$angle}° avec succès",
                'picture' => [
                    'id' => $picture->id,
                    'width' => $newWidth,
                    'height' => $newHeight,
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la rotation: '.$e->getMessage(),
            ], 500);
        }
    }
}
