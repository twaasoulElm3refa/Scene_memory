<?php

use App\Http\Controllers\api\admin\AdminPlanController;
use App\Http\Controllers\api\admin\AdminDashboardController;
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
use App\Http\Controllers\api\admin\SubCategoriesCreateController;
use App\Http\Controllers\api\admin\UserController;
use App\Http\Controllers\api\admin\UserCountsController;
use App\Http\Controllers\api\admin\WithdrawlController;
use App\Http\Controllers\api\auth\AuthController;
use App\Http\Controllers\api\auth\GoogleAuthController;
use App\Http\Controllers\api\auth\DownloadController;
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
use App\Http\Controllers\api\webhook\WebhookController;
use App\Http\Controllers\api\webhook\WalletWebhookController;
use App\Http\Controllers\api\home\IncomeController;
use App\Http\Controllers\api\home\LikesController;
use App\Http\Controllers\api\home\PlanController;
use App\Http\Controllers\api\home\SubCategoryController;
use App\Http\Controllers\api\home\WhisListController;
use App\Http\Controllers\api\owner\CreatorController;
use App\Http\Controllers\api\owner\UserWithdrawlController;
use App\Http\Controllers\api\payment\DepositController;
use App\Http\Controllers\api\payment\PurchaseController;
use App\Http\Controllers\api\payment\PaymentController;
use App\Http\Controllers\api\userDshboard\MediaRequestController;
use App\Http\Controllers\api\userDshboard\UserDashboardController;
use App\Http\Controllers\home\HomeController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\OwnEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // AUTH
    Route::prefix('users')->group(function () {
        // Auth Routes
        Route::post('/register', [AuthController::class, 'register'])->middleware(['throttle:4,1']);
        Route::post('/login', [AuthController::class, 'login'])->middleware(['throttle:6,1']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware(['throttle:5,1', 'guest']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware(['throttle:5,1', 'guest']);

        // Social Auth
        Route::get('/google-login', [GoogleAuthController::class, 'googleLogin'])->middleware('guest');
        Route::get('/google-callback', [GoogleAuthController::class, 'googleCallback'])->middleware('guest');
        // profile Routes
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('/profile', [AuthController::class, 'profile']);
            Route::get('/wallet', [AuthController::class, 'wallet']);
            Route::get('/downloads', [DownloadController::class, 'downloads']);
            Route::post('/update-profile', [AuthController::class, 'updateProfile']);
            Route::put('/password', [AuthController::class, 'updatePassword']);
        });
    });

    Route::prefix('admin')->group(function () {
        Route::post('/login',[AdminAuthController::class,'login']);
    });

    Route::prefix('admin')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);
    });

    // downloads Media
    Route::get('/download', function (Request $request) {
        $path = $request->query('path');
        $fullPath = storage_path('app/public/' . $path);
        return response()->download($fullPath);
    })->middleware('auth:sanctum');

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
    Route::prefix('sub_categories')->middleware(['throttle:15,1'])->group(function () {
        Route::get('/', [SubCategoryController::class,  'index']);
        Route::get('/all/paginated', [SubCategoryController::class,  'paginated']);
        Route::get('/{id}', [SubCategoryController::class,  'single']);
        Route::post('/create', [SubCategoriesCreateController::class,  'create'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/update/{id}', [SubCategoryController::class,  'update'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::delete('/delete/{id}', [SubCategoryController::class,  'delete'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        //25
    });

    // COUNTRIES CRUD
    Route::prefix('countries')->group(function () {
        Route::get('/', [CountriesController::class,  'index']);
        Route::get('/all/get', [CountriesController::class,  'all']);
        Route::get('/paginated/get', [CountriesController::class,  'paginated'])->middleware('throttle:25,1');
        Route::get('/all/count', [CountriesController::class,  'count'])->middleware('throttle:25,1');
        Route::get('/{id}/cities', [CountriesController::class,  'cities'])->middleware('throttle:25,1');
        Route::get('/{id}', [CountriesController::class,  'single'])->middleware('throttle:25,1');
        Route::post('/create', [CountriesCreateController::class,  'create'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/{id}/update', [CountriesController::class,  'update'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::delete('/{id}/delete', [CountriesController::class,  'delete'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        //34
    });

    // CITIES CRUD
    Route::prefix('cities')->group(function () {
        Route::get('/', [CitiesController::class,  'index'])->middleware('throttle:25,1');
        Route::get('/paginated/get', [CitiesController::class,  'paginated'])->middleware('throttle:25,1');
        Route::get('/{id}', [CitiesController::class,  'single'])->middleware('throttle:25,1');
        Route::post('/create', [CitiesCreateController::class,  'create'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/{id}/update', [CitiesController::class, 'update'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::delete('/{id}/delete', [CitiesController::class, 'delete'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        //40
    });

    // Events
    Route::prefix('events')->middleware(['throttle:60,1'])->group(function () {
        // Home Events
        Route::get('/', [EventController::class, 'all']);
        Route::get('/daily', [EventController::class, 'daily']);
        Route::get('/historical', [EventController::class, 'historical']);
        Route::get('/count', [EventController::class, 'count']);
        Route::get('/memories', [EventController::class, 'memories']);
        Route::get('/{city_id?}/{sub_category_id?}', [EventController::class,  'index']);
        Route::get('/{city}/marker/search', [EventController::class,  'MarkerSearch']);
        Route::get('/{slug}/single/get', [EventController::class,  'single']);
        //48

        // Events CRUD
        Route::post('/create', [EventAdminCreateController::class,  'create'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/historic', [EventAdminCreateController::class,  'historic'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::post('/create/user', [EventUserCreateController::class,  'create'])->middleware('auth:sanctum');
        Route::post('/historic/user', [EventUserCreateController::class,  'historic'])->middleware('auth:sanctum');
        Route::post('/{id}/update', [EventAdminController::class,  'update'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        Route::delete('/{id}/delete', [EventAdminController::class,  'destroy'])->middleware(AdminMiddleware::class, 'auth:sanctum');
        //54
    });

    // Purchases CRUD
    Route::prefix('purchases')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/', [PurchasesController::class, 'index']);
        Route::get('/all/count', [PurchasesController::class,  'count'])->middleware('throttle:25,1');
        Route::get('/type/{type}', [PurchasesController::class, 'filter']);
        Route::get('/status/{status}', [PurchasesController::class, 'status']);
        Route::get('/show/{id}', [PurchasesController::class, 'show']);
        Route::post('/update/{id}', [PurchasesController::class, 'update']);
        Route::delete('/delete/{id}', [PurchasesController::class, 'destroy']);
        //61
    });

     // withdrawals
    Route::prefix('withdraw')->middleware(['auth:sanctum', AdminMiddleware::class,'throttle:90,1'])->group(function () {
        Route::get('/', [WithdrawlController::class, 'index']);
        Route::get('/all/count', [WithdrawlController::class,  'count']);
        Route::get('/status/{status}', [WithdrawlController::class, 'status']);
        Route::get('/show/{id}', [WithdrawlController::class, 'show']);
        Route::post('/update/{id}', [WithdrawlController::class, 'update']);
        Route::post('/approve/{id}', [WithdrawlController::class, 'approve']);
        Route::post('/reject/{id}', [WithdrawlController::class, 'reject']);
        Route::delete('/delete/{id}', [WithdrawlController::class, 'destroy']);
        //69
    });

    Route::prefix('users')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        // USERS CRUD
        Route::get('/', [UserController::class, 'index']);
        Route::get('/all/get', [UserController::class, 'all']);
        Route::get('/latest/get', [UserController::class, 'latest']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/create', [UserController::class, 'create']);
        Route::post('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        //76

        // USER COUNTS
        Route::get('/all/count', [UserCountsController::class, 'count']);
        Route::get('/all/last-login', [UserCountsController::class, 'last_login']);
        Route::get('/all/new-users', [UserCountsController::class, 'NewUsers']);
        //79
    });

    Route::prefix('')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        // CONTACTS
        Route::get('/contacts', [ContactController::class, 'all']);
        Route::get('/contacts/{id}', [ContactController::class, 'single']);
        Route::post('/contacts/create', [ContactController::class, 'create']);
        Route::post('/contacts/respond/{id}', [ContactController::class, 'respond']);
        Route::delete('/contacts/delete/{id}', [ContactController::class, 'delete']);
        //84

        // Newsletters
        Route::get('/newsletters', [NewsletterController::class, 'all']);
        Route::post('/newsletters/create', [NewsletterController::class, 'create']);
        Route::post('/newsletters/respond/{id}', [NewsletterController::class, 'respond']);
        //87

        // Footer
        Route::get('/footer', [FooterController::class, 'all']);
        Route::post('/footer/update', [FooterController::class, 'update']);
        //89
    });

    // Event Media
    Route::prefix('event-images')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/{id}', [EventImageController::class, 'allPerEvent']);
        Route::post('/create/{id}', [EventImageController::class, 'create']);
        Route::delete('/{id}/delete', [EventImageController::class, 'delete']);
        //92
    });

    // User Dashboard
    Route::prefix('user-dshboard')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/my-events', [UserDashboardController::class, 'myEvents']);
        Route::post('/{slug}', [UserDashboardController::class, 'addMedia']);
        Route::delete('/{id}/delete', [EventImageController::class, 'delete']);
        //95

        // Create Event
        Route::post('/create/Event', [UserDashboardController::class,  'create'])->middleware('auth:sanctum');
        Route::post('/{slug}/update/Event', [UserDashboardController::class,  'update'])->middleware(OwnEvent::class, 'auth:sanctum');
        Route::delete('/{id}/destroy', [UserDashboardController::class, 'delete'])->middleware(OwnEvent::class);
        //98
    });

    // Upload Media
    Route::prefix('media-request')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/{id}', [MediaRequestController::class, 'all']);
        // Route::post('/approve/{id}', [MediaRequestController::class, 'approve'])->middleware(AdminMiddleware::class);
        // Route::post('/reject/{id}', [MediaRequestController::class, 'reject'])->middleware(AdminMiddleware::class);
        Route::post('/upload/{id}', [MediaRequestController::class, 'upload']);
        //100
    });

    // Event Creation Requests
    Route::prefix('requests')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/all/paginated', [RequestController::class, 'allPaginated']);
        Route::get('/{id}', [RequestController::class, 'show']);
        Route::post('/approve/{request_id}', [RequestController::class, 'approve']);
        Route::post('/decline/{request_id}', [RequestController::class, 'decline']);
        Route::delete('/{id}', [RequestController::class, 'destroy']);
        //105
    });

    Route::prefix('create')->middleware(['auth:sanctum'])->group(function () {
        Route::post('/', [HomeController::class, 'create']);
        Route::post('/{id}', [HomeController::class, 'update']);
        Route::delete('/{id}', [HomeController::class, 'destroy']);
        //108
    });

    // Comments
    Route::prefix('comments')->middleware('throttle:50,1')->group(function () {
        Route::get('/{slug}', [CommentController::class, 'allPaginated']);
        Route::post('/{id}/support', [CommentInteractionController::class, 'support']);
        Route::post('/{id}/Exhibitions', [CommentInteractionController::class, 'exhibitions']);
        Route::post('/{id}/neutral', [CommentInteractionController::class, 'neutral']);
        Route::post('/{id}/report', [CommentInteractionController::class, 'report']);
        Route::post('{id}/create', [CommentController::class, 'create']);
        Route::delete('/{id}/delete', [CommentController::class, 'destroy']);
        //115
    });

    // Comments
    Route::prefix('comments')->middleware('throttle:50,1')->group(function () {
        Route::get('/reports/all', [ReportController::class, 'reports'])->middleware(AdminMiddleware::class);
        Route::delete('/reports/{id}/delete', [ReportController::class, 'delete'])->middleware(AdminMiddleware::class);
        //117
    });

    // Likes
    Route::prefix('likes')->middleware('throttle:10,1')->group(function () {
        Route::get('/{id}', [LikesController::class, 'count']);
        Route::post('{id}/create', [LikesController::class, 'create']);
        //119
    });

    // Wishlist
    Route::prefix('Wishlist')->middleware('auth:sanctum')->group(function () {
        Route::get('/me', [WhisListController::class,'me']);
        Route::post('/{id}', [WhisListController::class,'add']);
        Route::delete('/{id}/delete', [WhisListController::class,'delete']);
        //122
    });

    // Notifications
    Route::prefix('notify')->middleware(['auth:sanctum',AdminMiddleware::class])->group(function () {
        Route::post('/create', [NotificationController::class, 'create']);
        //123
    });

    // Replies
    Route::prefix('replies')->middleware(['auth:sanctum',AdminMiddleware::class])->group(function () {
        Route::post('/reply/{id}', [CommentReplyController::class,'create']);
        //124
    });

    // Plans
    Route::prefix('plans')->group(function () {
        Route::get('/all', [PlanController::class,'all']);
        Route::get('/single/{slug}', [PlanController::class,'single']);
        Route::get('/all/admin', [AdminPlanController::class,'all'])->middleware(AdminMiddleware::class);
        Route::get('/single/admin/{id}', [AdminPlanController::class,'single'])->middleware(AdminMiddleware::class);
        Route::post('/create', [AdminPlanController::class,'create'])
        ->middleware( AdminMiddleware::class);
        Route::put('/update/{id}', [AdminPlanController::class,'update'])
        ->middleware('auth:sanctum', AdminMiddleware::class);
        Route::delete('/delete/{id}', [AdminPlanController::class,'delete'])
        ->middleware('auth:sanctum', AdminMiddleware::class);
        //131
    });

    // Benefits
    Route::prefix('benefits')->middleware([AdminMiddleware::class])->group(
        function () {
        Route::post('/create/{id}', [BenefitsController::class,'create']);
        Route::post('/update/{id}/plan', [BenefitsController::class,'update']);
        Route::delete('/delete/{id}/plan', [BenefitsController::class,'delete']);
        //134
    });

    // Subscribe
    Route::prefix('subscribe')->middleware('auth:sanctum')->group(
        function () {
        Route::post('/{id}', [IncomeController::class,'subscribe']);
        //135
    });

    // Cart
    Route::prefix('cart')->middleware('auth:sanctum')->group(
        function () {
        Route::post('/addToCart/{id}', [CartController::class,'addToCart']);
        Route::get('/get', [CartController::class,'cart']);
        Route::delete('/delete/{id}', [CartController::class,'deleteFromCart']);
        Route::delete('/clearCart', [CartController::class,'clearCart']);
        //139
    });

    // Purchase
    Route::prefix('purchase')->middleware('auth:sanctum')->group(
        function () {
        Route::post('/', [PurchaseController::class,'purchase']);
        //140
    });

    // Gate
    Route::prefix('gate')->middleware('auth:sanctum')->group(function () {
       Route::get('/random', [GateController::class, 'random']);
       Route::get('/all', [GateController::class, 'countries']);
       Route::get('/{code}/stats', [GateController::class, 'country']);
       //143
    });

    // Payment
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/pay', [PaymentController::class, 'pay']);
        Route::post('/pay/wallet', [PaymentController::class, 'payWallet']);
        //145
    });

    Route::get('/paypal/success', [PaymentController::class, 'success'])->name('paypal.success');
    Route::get('/paypal/cancel',  [PaymentController::class, 'cancel'])->name('paypal.cancel');
    Route::post('/paypal/webhook', [WebhookController::class, 'handle'])->name('paypal.webhook');
    Route::get('/order/status/{id}', [PaymentController::class, 'orderStatus']);
    //149

    Route::prefix('deposit')->middleware('auth:sanctum')->group(function () {
        Route::post('/pay', [DepositController::class, 'create']);
        //150
    });

    Route::get('/wallet/success', [DepositController::class, 'success'])->name('wallet.success');
    Route::get('/wallet/cancel',  [DepositController::class, 'cancel'])->name('wallet.cancel');
    Route::post('/wallet/webhook', [WalletWebhookController::class, 'handle'])->name('wallet.webhook');
    Route::get('/wallet/order-status/{id}', [DepositController::class, 'orderStatus']);
    //154

    Route::prefix('creator')->middleware('auth:sanctum')->group(function () {
        Route::get('/all', [CreatorController::class, 'all']);
        Route::get('/show/{slug}', [CreatorController::class, 'show']);
        Route::get('/total',[CreatorController::class,'total']);
    });

    Route::prefix('withdraw')->middleware('auth:sanctum')->group(function () {
        Route::get('/myWithdrawals', [UserWithdrawlController::class, 'myWithdrawals']);
        Route::post('/showWithdrawals', [UserWithdrawlController::class, 'showWithdrawals']);
        Route::post('/requestWithdrawals/{id}', [UserWithdrawlController::class, 'requestWithdrawals']);
        Route::post('/updateWithdrawals/{id}', [UserWithdrawlController::class, 'updateWithdrawals']);
        Route::delete('/deleteWithdrawals', [UserWithdrawlController::class, 'deleteWithdrawals']);
    });
    // 162 EndPoint till now
});
