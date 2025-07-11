<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" prefix="og: https://ogp.me/ns#">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#F78E57" />
        <meta name="author" content="Sofiane Lasri">
        
        <!-- Favicon with theme support -->
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml" media="(prefers-color-scheme: light)">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml" media="(prefers-color-scheme: dark)">
        
        <!-- Generic Open Graph tags -->
        <meta property="og:locale" content="{{ app()->getLocale() }}" />
        <meta property="og:locale:alternate" content="fr" />
        <meta property="og:locale:alternate" content="en" />
        <meta property="og:site_name" content="{{ config('app.name', 'SofianeLasri') }}" />

        <title inertia>{{ config('app.name', 'SofianeLasri') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,700" rel="stylesheet" />

        @routes
        @vite(['resources/js/public-app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
