<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PersonRequest;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PersonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json(Person::all()->load('picture'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PersonRequest $request): JsonResponse
    {
        $person = Person::create($request->validated())->load('picture');

        return response()->json($person, Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Person $person): JsonResponse
    {
        return response()->json($person->load('picture'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PersonRequest $request, Person $person): JsonResponse
    {
        $person->update($request->validated());

        return response()->json($person->load('picture'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Person $person): Response
    {
        $person->delete();

        return response()->noContent();
    }

    public function checkAssociations(Person $person): JsonResponse
    {
        $hasCreations = $person->creations()->exists();
        $hasCreationDrafts = $person->creationDrafts()->exists();

        return response()->json([
            'has_associations' => $hasCreations || $hasCreationDrafts,
            'creations_count' => $person->creations()->count(),
            'creation_drafts_count' => $person->creationDrafts()->count(),
        ]);
    }
}
