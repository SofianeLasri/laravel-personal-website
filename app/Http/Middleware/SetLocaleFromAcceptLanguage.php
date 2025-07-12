<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromAcceptLanguage
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // First check if there's a language preference cookie
        $languagePreference = $request->cookie('language_preference');

        if ($languagePreference && in_array($languagePreference, ['fr', 'en'])) {
            // Use the language preference from cookie
            App::setLocale($languagePreference);
        } else {
            // Default to French
            App::setLocale('fr');

            // Store the browser's preferred language in the request for potential use by the frontend
            $acceptLanguage = $request->header('Accept-Language');
            if ($acceptLanguage) {
                $browserLanguage = $this->parseAcceptLanguage($acceptLanguage);
                $request->attributes->set('browser_language', $browserLanguage);
            }
        }

        return $next($request);
    }

    /**
     * Parse the Accept-Language header to get the preferred language.
     */
    private function parseAcceptLanguage(string $acceptLanguage): string
    {
        // Parse Accept-Language header format: "fr-FR,fr;q=0.9,en;q=0.8"
        $languages = [];

        // Split by comma to get individual language entries
        $parts = explode(',', $acceptLanguage);

        foreach ($parts as $part) {
            $part = trim($part);

            // Check if there's a quality value (q=x.x)
            if (strpos($part, ';q=') !== false) {
                [$language, $quality] = explode(';q=', $part, 2);
                $quality = (float) $quality;
            } else {
                $language = $part;
                $quality = 1.0; // Default quality if not specified
            }

            // Extract the language code (before any hyphen)
            $languageCode = strtolower(explode('-', trim($language))[0]);

            $languages[$languageCode] = $quality;
        }

        // Sort by quality value in descending order
        arsort($languages);

        // Return the highest quality language code
        return array_key_first($languages) ?: 'en';
    }

    /**
     * Check if the given language code is French.
     */
    private function isFrench(string $languageCode): bool
    {
        return $languageCode === 'fr';
    }
}
