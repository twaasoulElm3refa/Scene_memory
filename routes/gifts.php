<?php

use App\Http\Controllers\api\admin\VirtualGiftAdminController as AdminGifts;
use App\Http\Controllers\api\gifts\GiftController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:90,1')->group(function () {
    Route::get('/virtual-gifts', [GiftController::class, 'catalog']);
    Route::get('/events/{event}/gifts/summary', [GiftController::class, 'eventSummary']);
    Route::get('/comments/{comment}/gifts/summary', [GiftController::class, 'commentSummary']);
    Route::get('/gifts/paypal/return', [GiftController::class, 'paypalReturn'])->name('gifts.paypal.return');
});
Route::prefix('gifts')->middleware(['auth:sanctum', 'throttle:45,1'])->group(function () {
    Route::post('/send/wallet', [GiftController::class, 'wallet']);
    Route::post('/paypal/create-order', [GiftController::class, 'createOrder']);
    Route::post('/paypal/capture', [GiftController::class, 'capture']);
    Route::get('/sent', [GiftController::class, 'history'])->name('gifts.sent');
    Route::get('/received', [GiftController::class, 'history'])->name('gifts.received');
    Route::get('/earnings', [GiftController::class, 'earnings']);
    Route::get('/withdrawals', [GiftController::class, 'withdrawals']);
    Route::post('/withdrawals', [GiftController::class, 'withdraw']);
    Route::post('/withdrawals/{reference}/cancel', [GiftController::class, 'cancelWithdrawal']);
    Route::get('/{reference}/status', [GiftController::class, 'status']);
    Route::post('/{reference}/cancel', [GiftController::class, 'cancel']);
});
Route::prefix('v1/admin/gifts')->middleware(['auth:sanctum', AdminMiddleware::class, 'throttle:60,1'])->group(function () {
    Route::get('/catalog', [AdminGifts::class, 'index']);
    Route::post('/catalog', [AdminGifts::class, 'save']);
    Route::post('/catalog/{id}', [AdminGifts::class, 'save']);
    Route::delete('/catalog/{id}', [AdminGifts::class, 'deactivate']);
    Route::post('/catalog/{id}/activate', [AdminGifts::class, 'activate']);
    Route::get('/transactions', [AdminGifts::class, 'transactions']);
    Route::get('/transactions/{reference}/audit', [AdminGifts::class, 'audit']);
    Route::post('/transactions/{reference}/actions', [AdminGifts::class, 'action']);
    Route::get('/report', [AdminGifts::class, 'report']);
    Route::get('/withdrawals', [AdminGifts::class, 'withdrawals']);
    Route::get('/withdrawals/{reference}', [AdminGifts::class, 'withdrawalDetail']);
    Route::post('/withdrawals/{reference}', [AdminGifts::class, 'withdrawal']);
});
