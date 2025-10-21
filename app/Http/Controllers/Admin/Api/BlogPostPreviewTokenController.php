<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPostDraft;
use App\Models\BlogPostPreviewToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostPreviewTokenController extends Controller
{
    /**
     * Generate or regenerate a preview token for a blog post draft
     */
    public function store(Request $request, BlogPostDraft $blogPostDraft): JsonResponse
    {
        $request->validate([
            'expires_in_days' => 'sometimes|integer|min:1|max:30',
        ]);

        $expiresInDays = $request->input('expires_in_days', 7);

        // Create or regenerate token
        $token = BlogPostPreviewToken::createForDraft($blogPostDraft, $expiresInDays);

        $expiresAt = $token->expires_at;
        $expiresAtFr = $expiresAt->locale('fr');

        return response()->json([
            'success' => true,
            'message' => 'Lien de prévisualisation généré avec succès',
            'data' => [
                'id' => $token->id,
                'token' => $token->token,
                'url' => $token->getPreviewUrl(),
                'expires_at' => $expiresAt->toIso8601String(),
                // @phpstan-ignore method.nonObject (locale() returns Carbon, not string)
                'expires_at_human' => $expiresAtFr->diffForHumans(),
            ],
        ]);
    }

    /**
     * Revoke a preview token
     */
    public function destroy(BlogPostPreviewToken $blogPostPreviewToken): JsonResponse
    {
        $blogPostPreviewToken->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lien de prévisualisation révoqué avec succès',
        ]);
    }

    /**
     * Get the current preview token for a blog post draft
     */
    public function show(BlogPostDraft $blogPostDraft): JsonResponse
    {
        $token = $blogPostDraft->previewTokens()
            ->valid()
            ->latest()
            ->first();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun lien de prévisualisation actif',
                'data' => null,
            ], 404);
        }

        $expiresAt = $token->expires_at;
        $expiresAtFr = $expiresAt->locale('fr');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $token->id,
                'token' => $token->token,
                'url' => $token->getPreviewUrl(),
                'expires_at' => $expiresAt->toIso8601String(),
                // @phpstan-ignore method.nonObject (locale() returns Carbon, not string)
                'expires_at_human' => $expiresAtFr->diffForHumans(),
            ],
        ]);
    }
}
