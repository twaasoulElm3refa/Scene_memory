<?php

use App\Http\Controllers\api\admin\AdminDashboardController;
use App\Http\Controllers\api\admin\AdminPlanController;
use App\Http\Controllers\api\admin\AdminTagsController;
use App\Http\Controllers\api\admin\auth\AdminAuthController;
use App\Http\Controllers\api\admin\BenefitsController;
use App\Http\Controllers\api\admin\CategoriesCreateController;
use App\Http\Controllers\api\admin\CitiesCreateController;
use App\Http\Controllers\api\admin\ContactController;
use App\Http\Controllers\api\admin\CountriesCreateController;
use App\Http\Controllers\api\admin\EventAdminController;
use App\Http\Controllers\api\admin\EventAdminCreateController;
use App\Http\Controllers\api\admin\EventImageController;
use App\Http\Controllers\api\admin\FooterController;
use App\Http\Controllers\api\admin\NewsletterController;
use App\Http\Controllers\api\admin\NotificationController;
use App\Http\Controllers\api\admin\PurchasesController;
use App\Http\Controllers\api\admin\ReportController;
use App\Http\Controllers\api\admin\RequestController;
use App\Http\Controllers\api\admin\SpecialCoverageRequestController as AdminSpecialCoverageRequestController;
use App\Http\Controllers\api\admin\SubCategoriesCreateController;
use App\Http\Controllers\api\admin\AdminUserDataDeletionController;
use App\Http\Controllers\api\admin\UserController;
use App\Http\Controllers\api\admin\UserCountsController;
use App\Http\Controllers\api\admin\WithdrawlController;
use App\Http\Controllers\api\auth\AuthController;
use App\Http\Controllers\api\auth\DownloadController;
use App\Http\Controllers\api\auth\FacebookAuthController;
use App\Http\Controllers\api\auth\GoogleAuthController;
use App\Http\Controllers\api\home\CartController;
use App\Http\Controllers\api\home\CategoryController;
use App\Http\Controllers\api\home\CitiesController;
use App\Http\Controllers\api\home\CommentController;
use App\Http\Controllers\api\home\CommentInteractionController;
use App\Http\Controllers\api\home\CommentReplyController;
use App\Http\Controllers\api\home\CountriesController;
use App\Http\Controllers\api\home\EventController;
use App\Http\Controllers\api\home\EventUserCreateController;
use App\Http\Controllers\api\home\GateController;
use App\Http\Controllers\api\home\ImageTagsController;
use App\Http\Controllers\api\home\IncomeController;
use App\Http\Controllers\api\home\LeaderboardController;
use App\Http\Controllers\api\home\LikesController;
use App\Http\Controllers\api\home\MediaDeliveryController;
use App\Http\Controllers\api\home\MediaValidationController;
use App\Http\Controllers\api\home\PlanController;
use App\Http\Controllers\api\home\PlanSubscriptionController;
use App\Http\Controllers\api\home\ProfileController;
use App\Http\Controllers\api\home\SpecialCoverageCityController;
use App\Http\Controllers\api\home\SpecialCoverageRequestController;
use App\Http\Controllers\api\home\SubCategoryController;
use App\Http\Controllers\api\home\TagsController;
use App\Http\Controllers\api\home\WhisListController;
use App\Http\Controllers\api\moderation\N8nEventTranslationController;
use App\Http\Controllers\api\owner\CreatorController;
use App\Http\Controllers\api\owner\UserWithdrawlController;
use App\Http\Controllers\api\payment\CollectionController;
use App\Http\Controllers\api\payment\DepositController;
use App\Http\Controllers\api\payment\PaymentController;
use App\Http\Controllers\api\userDshboard\MediaRequestController;
use App\Http\Controllers\api\userDshboard\UserDataDeletionController;
use App\Http\Controllers\api\userDshboard\UserDashboardController;
use App\Http\Controllers\api\webhook\WalletWebhookController;
use App\Http\Controllers\api\webhook\WebhookController;
use App\Http\Controllers\home\HomeController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\OwnEvent;
use App\Http\Middleware\ProtectFacebookEmailRecovery;
use App\Http\Middleware\VerifyN8nWebhookSecret;
use Illuminate\Support\Facades\Route;

require __DIR__.'/gifts.php';

Route::get('/leaderboard/monthly-preview', [LeaderboardController::class, 'monthlyPreview'])
    ->middleware('throttle:60,1')
    ->name('leaderboard.monthly-preview');

