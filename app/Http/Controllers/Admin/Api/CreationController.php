<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\Creation;
use App\Models\CreationDraft;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CreationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json(Creation::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'draft_id' => ['required', 'exists:creation_drafts,id'],
        ]);

        /** @var CreationDraft|null $creationDraft */
        $creationDraft = app(CreationDraft::class)->find($request->draft_id);

        if (! $creationDraft) {
            return response()->json(['message' => 'Creation draft not found'], 404);
        }

        try {
            $creation = $creationDraft->toCreation();

            return response()->json($creation, \Symfony\Component\HttpFoundation\Response::HTTP_CREATED);
        } catch (ValidationException $e) {
            return response()->json($e->errors(), 422);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Creation $creation): JsonResponse
    {
        return response()->json($creation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Creation $creation): JsonResponse
    {
        $request->validate([
            'featured' => ['sometimes', 'boolean'],
        ]);

        $creation->update($request->only('featured'));

        return response()->json($creation);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Creation $creation): Response
    {
        $creation->delete();

        return response()->noContent();
    }
}
