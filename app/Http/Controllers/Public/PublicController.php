<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

abstract class PublicController extends Controller
{
    /**
     * Get the browser's preferred language from the request attributes.
     * This value is set by the BrowserLanguageMiddleware.
     */
    protected function getBrowserLanguage(Request $request): ?string
    {
        return $request->attributes->get('browser_language');
    }
}
