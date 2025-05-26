<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CertificationRequest;
use App\Models\Certification;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CertificationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Certification::all());
    }

    public function store(CertificationRequest $request): JsonResponse
    {
        $certification = Certification::create($request->validated());

        return response()->json($certification, Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $certification = Certification::findOrFail($id);

        return response()->json($certification);
    }

    public function update(CertificationRequest $request, int $id): JsonResponse
    {
        $certification = Certification::findOrFail($id);
        $certification->update($request->validated());

        return response()->json($certification);
    }

    public function destroy(int $id): Response
    {
        $certification = Certification::findOrFail($id);
        $certification->delete();

        return response()->noContent();
    }
}
