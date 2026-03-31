<!DOCTYPE html>
@php
    $supported = ['ar', 'en', 'fr', 'de', 'ru', 'es', 'it', 'hi', 'ja', 'fa', 'zh', 'ur'];

    $segmentLang = request()->segment(1);
    $locale = in_array($segmentLang, $supported) ? $segmentLang : 'en';
@endphp

<html
    id="html-root"
    data-theme="light"
    lang="en"
>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SceMory</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://unpkg.com/maplibre-gl@latest/dist/maplibre-gl.css" rel="stylesheet" />
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
</head>

<body class="app-body">
    <div id="app"></div>

    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.js" defer></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: "toast-top-right",
            timeOut: "5000"
        };
    </script>

    {!! Toastr::message() !!}

    <script src="https://unpkg.com/maplibre-gl@latest/dist/maplibre-gl.js"></script>
    <script src="https://cdn.maptiler.com/mapbox-gl-js/plugins/mapbox-gl-rtl-text/v0.2.3/mapbox-gl-rtl-text.js"></script>
</body>

</html>
