<?php

use App\Http\Controllers\api\admin\EventAdminController;
use App\Http\Controllers\api\admin\UserController;
use App\Http\Controllers\api\auth\AuthController;
use App\Http\Controllers\api\auth\GoogleAuthController;
use App\Http\Controllers\api\auth\SocialAuthController;
use App\Http\Controllers\api\home\CategoryController;
use App\Http\Controllers\api\home\CitiesController;
use App\Http\Controllers\api\home\CountriesController;
use App\Http\Controllers\api\home\EventController;
use App\Http\Controllers\api\home\SubCategoryController;
use App\Http\Controllers\api\admin\UserCountsController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // AUTH
    Route::prefix('users')->group(function () {
        // Auth Routes
        Route::post('/register', [AuthController::class, 'register'])->middleware(['throttle:3,1', 'guest']);
        Route::post('/login', [AuthController::class, 'login'])->middleware(['throttle:7,1', 'guest']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware(['throttle:5,1', 'guest']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware(['throttle:5,1', 'guest']);

        // Social Auth
        Route::get('/google-login', [GoogleAuthController::class, 'googleLogin'])->middleware('guest');
        Route::get('/google-callback', [GoogleAuthController::class, 'googleCallback'])->middleware('guest');
        Route::get('/facebook-login', [SocialAuthController::class, 'redirectToFacebook']);
        Route::get('/facebook-callback', [SocialAuthController::class, 'handleFacebookCallback']);

        // profile Routes
        Route::middleware(['auth:sanctum', 'throttle:15,1'])->group(function () {
            Route::get('/profile', [AuthController::class, 'profile']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::put('/password', [AuthController::class, 'updatePassword']);
        });
    });

    // CATEGORIES CRUD
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class,  'index']);
        Route::get('/all/paginated', [CategoryController::class,  'paginated']);
        Route::get('/{id}', [CategoryController::class,  'single']);
        Route::get('/{id}/sub_categories/get', [CategoryController::class,  'sub_categories']); 
        Route::post('/create', [CategoryController::class,  'create'])->middleware('auth:sanctum', AdminMiddleware::class);
        Route::post('/edit/{id}/update/edit', [CategoryController::class,  'update'])->middleware('auth:sanctum', AdminMiddleware::class);
        Route::delete('/delete/{id}/delete/delete', [CategoryController::class,  'delete'])->middleware('auth:sanctum', AdminMiddleware::class);
    });

    // SUB CATEGORIES CRUD
    Route::prefix('sub_categories')->middleware(['throttle:15,1'])->group(function () {
        Route::get('/', [SubCategoryController::class,  'index']);
        Route::get('/all/paginated', [SubCategoryController::class,  'paginated']);
        Route::get('/{id}', [SubCategoryController::class,  'single']);
        Route::post('/create', [SubCategoryController::class,  'create'])->middleware(AdminMiddleware::class,'auth:sanctum');
        Route::post('/update/{id}', [SubCategoryController::class,  'update'])->middleware(AdminMiddleware::class,'auth:sanctum');
        Route::delete('/delete/{id}', [SubCategoryController::class,  'delete'])->middleware(AdminMiddleware::class,'auth:sanctum');
    });

    // COUNTRIES CRUD
    Route::prefix('countries')->group(function () {
        Route::get('/', [CountriesController::class,  'index']);
        Route::get('/paginated/get', [CountriesController::class,  'paginated'])->middleware('throttle:15,1');
        Route::get('/all/count', [CountriesController::class,  'count'])->middleware('throttle:15,1');
        Route::get('/{id}', [CountriesController::class,  'single'])->middleware('throttle:15,1');
        Route::post('/create', [CountriesController::class,  'create'])->middleware(AdminMiddleware::class,'auth:sanctum');
        Route::post('/{id}/update', [CountriesController::class,  'update'])->middleware(AdminMiddleware::class,'auth:sanctum');
        Route::delete('/{id}/delete', [CountriesController::class,  'delete'])->middleware(AdminMiddleware::class,'auth:sanctum');
    });

    // CITIES CRUD
    Route::prefix('cities')->group(function () {
        Route::get('/', [CitiesController::class,  'index'])->middleware('throttle:15,1');
        Route::get('/paginated/get', [CitiesController::class,  'paginated'])->middleware('throttle:15,1');
        Route::get('/{id}', [CitiesController::class,  'single'])->middleware('throttle:15,1');
        Route::post('/create', [CitiesController::class,  'create'])->middleware(AdminMiddleware::class,'auth:sanctum');
        Route::post('/{id}/update', [CitiesController::class,'update'])->middleware(AdminMiddleware::class,'auth:sanctum');
        Route::delete('/{id}/delete', [CitiesController::class,'delete'])->middleware(AdminMiddleware::class,'auth:sanctum');
    });

    // Events 
    Route::prefix('events')->middleware(['throttle:45,1'])->group(function () {
        // Home Events
        Route::get('/', [EventController::class, 'all']);
        Route::get('/count', [EventController::class, 'count']);
        Route::get('/memories', [EventController::class, 'memories']);
        Route::get('/{city_id}/{sub_category_id}', [EventController::class,  'index']);
        Route::get('/{city}/marker/search', [EventController::class,  'MarkerSearch']);
        Route::get('/{slug}/single/get', [EventController::class,  'single']);

        // Events CRUD
        Route::post('/create', [EventAdminController::class,  'create'])->middleware(AdminMiddleware::class,'auth:sanctum');
        Route::post('/{id}/update', [EventAdminController::class,  'update'])->middleware(AdminMiddleware::class,'auth:sanctum');
        Route::delete('/{id}/delete', [EventAdminController::class,  'destroy'])->middleware(AdminMiddleware::class,'auth:sanctum');
    });

    Route::prefix('users')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        // USERS CRUD
        Route::get('/', [UserController::class, 'index']);
        Route::get('/all/get', [UserController::class, 'all']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/create', [UserController::class, 'create']);
        Route::post('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        
        // USER COUNTS
        Route::get('/all/count', [UserCountsController::class, 'count']);
        Route::get('/all/last-login', [UserCountsController::class, 'last_login']);
        Route::get('/all/new-users', [UserCountsController::class, 'NewUsers']);
    });
});
