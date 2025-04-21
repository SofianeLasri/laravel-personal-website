<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SocialMediaLinkRequest;
use App\Models\SocialMediaLink;

class SocialMediaLinkController extends Controller
{
    public function index()
    {
        return SocialMediaLink::all();
    }

    public function store(SocialMediaLinkRequest $request)
    {
        return SocialMediaLink::create($request->validated());
    }

    public function show(int $id)
    {
        $socialMediaLink = SocialMediaLink::findOrFail($id);

        return $socialMediaLink;
    }

    public function update(SocialMediaLinkRequest $request, int $id)
    {
        $socialMediaLink = SocialMediaLink::findOrFail($id);
        $socialMediaLink->update($request->validated());

        return $socialMediaLink;
    }

    public function destroy(int $id)
    {
        $socialMediaLink = SocialMediaLink::findOrFail($id);
        $socialMediaLink->delete();

        return response()->noContent();
    }
}
