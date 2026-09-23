<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $htmlDir ?? 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.gtm')

        {{-- Inline page-paint background. Matches `--background` so the
             first paint doesn't flash white before CSS loads. --}}
        <style>
            html {
                background-color: #F8F7F4;
            }
        </style>

        <title data-inertia>{{ config('app.name', 'King Social Hub') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#7c3aed">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="King Hub">

        <!-- Be Vietnam Pro for all UI/display text; JetBrains Mono for code. -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @include('partials.gtm-noscript')
        @inertia

        <script>
            // Đăng ký Service Worker cho PWA & Push Notification
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('/sw.js', { scope: '/' })
                        .catch(function (err) {
                            console.warn('[SW] Đăng ký thất bại:', err);
                        });
                });
            }
        </script>
    </body>
</html>
