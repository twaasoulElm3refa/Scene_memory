<?php

use App\Http\Controllers\api\admin\UserController;
use App\Http\Controllers\api\auth\AuthController;
use App\Http\Controllers\api\auth\GoogleAuthController;
use App\Http\Controllers\api\auth\SocialAuthController;
use App\Http\Controllers\api\home\CategoryController;
use App\Http\Controllers\api\home\CitiesController;
use App\Http\Controllers\api\home\CountriesController;
use App\Http\Controllers\api\home\EventController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('users')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware(['throttle:3,1', 'guest']);
        Route::post('/login', [AuthController::class, 'login'])->middleware(['throttle:7,1', 'guest']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware(['throttle:5,1', 'guest']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware(['throttle:5,1', 'guest']);

        Route::get('/google-login', [GoogleAuthController::class, 'googleLogin'])->middleware('guest');
        Route::get('/google-callback', [GoogleAuthController::class, 'googleCallback'])->middleware('guest');
        Route::get('/facebook-login', [SocialAuthController::class, 'redirectToFacebook']);
        Route::get('/facebook-callback', [SocialAuthController::class, 'handleFacebookCallback']);

        Route::middleware(['auth:sanctum', 'throttle:15,1'])->group(function () {
            Route::get('/profile', [AuthController::class, 'profile']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::put('/password', [AuthController::class, 'updatePassword']);
        });
    });

    Route::prefix('categories')->middleware(['auth:sanctum', 'throttle:15,1'])->group(function () {
        Route::get('/', [CategoryController::class,  'index']);
        Route::get('/all/paginated', [CategoryController::class,  'paginated']);
        Route::get('/{id}', [CategoryController::class,  'single']);
        Route::post('/create', [CategoryController::class,  'create']);
    });

    Route::prefix('countries')->middleware(['auth:sanctum', 'throttle:15,1'])->group(function () {
        Route::get('/', [CountriesController::class,  'index']);
        Route::get('/all/count', [CountriesController::class,  'count']);
        Route::get('/{id}', [CountriesController::class,  'single']);
    });

    Route::prefix('cities')->middleware(['auth:sanctum', 'throttle:15,1'])->group(function () {
        Route::get('/', [CitiesController::class,  'index']);
        Route::get('/{id}', [CitiesController::class,  'single']);
    });

    Route::prefix('events')->middleware(['auth:sanctum', 'throttle:15,1'])->group(function () {
        Route::get('/', [EventController::class, 'all']);
        Route::get('/count', [EventController::class, 'count']);
        Route::get('/memories', [EventController::class, 'memories']);
        Route::get('/{city_id}/{category_id}', [EventController::class,  'index']);
        Route::get('/{city}/marker/search', [EventController::class,  'MarkerSearch']);
        Route::get('/{slug}/single/get', [EventController::class,  'single']);
    });

    Route::prefix('users')->middleware(['auth:sanctum', AdminMiddleware::class, 'throttle:30,1'])->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/all/get', [UserController::class, 'all']);
        Route::get('/all/count', [UserController::class, 'count']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/create', [UserController::class, 'create']);
        Route::post('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
    });
});
