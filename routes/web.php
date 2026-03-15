<?php

use Illuminate\Support\Facades\Route;
Route::get('/robots.txt', function () {

    $content = [
        "User-agent: *",
        "Allow: /",
        "Allow: /historical",
        "Allow: /all_events",
        "Allow: /single_event/",
        "Disallow: /admin",
        "Disallow: /admin/",
        "Disallow: /auth",
        "Disallow: /profile",
        "Disallow: /v1/",
        "Disallow: /api/",
    ];

    return response(implode("\n", $content), 200)
        ->header('Content-Type', 'text/plain');

});

Route::get('/{any}', function () {
    return view('index');
})->where('any', '.*');
