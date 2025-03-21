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
        return response()->json(Person::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PersonRequest $request): JsonResponse
    {
        $person = Person::create($request->validated());

        return response()->json($person, Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Person $person): JsonResponse
    {
        return response()->json($person);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PersonRequest $request, Person $person): JsonResponse
    {
        $person->update($request->validated());

        return response()->json($person);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Person $person): Response
    {
        $person->delete();

        return response()->noContent();
    }
}
