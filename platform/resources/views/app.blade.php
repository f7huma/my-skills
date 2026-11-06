<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#1b1f25">
    @if(config('office.noindex'))<meta name="robots" content="noindex, nofollow, noarchive">@endif
    <title inertia>{{ config('office.short_name') }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @viteReactRefresh
    @vite(["resources/js/app.tsx"])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
