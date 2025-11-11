<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomEmojiRequest;
use App\Models\CustomEmoji;
use App\Models\Picture;
use App\Services\UploadedFilesService;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CustomEmojiController extends Controller
{
    public function __construct(protected UploadedFilesService $uploadedFilesService) {}

    /**
     * Display a listing of custom emojis.
     */
    public function index(): JsonResponse
    {
        $emojis = CustomEmoji::with(['picture.optimizedPictures'])
            ->orderBy('name')
            ->get();

        return response()->json($emojis);
    }

    /**
     * Get custom emojis formatted for the editor (lightweight).
     */
    public function forEditor(): JsonResponse
    {
        $emojis = CustomEmoji::with(['picture.optimizedPictures'])
            ->orderBy('name')
            ->get()
            ->map(function (CustomEmoji $emoji) {
                return [
                    'name' => $emoji->name,
                    'preview_url' => $emoji->getPreviewUrl(),
                ];
            });

        return response()->json($emojis);
    }

    /**
     * Store a newly created custom emoji.
     */
    public function store(CustomEmojiRequest $request): JsonResponse
    {
        try {
            // Upload and optimize the picture
            $picture = $this->uploadedFilesService->storeAndOptimizeUploadedPicture(
                $request->file('picture')
            );

            // Create the custom emoji
            $emoji = CustomEmoji::create([
                'name' => $request->input('name'),
                'picture_id' => $picture->id,
            ]);

            // Load relationships for response
            $emoji->load(['picture.optimizedPictures']);

            return response()->json($emoji, Response::HTTP_CREATED);
        } catch (Exception $e) {
            // Clean up uploaded picture if emoji creation failed
            if (isset($picture) && $picture instanceof Picture) {
                $picture->delete();
            }

            return response()->json([
                'message' => 'Erreur lors de la création de l\'emoji: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified custom emoji.
     */
    public function show(int $id): JsonResponse
    {
        $emoji = CustomEmoji::with(['picture.optimizedPictures'])
            ->findOrFail($id);

        return response()->json($emoji);
    }

    /**
     * Remove the specified custom emoji.
     */
    public function destroy(int $id): Response
    {
        $emoji = CustomEmoji::findOrFail($id);

        // Get the picture_id before deleting (in case we need to manually clean up)
        $pictureId = $emoji->picture_id;

        // Delete the emoji (cascade will delete the picture)
        $emoji->delete();

        return response()->noContent();
    }
}
