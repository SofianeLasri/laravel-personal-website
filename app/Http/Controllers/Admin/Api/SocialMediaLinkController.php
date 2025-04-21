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

    public function show(SocialMediaLink $socialMediaLink)
    {
        return $socialMediaLink;
    }

    public function update(SocialMediaLinkRequest $request, SocialMediaLink $socialMediaLink)
    {
        $socialMediaLink->update($request->validated());

        return $socialMediaLink;
    }

    public function destroy(SocialMediaLink $socialMediaLink)
    {
        $socialMediaLink->delete();

        return response()->json();
    }
}
