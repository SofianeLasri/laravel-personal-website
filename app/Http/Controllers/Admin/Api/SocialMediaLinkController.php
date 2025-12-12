<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SocialMediaLinkRequest;
use App\Models\SocialMediaLink;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;

class SocialMediaLinkController extends Controller
{
    /**
     * @return Collection<int, SocialMediaLink>
     */
    public function index(): Collection
    {
        return SocialMediaLink::all();
    }

    public function store(SocialMediaLinkRequest $request): SocialMediaLink
    {
        return SocialMediaLink::create($request->validated());
    }

    public function show(int $id): SocialMediaLink
    {
        return SocialMediaLink::findOrFail($id);
    }

    public function update(SocialMediaLinkRequest $request, int $id): SocialMediaLink
    {
        $socialMediaLink = SocialMediaLink::findOrFail($id);
        $socialMediaLink->update($request->validated());

        return $socialMediaLink;
    }

    public function destroy(int $id): Response
    {
        $socialMediaLink = SocialMediaLink::findOrFail($id);
        $socialMediaLink->delete();

        return response()->noContent();
    }
}
