<?php

use Illuminate\Support\Facades\Route;

Route::get('/mobile/auth/callback', fn () => response(
    'Open this link on an Android device with Scemory installed to finish signing in.',
    200,
    [
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Cache-Control' => 'no-store, private',
        'X-Robots-Tag' => 'noindex, nofollow',
    ]
));
Route::get('/robots.txt', function () {

    $content = [
        'User-agent: *',
        'Allow: /',
        'Allow: /historical',
        'Allow: /all_events',
        'Allow: /single_event/',
        'Disallow: /admin',
        'Disallow: /admin/',
        'Disallow: /auth',
        'Disallow: /profile',
        'Disallow: /v1/',
        'Disallow: /api/',
    ];

    return response(implode("\n", $content), 200)
        ->header('Content-Type', 'text/plain');

});

Route::any('/api/{any}', fn () => response()->json(['message' => 'Not found.'], 404))
    ->where('any', '.*');

Route::get('/{any}', function () {
    return view('index');
})->where('any', '.*');
