<?php

use App\Http\Middleware\BlockBadBots;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\SecurityResponseHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Services\Commands\BackupPendingOriginals::class,
        \App\Services\Commands\CleanupTemporaryOriginals::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) =>
            $request->is('api/*') ? null : '/login'
        );
        $middleware->append(SecurityResponseHeaders::class);
        $middleware->api(SetLocale::class);
        $middleware->api(BlockBadBots::class);
        $middleware->api(EnsureActiveAccount::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn (\Illuminate\Http\Request $request, \Throwable $e) =>
            $request->is('api/*') || $request->expectsJson()
        );
    })->create();
