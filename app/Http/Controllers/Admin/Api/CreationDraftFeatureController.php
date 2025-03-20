<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreationDraftFeatureRequest;
use App\Models\CreationDraft;
use App\Models\CreationDraftFeature;
use Illuminate\Http\JsonResponse;

class CreationDraftFeatureController extends Controller
{
    public function index(CreationDraft $creationDraft): JsonResponse
    {
        return response()->json($creationDraft->features);
    }

    public function store(CreationDraftFeatureRequest $request, CreationDraft $creationDraft): JsonResponse
    {
        $creationDraftFeature = $creationDraft->features()->create($request->validated());

        return response()->json($creationDraftFeature);
    }

    public function show(CreationDraftFeature $creationDraftFeature): JsonResponse
    {
        return response()->json($creationDraftFeature);
    }

    public function update(CreationDraftFeature $creationDraftFeature): JsonResponse
    {
        $creationDraftFeature->update(request()->validated());

        return response()->json($creationDraftFeature);
    }

    public function destroy(CreationDraftFeature $creationDraftFeature): JsonResponse
    {
        $creationDraftFeature->delete();

        return response()->json(['message' => 'Feature deleted']);
    }
}
