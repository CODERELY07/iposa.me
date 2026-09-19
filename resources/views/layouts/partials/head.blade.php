<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0c0a09">
@auth
    {{-- Offline sales are queued per user; the page only syncs the queue of whoever is logged in. --}}
    <meta name="user-id" content="{{ auth()->id() }}">
@endauth

<title>{{ isset($title) && $title ? $title.' · ' : '' }}iPOSa</title>

{{-- Icons & installable app (PWA) --}}
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="application-name" content="iPOSa">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="iPOSa">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

<script>
    try {
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.remove('dark');
        }
    } catch (e) {}
</script>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|jetbrains-mono:400,500,600|instrument-serif:400,400i&display=swap" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
