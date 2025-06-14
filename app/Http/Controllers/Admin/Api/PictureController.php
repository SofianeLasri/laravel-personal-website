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
}
