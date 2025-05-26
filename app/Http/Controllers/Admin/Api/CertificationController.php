<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CertificationRequest;
use App\Models\Certification;

class CertificationController extends Controller
{
    public function index()
    {
        return Certification::all();
    }

    public function store(CertificationRequest $request)
    {
        return Certification::create($request->validated());
    }

    public function show(Certification $certification)
    {
        return $certification;
    }

    public function update(CertificationRequest $request, Certification $certification)
    {
        $certification->update($request->validated());

        return $certification;
    }

    public function destroy(Certification $certification)
    {
        $certification->delete();

        return response()->json();
    }
}
