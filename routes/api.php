<?php

use App\Http\Controllers\api\admin\ContactController;
use App\Http\Controllers\api\admin\EventAdminController;
use App\Http\Controllers\api\admin\EventImageController;
use App\Http\Controllers\api\admin\FooterController;
use App\Http\Controllers\api\admin\NewsletterController;
use App\Http\Controllers\api\admin\UserController;
use App\Http\Controllers\api\admin\UserCountsController;
use App\Http\Controllers\api\auth\AuthController;
use App\Http\Controllers\api\auth\GoogleAuthController;
use App\Http\Controllers\api\auth\SocialAuthController;
use App\Http\Controllers\api\home\CategoryController;
use App\Http\Controllers\api\home\CitiesController;
use App\Http\Controllers\api\home\CountriesController;
use App\Http\Controllers\api\home\EventController;
use App\Http\Controllers\api\home\SubCategoryController;
use App\Http\Controllers\api\userDshboard\UserDashboardController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\OwnerMiddleware;
use App\Http\Middleware\OwnEvent;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // AUTH
    Route::prefix('users')->group(function () {
        // Auth Routes
        Route::post('/register', [AuthController::class, 'register'])->middleware(['throttle:3,1']);
        Route::post('/login', [AuthController::class, 'login'])->middleware(['throttle:4,1']);
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
        Route::post('/create', [SubCategoryController::class,  'create'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/update/{id}', [SubCategoryController::class,  'update'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::delete('/delete/{id}', [SubCategoryController::class,  'delete'])->middleware(AdminMiddleware::class, 'auth:sanctum');
    });

    // COUNTRIES CRUD
    Route::prefix('countries')->group(function () {
        Route::get('/', [CountriesController::class,  'index']);
        Route::get('/paginated/get', [CountriesController::class,  'paginated'])->middleware('throttle:15,1');
        Route::get('/all/count', [CountriesController::class,  'count'])->middleware('throttle:15,1');
        Route::get('/{id}', [CountriesController::class,  'single'])->middleware('throttle:15,1');
        Route::post('/create', [CountriesController::class,  'create'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/{id}/update', [CountriesController::class,  'update'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::delete('/{id}/delete', [CountriesController::class,  'delete'])->middleware(AdminMiddleware::class, 'auth:sanctum');
    });

    // CITIES CRUD
    Route::prefix('cities')->group(function () {
        Route::get('/', [CitiesController::class,  'index'])->middleware('throttle:15,1');
        Route::get('/paginated/get', [CitiesController::class,  'paginated'])->middleware('throttle:15,1');
        Route::get('/{id}', [CitiesController::class,  'single'])->middleware('throttle:15,1');
        Route::post('/create', [CitiesController::class,  'create'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/{id}/update', [CitiesController::class, 'update'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::delete('/{id}/delete', [CitiesController::class, 'delete'])->middleware(AdminMiddleware::class, 'auth:sanctum');
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
        Route::post('/create', [EventAdminController::class,  'create'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/{id}/update', [EventAdminController::class,  'update'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::delete('/{id}/delete', [EventAdminController::class,  'destroy'])->middleware(AdminMiddleware::class, 'auth:sanctum');
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

    Route::prefix('')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        // CONTACTS
        Route::get('/contacts', [ContactController::class, 'all']);
        Route::get('/contacts/{id}', [ContactController::class, 'single']);
        Route::post('/contacts/create', [ContactController::class, 'create']);
        Route::post('/contacts/respond/{id}', [ContactController::class, 'respond']);
        Route::delete('/contacts/delete/{id}', [ContactController::class, 'delete']);

        // Newsletters
        Route::get('/newsletters', [NewsletterController::class, 'all']);
        Route::post('/newsletters/create', [NewsletterController::class, 'create']);
        Route::post('/newsletters/respond/{id}', [NewsletterController::class, 'respond']);

        // Footer
        Route::get('/footer', [FooterController::class, 'all']);
        Route::post('/footer/update', [FooterController::class, 'update']);

    });

    // Event Media
    Route::prefix('event-images')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/{id}', [EventImageController::class, 'allPerEvent']);
        Route::post('/create/{id}', [EventImageController::class, 'create']);
        Route::delete('/{id}/delete', [EventImageController::class, 'delete']);
    });

    // User Dashboard
    Route::prefix('user-dshboard')->middleware(['auth:sanctum', OwnerMiddleware::class])->group(function () {
        Route::get('/my-events', [UserDashboardController::class, 'myEvents']);
        Route::post('/{slug}', [UserDashboardController::class, 'addMedia']);
        Route::delete('/{id}/delete', [EventImageController::class, 'delete']);
        Route::delete('/{id}/destroy', [UserDashboardController::class, 'delete'])->middleware(OwnEvent::class);

        // Create Media
        Route::post('/create/Event', [UserDashboardController::class,  'create'])->middleware(OwnerMiddleware::class, 'auth:sanctum');
        Route::post('/{slug}/update/Event', [UserDashboardController::class,  'update'])->middleware(OwnerMiddleware::class, OwnEvent::class, 'auth:sanctum');

    });
});
