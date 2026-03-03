<!DOCTYPE html>
@php
    $locale = app()->getLocale();
    $dir = in_array($locale, ['ar']) ? 'rtl' : 'ltr';
@endphp

<html id="html-root" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scene  Memory </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body class="app-body">
    <div id="app"></div>

    {{-- <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if(!savedTheme){
                localStorage.setItem('theme', 'light');
            };
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script> --}}
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.js" defer></script>
</body>
</html>
<script>
    const html = document.getElementById('html-root');
    const lang = localStorage.getItem('language') || 'ar';
    html.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
    html.setAttribute('lang', lang);
</script>