Route::get('/leaderboard/weekly-preview', [LeaderboardController::class, 'weeklyPreview'])
    ->middleware('throttle:60,1')->name('leaderboard.weekly-preview');
Route::get('/leaderboard/weekly', [LeaderboardController::class, 'weekly'])
    ->middleware('throttle:60,1')->name('leaderboard.weekly');
Route::get('/leaderboard/weekly-history', [LeaderboardController::class, 'weeklyHistory'])
    ->middleware('throttle:60,1')->name('leaderboard.weekly-history');

Route::prefix('v1')->group(function () {

    // Visitors can contact support and report rights/privacy issues. Inbox access stays private.
    Route::post('/contacts/create', [ContactController::class, 'create'])->middleware('throttle:5,1');
    Route::get('/footer/links', [FooterController::class, 'publicLinks'])->middleware('throttle:60,1');

    Route::post('/moderation/events/{event}/translations', [N8nEventTranslationController::class, 'store'])
        ->middleware(VerifyN8nWebhookSecret::class)
        ->name('moderation.events.translations.store');

    // AUTH
    Route::prefix('users')->group(function () {
        // Auth Routes
        Route::post('/register', [AuthController::class, 'register'])->middleware([ProtectFacebookEmailRecovery::class, 'throttle:4,1']);
        Route::post('/register/verify-otp', [AuthController::class, 'verifyRegisterOtp'])->middleware([ProtectFacebookEmailRecovery::class, 'throttle:6,1']);
        Route::post('/register/resend-otp', [AuthController::class, 'resendRegisterOtp'])->middleware([ProtectFacebookEmailRecovery::class, 'throttle:3,1']);
        Route::post('/login', [AuthController::class, 'login'])->middleware([ProtectFacebookEmailRecovery::class, 'throttle:6,1']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware([ProtectFacebookEmailRecovery::class, 'throttle:5,1', 'guest']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware([ProtectFacebookEmailRecovery::class, 'throttle:5,1', 'guest']);

        // Social Auth
        Route::get('/facebook-login', [FacebookAuthController::class, 'login'])->middleware(['web', 'guest', 'throttle:10,1']);
        Route::get('/facebook-callback', [FacebookAuthController::class, 'callback'])->middleware(['web', 'guest', 'throttle:20,1']);
        Route::post('/facebook-exchange', [FacebookAuthController::class, 'exchange'])->middleware(['web', 'throttle:10,1']);
        Route::get('/google-login', [GoogleAuthController::class, 'googleLogin'])->middleware(['web', 'guest', 'throttle:10,1']);
        Route::get('/google-callback', [GoogleAuthController::class, 'googleCallback'])->middleware(['web', 'throttle:20,1']);
        Route::post('/google-exchange', [GoogleAuthController::class, 'exchangeWebCode'])->middleware(['web', 'throttle:10,1']);
        Route::post('/google-mobile-exchange', [GoogleAuthController::class, 'exchangeMobileCode'])->middleware('throttle:10,1');
        Route::post('/google-link-confirm', [GoogleAuthController::class, 'confirmLinkWithPassword'])->middleware(['web', 'throttle:10,1']);
        Route::post('/google-recovery-start', [GoogleAuthController::class, 'startLegacyRecovery'])->middleware(['web', 'throttle:3,1']);
        Route::post('/google-recovery-confirm', [GoogleAuthController::class, 'confirmLegacyRecovery'])->middleware(['web', 'throttle:6,1']);
        // profile Routes
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('/google-link-start', [GoogleAuthController::class, 'linkStart'])->middleware(['web', 'throttle:10,1']);
            Route::post('/google-unlink', [GoogleAuthController::class, 'unlink'])->middleware(['throttle:10,1']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/profile', [AuthController::class, 'profile']);
            Route::get('/timeline', [ProfileController::class, 'activity']);
            Route::get('/wallet', [AuthController::class, 'wallet']);
            Route::get('/downloads', [DownloadController::class, 'downloads']);
            Route::post('/update-profile', [AuthController::class, 'updateProfile']);
            Route::put('/password', [AuthController::class, 'updatePassword']);

            // User Data Deletion Routes
            Route::post('/data-deletion-request', [UserDataDeletionController::class, 'submit'])->middleware('throttle:5,1');
            Route::post('/data-deletion-confirm', [UserDataDeletionController::class, 'confirm'])->middleware('throttle:5,1');
            Route::get('/data-deletion-status', [UserDataDeletionController::class, 'status']);
            Route::post('/data-deletion-resend-code', [UserDataDeletionController::class, 'resendCode'])->middleware('throttle:3,1');
            Route::post('/data-deletion-cancel', [UserDataDeletionController::class, 'cancel'])->middleware('throttle:10,1');
        });
    });

    // Public Data Deletion status & Meta Callback
    Route::get('/data-deletion-status/{reference}', [UserDataDeletionController::class, 'publicStatus'])->middleware('throttle:30,1');
    Route::post('/facebook/data-deletion', [UserDataDeletionController::class, 'facebookCallback'])->middleware('throttle:30,1');

    Route::prefix('admin')->group(function () {
        Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:6,1');
    });

    Route::prefix('admin')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);

        Route::prefix('data-deletion-requests')->group(function () {
            Route::get('/', [AdminUserDataDeletionController::class, 'index']);
            Route::get('/{id}', [AdminUserDataDeletionController::class, 'show']);
            Route::post('/{id}/process', [AdminUserDataDeletionController::class, 'process']);
            Route::post('/{id}/reject', [AdminUserDataDeletionController::class, 'reject']);
        });

        Route::prefix('special-coverage-requests')->group(function () {
            Route::get('/', [AdminSpecialCoverageRequestController::class, 'index']);
            Route::get('/{id}', [AdminSpecialCoverageRequestController::class, 'show']);
            Route::patch('/{id}', [AdminSpecialCoverageRequestController::class, 'update']);
            Route::post('/{id}/approve', [AdminSpecialCoverageRequestController::class, 'approve']);
            Route::post('/{id}/reject', [AdminSpecialCoverageRequestController::class, 'reject']);
        });

        Route::post('/users/{id}/unban', [UserController::class, 'unban']);
    });

    Route::get('/download/{media}', [DownloadController::class, 'download'])->name('media.download')
        ->middleware(['auth:sanctum', 'throttle:60,1']);
    Route::get('/media/{media}/purchased-original', [DownloadController::class, 'purchasedOriginal'])
        ->name('media.purchased-original')->middleware(['signed', 'throttle:120,1']);
    Route::get('/media/{media}/preview', [MediaDeliveryController::class, 'preview'])
        ->name('media.preview')->middleware('throttle:120,1');
    Route::get('/media/{media}/review-original', [MediaDeliveryController::class, 'reviewOriginal'])
        ->name('media.review-original')->middleware(['signed', 'throttle:60,1']);

    Route::prefix('special-coverage-requests')->middleware('auth:sanctum')->group(function () {
        Route::post('/', [SpecialCoverageRequestController::class, 'store'])->middleware('throttle:30,1');
        Route::post('/cities', [SpecialCoverageCityController::class, 'store'])->middleware('throttle:10,1');
    });

    // CATEGORIES CRUD
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class,  'index']);
        Route::get('/all/paginated', [CategoryController::class,  'paginated']);
        Route::get('/{id}', [CategoryController::class,  'single']);
        Route::get('/{id}/sub_categories/get', [CategoryController::class,  'sub_categories']);
        Route::post('/create', [CategoriesCreateController::class,  'create'])->middleware('auth:sanctum', AdminMiddleware::class);
        Route::post('/edit/{id}/update/edit', [CategoryController::class,  'update'])->middleware('auth:sanctum', AdminMiddleware::class);
        Route::delete('/delete/{id}/delete/delete', [CategoryController::class,  'delete'])->middleware('auth:sanctum', AdminMiddleware::class);
        // 19
    });

    // SUB CATEGORIES CRUD
    Route::prefix('sub_categories')->middleware(['throttle:90,1'])->group(function () {
        Route::get('/', [SubCategoryController::class,  'index']);
        Route::get('/all/paginated', [SubCategoryController::class,  'paginated']);
        Route::get('/{id}', [SubCategoryController::class,  'single']);
        Route::post('/create', [SubCategoriesCreateController::class,  'create'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/update/{id}', [SubCategoryController::class,  'update'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::delete('/delete/{id}', [SubCategoryController::class,  'delete'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        // 25
    });

    // Tags
    Route::prefix('tags')->group(function () {
        Route::get('/search', [TagsController::class,  'search'])->middleware('throttle:90,1');
        Route::get('/', [TagsController::class,  'index']);
        Route::get('/all/paginated', [AdminTagsController::class,  'paginated']);
        Route::get('/{id}', [AdminTagsController::class,  'single'])->middleware('throttle:90,1');
        Route::post('/create', [AdminTagsController::class,  'create'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/update/{id}', [AdminTagsController::class,  'update'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::delete('/delete/{id}', [AdminTagsController::class,  'delete'])->middleware(AdminMiddleware::class, 'auth:sanctum');
    });

    // COUNTRIES CRUD
    Route::prefix('countries')->group(function () {
        Route::get('/', [CountriesController::class,  'index']);
        Route::get('/all/get', [CountriesController::class,  'all']);
        Route::get('/paginated/get', [CountriesController::class,  'paginated'])->middleware('throttle:90,1');
        Route::get('/all/count', [CountriesController::class,  'count'])->middleware('throttle:90,1');
        Route::get('/{id}/cities', [CountriesController::class,  'cities'])->middleware('throttle:90,1');
        Route::get('/{id}', [CountriesController::class,  'single'])->middleware('throttle:90,1');
        Route::post('/create', [CountriesCreateController::class,  'create'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/{id}/update', [CountriesController::class,  'update'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::delete('/{id}/delete', [CountriesController::class,  'delete'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        // 34
    });

    // CITIES CRUD
    Route::prefix('cities')->group(function () {
        Route::get('/', [CitiesController::class,  'index'])->middleware('throttle:90,1');
        Route::get('/paginated/get', [CitiesController::class,  'paginated'])->middleware('throttle:90,1');
        Route::get('/{id}', [CitiesController::class,  'single'])->middleware('throttle:90,1');
        Route::post('/create', [CitiesCreateController::class,  'create'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/{id}/update', [CitiesController::class, 'update'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::delete('/{id}/delete', [CitiesController::class, 'delete'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        // 40
    });

    // Events
    Route::prefix('events')->middleware(['throttle:90,1'])->group(function () {
        // Home Events
        Route::get('/', [EventController::class, 'all']);
        Route::get('/trending', [EventController::class, 'trending']);
        Route::get('/daily', [EventController::class, 'daily']);
        Route::get('/historical', [EventController::class, 'historical']);
        Route::get('/count', [EventController::class, 'count']);
        Route::get('/memories', [EventController::class, 'memories']);
        Route::get('/discovery/search', [EventController::class, 'discovery']);
        Route::post('/marker/search-by-place', [EventController::class, 'searchMarkerByPlace']);
        Route::get('/{city_id?}/{sub_category_id?}', [EventController::class,  'index']);
        Route::get('/{city}/marker/search', [EventController::class,  'MarkerSearch']);
        Route::get('/{slug}/single/get', [EventController::class,  'single']);
        // 48

        // Events CRUD
        Route::post('/create', [EventAdminCreateController::class, 'createAdmin'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/historic', [EventAdminCreateController::class, 'historicAdmin'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/create/user', [EventUserCreateController::class,  'create'])->middleware('auth:sanctum');
        Route::post('/historic/user', [EventUserCreateController::class,  'historic'])->middleware('auth:sanctum');
        Route::post('/{id}/update', [EventAdminController::class,  'update'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::delete('/{id}/delete', [EventAdminController::class,  'destroy'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        // 54
    });

    Route::prefix('media')->middleware(['auth:sanctum', 'throttle:90,1'])->group(function () {
        Route::post('/validate-photo', [MediaValidationController::class, 'validatePhoto']);
    });

    // Purchases CRUD
    Route::prefix('purchases')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/', [PurchasesController::class, 'index']);
        Route::get('/all/count', [PurchasesController::class,  'count'])->middleware('throttle:90,1');
        Route::get('/type/{type}', [PurchasesController::class, 'filter']);
        Route::get('/status/{status}', [PurchasesController::class, 'status']);
        Route::get('/show/{id}', [PurchasesController::class, 'show']);
        Route::post('/update/{id}', [PurchasesController::class, 'update']);
        Route::delete('/delete/{id}', [PurchasesController::class, 'destroy']);
        // 61
    });
    // withdrawals
    Route::prefix('withdraw')->middleware(['auth:sanctum', AdminMiddleware::class, 'throttle:90,1'])->group(function () {
        Route::get('/', [WithdrawlController::class, 'index']);
        Route::get('/all/count', [WithdrawlController::class,  'count']);
        Route::get('/status/{status}', [WithdrawlController::class, 'status']);
        Route::get('/show/{id}', [WithdrawlController::class, 'show']);
        Route::post('/update/{id}', [WithdrawlController::class, 'update']);
        Route::post('/approve/{id}', [WithdrawlController::class, 'approve']);
        Route::post('/reject/{id}', [WithdrawlController::class, 'reject']);
        Route::delete('/delete/{id}', [WithdrawlController::class, 'destroy']);
        // 69
    });

    Route::prefix('users')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        // USERS CRUD
        Route::get('/', [UserController::class, 'index']);
        Route::get('/all/get', [UserController::class, 'all']);
        Route::get('/latest/get', [UserController::class, 'latest']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/create', [UserController::class, 'create']);
        Route::post('/{id}', [UserController::class, 'update']);
        Route::post('/{id}/unban', [UserController::class, 'unban']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        // 76

        // USER COUNTS
        Route::get('/all/count', [UserCountsController::class, 'count']);
        Route::get('/all/last-login', [UserCountsController::class, 'last_login']);
        Route::get('/all/new-users', [UserCountsController::class, 'NewUsers']);
        // 79
    });

    Route::prefix('')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        // CONTACTS
        Route::get('/contacts', [ContactController::class, 'all']);
        Route::get('/contacts/{id}', [ContactController::class, 'single']);
        Route::post('/contacts/respond/{id}', [ContactController::class, 'respond']);
        Route::delete('/contacts/delete/{id}', [ContactController::class, 'delete']);
        // 84

        // Newsletters
        Route::get('/newsletters', [NewsletterController::class, 'all']);
        Route::post('/newsletters/create', [NewsletterController::class, 'create']);
        Route::post('/newsletters/respond/{id}', [NewsletterController::class, 'respond']);
        // 87

        // Footer
        Route::get('/footer', [FooterController::class, 'all']);
        Route::post('/footer/update', [FooterController::class, 'update']);
        // 89
    });

    // Event Media
    Route::prefix('event-images')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/{id}', [EventImageController::class, 'allPerEvent']);
        Route::post('/create/{id}', [EventImageController::class, 'create']);
        Route::delete('/{id}/delete', [EventImageController::class, 'delete']);
        // 92
    });

    // User Dashboard
    Route::prefix('user-dshboard')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/my-events', [UserDashboardController::class, 'myEvents']);
        Route::post('/{slug}', [UserDashboardController::class, 'addMedia'])->middleware('throttle:20,1');
        Route::delete('/{id}/delete', [EventImageController::class, 'delete']);
        // 95

        // Create Event
        Route::post('/create/Event', [EventUserCreateController::class,  'create'])->middleware('auth:sanctum');
        Route::post('/{slug}/update/Event', [UserDashboardController::class,  'update'])->middleware(OwnEvent::class, 'auth:sanctum');
        Route::delete('/{id}/destroy', [UserDashboardController::class, 'delete'])->middleware(OwnEvent::class);
        // 98
    });

    // Upload Media
    Route::prefix('media-request')->middleware(['auth:sanctum', 'throttle:20,1'])->group(function () {
        Route::get('/{id}', [MediaRequestController::class, 'all']);
        // Route::post('/approve/{id}', [MediaRequestController::class, 'approve'])->middleware(AdminMiddleware::class);
        // Route::post('/reject/{id}', [MediaRequestController::class, 'reject'])->middleware(AdminMiddleware::class);
        Route::post('/upload/{id}', [MediaRequestController::class, 'upload']);
        // 100
    });

    // Event Creation Requests
    Route::prefix('requests')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/all/paginated', [RequestController::class, 'allPaginated']);
        Route::get('/{id}', [RequestController::class, 'show']);
        Route::post('/approve/{request_id}', [RequestController::class, 'approve']);
        Route::post('/decline/{request_id}', [RequestController::class, 'decline']);
        Route::delete('/{id}', [RequestController::class, 'destroy']);
        // 105
    });

    Route::prefix('create')->middleware(['auth:sanctum'])->group(function () {
        Route::post('/', [EventUserCreateController::class, 'create']);
        Route::post('/{id}', [HomeController::class, 'update']);
        Route::delete('/{id}', [HomeController::class, 'destroy']);
        // 108
    });

    // Comments
    Route::prefix('comments')->middleware('throttle:90,1')->group(function () {
        Route::get('/{slug}', [CommentController::class, 'allPaginated']);
        Route::post('/{id}/support', [CommentInteractionController::class, 'support'])->middleware('auth:sanctum');
        Route::post('/{id}/Exhibitions', [CommentInteractionController::class, 'exhibitions'])->middleware('auth:sanctum');
        Route::post('/{id}/neutral', [CommentInteractionController::class, 'neutral'])->middleware('auth:sanctum');
        Route::post('/{id}/report', [CommentInteractionController::class, 'report']);
        Route::post('{id}/create', [CommentController::class, 'create'])->middleware('auth:sanctum');
        Route::delete('/{id}/delete', [CommentController::class, 'destroy'])->middleware('auth:sanctum');
        // 115
    });

    // Comments
    Route::prefix('comments')->middleware('throttle:90,1')->group(function () {
        Route::get('/reports/all', [ReportController::class, 'reports'])->middleware(AdminMiddleware::class);
        Route::delete('/reports/{id}/delete', [ReportController::class, 'delete'])->middleware(AdminMiddleware::class);
        // 117
    });

    // Likes
    Route::prefix('likes')->middleware('throttle:25,1')->group(function () {
        Route::get('/{id}', [LikesController::class, 'count']);
        Route::post('{id}/create', [LikesController::class, 'create'])->middleware('auth:sanctum');
        // 119
    });

    // Wishlist
    Route::prefix('Wishlist')->middleware('auth:sanctum')->group(function () {
        Route::get('/me', [WhisListController::class, 'me']);
        Route::post('/{id}', [WhisListController::class, 'add']);
        Route::delete('/{id}/delete', [WhisListController::class, 'delete']);
        // 122
    });

    // Notifications
    Route::prefix('notify')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::post('/create', [NotificationController::class, 'create']);
        // 123
    });

    // Replies
    Route::prefix('replies')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::post('/reply/{id}', [CommentReplyController::class, 'create']);
        // 124
    });

    // Plans
    Route::prefix('plans')->group(function () {
        Route::get('/all', [PlanController::class, 'all']);
        Route::get('/single/{slug}', [PlanController::class, 'single']);
        Route::get('/all/admin', [AdminPlanController::class, 'all'])->middleware(AdminMiddleware::class);
        Route::get('/single/admin/{id}', [AdminPlanController::class, 'single'])->middleware(AdminMiddleware::class);
        Route::post('/create', [AdminPlanController::class, 'create'])
            ->middleware(AdminMiddleware::class);
        Route::put('/update/{id}', [AdminPlanController::class, 'update'])
            ->middleware('auth:sanctum', AdminMiddleware::class);
        Route::delete('/delete/{id}', [AdminPlanController::class, 'delete'])
            ->middleware('auth:sanctum', AdminMiddleware::class);

        // Plan Subscriptions with wallet & paypal
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/subscribe/{plan}/wallet', [PlanSubscriptionController::class, 'subscribeWallet']);
            Route::post('/subscribe/{plan}/paypal', [PlanSubscriptionController::class, 'subscribePayPal']);
            Route::get('/{plan}/savings-estimate', [PlanSubscriptionController::class, 'savingsEstimate']);
        });
    });

    // Media Credits & Plan Redemptions
    Route::prefix('media-credits')->middleware('auth:sanctum')->group(function () {
        Route::get('/balance', [PlanSubscriptionController::class, 'userCredits']);
        Route::get('/history', [PlanSubscriptionController::class, 'creditHistory']);
        Route::get('/check/{media}', [PlanSubscriptionController::class, 'checkEligibility']);
        Route::post('/redeem/{media}', [PlanSubscriptionController::class, 'redeem']);
    });

    // Admin Media Tiers & Financial Reports
    Route::prefix('admin')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/media-tiers', [AdminPlanController::class, 'allTiers']);
        Route::post('/media-tiers', [AdminPlanController::class, 'saveTier']);
        Route::delete('/media-tiers/{id}', [AdminPlanController::class, 'deleteTier']);
        Route::match(['get', 'post'], '/plans/validate', [AdminPlanController::class, 'validatePricing']);
        Route::post('/plans/recommend', [AdminPlanController::class, 'recommend']);
        Route::get('/plan-financial-settings', [AdminPlanController::class, 'financialSettings']);
        Route::put('/plan-financial-settings', [AdminPlanController::class, 'saveFinancialSettings']);
        Route::get('/plan-financial-impact', [AdminPlanController::class, 'financialImpact']);
        Route::get('/plan-credit-reports', [AdminPlanController::class, 'creditReports']);
    });

    // Benefits
    Route::prefix('benefits')->middleware([AdminMiddleware::class])->group(
        function () {
            Route::post('/create/{id}', [BenefitsController::class, 'create']);
            Route::post('/update/{id}/plan', [BenefitsController::class, 'update']);
            Route::delete('/delete/{id}/plan', [BenefitsController::class, 'delete']);
            // 134
        });

    // Subscribe
    Route::prefix('subscribe')->middleware('auth:sanctum')->group(
        function () {
            Route::post('/{id}', [IncomeController::class, 'subscribe']);
            // 135
        });

    // Cart
    Route::prefix('cart')->middleware('auth:sanctum')->group(
        function () {
            Route::post('/addToCart/{id}', [CartController::class, 'addToCart']);
            Route::get('/get', [CartController::class, 'cart']);
            Route::delete('/delete/{id}', [CartController::class, 'deleteFromCart']);
            Route::delete('/clearCart', [CartController::class, 'clearCart']);
            // 139
        });

    // Gate
    Route::prefix('gate')->middleware('auth:sanctum')->group(function () {
        Route::get('/random', [GateController::class, 'random']);
        Route::get('/all', [GateController::class, 'countries']);
        Route::get('/{code}/stats', [GateController::class, 'country']);
        // 143
    });

    // Payment
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/pay', [PaymentController::class, 'pay'])->middleware('throttle:20,1');
        Route::post('/pay/wallet', [PaymentController::class, 'payWallet'])->middleware('throttle:60,1');
        // 145
    });

    Route::get('/paypal/success', [PaymentController::class, 'success'])->name('paypal.success');
    Route::get('/paypal/cancel', [PaymentController::class, 'cancel'])->name('paypal.cancel');
    Route::post('/paypal/webhook', [WebhookController::class, 'handle'])->name('paypal.webhook');
    Route::get('/order/status/{id}', [PaymentController::class, 'orderStatus'])
        ->middleware(['auth:sanctum', 'throttle:60,1']);
    // 149

    Route::prefix('deposit')->middleware('auth:sanctum')->group(function () {
        Route::post('/pay', [DepositController::class, 'create'])->middleware('throttle:10,1');
        // 150
    });

    Route::get('/wallet/success', [DepositController::class, 'success'])->name('wallet.success');
    Route::get('/wallet/cancel', [DepositController::class, 'cancel'])->name('wallet.cancel');
    Route::post('/wallet/webhook', [WalletWebhookController::class, 'handle'])->name('wallet.webhook');
    Route::get('/wallet/order-status/{id}', [DepositController::class, 'orderStatus'])
        ->middleware(['auth:sanctum', 'throttle:60,1']);
    // 154

    Route::prefix('creator')->middleware('auth:sanctum')->group(function () {
        Route::get('/all', [CreatorController::class, 'all']);
        Route::get('/show/{slug}', [CreatorController::class, 'show']);
        Route::get('/total', [CreatorController::class, 'total']);
    });

    Route::prefix('withdraw')->middleware('auth:sanctum')->group(function () {
        Route::get('/myWithdrawals', [UserWithdrawlController::class, 'myWithdrawals']);
        Route::post('/showWithdrawals', [UserWithdrawlController::class, 'showWithdrawals']);
        Route::post('/requestWithdrawals/{id}', [UserWithdrawlController::class, 'requestWithdrawals']);
        Route::post('/updateWithdrawals/{id}', [UserWithdrawlController::class, 'updateWithdrawals']);
        Route::delete('/deleteWithdrawals', [UserWithdrawlController::class, 'deleteWithdrawals']);
    });

    // Collections
    Route::prefix('collections')->group(function () {
        Route::get('/{event_id}', [CollectionController::class, 'getCollectionDetails']);
        Route::post('/{event_id}/add-to-cart', [CollectionController::class, 'addCollectionToCart'])->middleware('auth:sanctum');
    });

    Route::post('/tools/image-tags', [ImageTagsController::class, 'generate'])
        ->middleware(['auth:sanctum', 'throttle:30,1'])
        ->name('tools.image-tags');

    // 162 EndPoint till now
});
