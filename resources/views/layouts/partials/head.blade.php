<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0c0a09">

<title>{{ isset($title) && $title ? $title.' · ' : '' }}iPOSa</title>

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
