<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LanguageController extends Controller
{
    public function setLanguage(Request $request): JsonResponse
    {
        $language = $request->input('language');

        if (! in_array($language, ['fr', 'en'])) {
            return response()->json(['error' => 'Invalid language'], 400);
        }

        $cookie = Cookie::make('language_preference', $language, 60 * 24 * 365); // 1 year

        return response()->json(['success' => true])
            ->withCookie($cookie);
    }
}
