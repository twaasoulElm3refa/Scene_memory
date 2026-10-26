<?php

namespace App\Http\Controllers\api\home;

use App\Exceptions\CommerceException;
use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\EventsImges;
use App\Models\LicenceType;
use App\Models\Payment;
use App\Models\Purchases;
use App\Models\Subscriptions;
use App\Models\User;
use App\Models\Wallet;
use App\Services\MinorMoney;
use App\Services\PayPalServices;
use App\Services\PlanFinancialEngine;
use App\Services\PlanQuotaService;
use App\Services\PlanSavingsService;
use App\Services\WalletLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PlanSubscriptionController extends Controller
{
    public function __construct(
        private readonly PlanQuotaService $quotaService,
        private readonly WalletLedgerService $ledger,
        private readonly MinorMoney $money,
        private readonly PlanSavingsService $savingsService,
        private readonly PlanFinancialEngine $financialEngine,
    ) {}

    /**
     * Subscribe to a plan using Scemory Wallet balance.
     */
    public function subscribeWallet(Request $request, LicenceType $plan): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Unauthorized or account disabled.'], 403);
        }

        if (! $plan->is_active) {
            return response()->json(['message' => 'This plan is not currently available for purchase.'], 422);
        }

        $financialAnalysis = $this->financialEngine->analyzePlan($plan);
        if (! $financialAnalysis['is_valid']) {
            return response()->json([
                'message' => 'This plan is temporarily unavailable pending financial validation.',
                'financial_status' => $financialAnalysis['financial_safety_status'],
            ], 422);
        }

        try {
            $this->quotaService->checkAccumulationLimits($user, $plan);

            $order = DB::transaction(function () use ($user, $plan) {
                $userLocked = User::query()->lockForUpdate()->findOrFail($user->id);
                $wallet = Wallet::query()->where('user_id', $userLocked->id)->lockForUpdate()->first();
                if (! $wallet) {
                    throw new CommerceException('Wallet not found.', 422);
                }

                $priceMinor = $this->money->fromDecimal((string) $plan->price);
                if ((int) $wallet->balance_minor < $priceMinor) {
                    throw new CommerceException('Insufficient wallet balance.', 422);
                }

                $idempotencyKey = 'plan-wallet:'.hash('sha256', $user->id.':'.$plan->id.':'.microtime());

                $order = Purchases::create([
                    'user_id' => $userLocked->id,
                    'payment_method' => 'wallet',
                    'order_type' => 'plan_subscription',
                    'status' => 'completed',
                    'currency' => $wallet->currency ?: 'USD',
                    'amount' => $plan->price,
                    'amount_minor' => $priceMinor,
                    'idempotency_key' => $idempotencyKey,
                    'description' => "Subscription to plan: {$plan->name}",
                    'paid_at' => now(),
                    'fulfilled_at' => now(),
                    'purchase_granted' => true,
                ]);

                $payment = Payment::create([
                    'order_id' => $order->id,
                    'user_id' => $userLocked->id,
                    'operation' => 'plan_subscription',
                    'method' => 'wallet',
                    'status' => 'completed',
                    'amount_minor' => $priceMinor,
                    'currency' => $wallet->currency ?: 'USD',
                    'idempotency_key' => $idempotencyKey,
                    'custom_id' => 'plan:'.$plan->id,
                    'paid_at' => now(),
                    'fulfilled_at' => now(),
                    'purchase_granted' => true,
                ]);

                $this->ledger->post(
                    $wallet,
                    $order,
                    $payment,
                    'debit',
                    'plan_subscription',
                    $priceMinor,
                    'wallet-debit-plan:'.$order->id,
                    'order:'.$order->id,
                    ['plan_id' => $plan->id, 'plan_name' => $plan->name]
                );

                $grant = $this->quotaService->grantCredits($userLocked, $plan, $order, 'wallet');

                $userLocked->update(['licence_type_id' => $plan->id]);

                Subscriptions::updateOrCreate(
                    ['user_id' => $userLocked->id, 'licence_id' => $plan->id],
                    ['status' => 'active', 'start_at' => now()->toDateString(), 'end_at' => now()->addMonth()->toDateString()]
                );

                return $order;
            }, 5);

            $credits = $this->quotaService->getUserCredits($user);

            return response()->json([
                'status' => 'success',
                'message' => 'Subscribed successfully using wallet.',
                'order_id' => $order->id,
                'total_photos' => $credits['total_photos'],
                'total_videos' => $credits['total_videos'],
                'by_tier' => $credits['by_tier'],
            ]);
        } catch (CommerceException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        } catch (Throwable $e) {
            Log::error('plan_wallet_subscription_failed', ['user_id' => $user->id, 'plan_id' => $plan->id, 'exception' => $e::class, 'message' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to process wallet plan purchase.'], 500);
        }
    }

    /**
     * Subscribe to a plan using PayPal.
     */
    public function subscribePayPal(Request $request, LicenceType $plan): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Unauthorized or account disabled.', 'code' => 'unauthorized'], 403);
        }

        if (! $plan->is_active) {
            return response()->json(['message' => 'This plan is not currently available for purchase.', 'code' => 'plan_inactive'], 422);
        }

        // The same persisted policy is checked again before capture and at grant time.
        $financialAnalysis = $this->financialEngine->analyzePlan($plan);
        if (! $financialAnalysis['is_valid']) {
            Log::warning('plan_checkout_blocked', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'reason_code' => 'plan_financially_unavailable',
                'financial_status' => $financialAnalysis['financial_safety_status'],
                'settings_version' => $financialAnalysis['settings_version'],
            ]);

            return response()->json([
                'message' => 'This plan is temporarily unavailable pending financial validation.',
                'code' => 'plan_financially_unavailable',
                'financial_status' => $financialAnalysis['financial_safety_status'],
            ], 422);
        }

        // Without the merchant ID no PayPal payment can be verified, so do not let the buyer approve one.
        if (trim((string) config('paypal.merchant_id')) === '') {
            Log::error('plan_paypal_configuration_missing', ['plan_id' => $plan->id, 'setting' => 'paypal.merchant_id']);

            return response()->json(['message' => 'PayPal checkout is temporarily unavailable.', 'code' => 'paypal_unavailable'], 503);
        }

        try {
            $this->quotaService->checkAccumulationLimits($user, $plan);
        } catch (CommerceException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'accumulation_limit'], $e->status);
        }

        try {
            $checkout = app(PayPalServices::class)->createPlanOrder($user, $plan);
            if ($checkout['state'] === 'in_progress') {
                return response()->json([
                    'message' => 'This PayPal checkout was already approved and is being confirmed.',
                    'code' => 'checkout_in_progress',
                    'order_id' => $checkout['order']->id,
                ], 409);
            }

            return response()->json([
                'order_id' => $checkout['order']->id,
                'approval_url' => $checkout['approval_url'],
                'status' => $checkout['state'],
            ]);
        } catch (Throwable $e) {
            Log::error('plan_paypal_order_failed', ['user_id' => $user->id, 'plan_id' => $plan->id, 'exception' => $e::class, 'message' => $e->getMessage()]);

            return response()->json(['message' => 'Unable to create PayPal order for this plan.', 'code' => 'paypal_order_failed'], 502);
        }
    }

    /**
     * Redeem a photo or video using user's plan credits ("Get with Plan").
     */
    public function redeem(Request $request, EventsImges $media): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Unauthorized or account disabled.'], 403);
        }

        try {
            $result = $this->quotaService->redeemMedia($user, $media);

            if (($result['status'] ?? null) === 'success' && isset($result['grant_id'])) {
                $comparisons = collect($this->savingsService->forUser($user)['plans']);
                $result['plan_comparison'] = $comparisons->firstWhere('id', (int) $result['grant_id']);
            }

            return response()->json($result);
        } catch (CommerceException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        } catch (Throwable $e) {
            Log::error('plan_redemption_failed', ['user_id' => $user->id, 'media_id' => $media->id, 'exception' => $e::class, 'message' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to redeem media with plan credits.'], 500);
        }
    }

    /**
     * Check if the authenticated user can redeem the given media with their plan credits.
     */
    public function checkEligibility(Request $request, EventsImges $media): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'eligible' => false,
                'authenticated' => false,
                'reason' => 'Sign in to redeem with your plan credits.',
            ]);
        }

        $eligibility = $this->quotaService->checkRedemptionEligibility($user, $media);

        return response()->json($eligibility);
    }

    /**
     * Get authenticated user's credit balances (Navbar counters and dashboard overview).
     */
    public function userCredits(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $credits = $this->quotaService->getUserCredits($user);
        $savings = $this->savingsService->forUser($user);
        $activeGrants = collect($savings['plans'])
            ->filter(fn (array $grant) => $grant['remaining_photos'] > 0 || $grant['remaining_videos'] > 0)
            ->values();
        $highestTier = collect($credits['by_tier'])->sortByDesc('tier_rank')->first()['tier_name'] ?? null;
        $data = [
            'total_photos' => $credits['total_photos'],
            'total_videos' => $credits['total_videos'],
            'by_tier' => $credits['by_tier'],
            'highest_tier' => $highestTier,
            'grants_count' => $credits['grants_count'],
            'grants' => $activeGrants->all(),
            'plan_comparisons' => $savings['plans'],
            'comparisons_complete' => $savings['comparisons_complete'],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data,
            ...$data,
        ]);
    }

    /**
     * Estimate this plan against the authenticated user's current cart.
     */
    public function savingsEstimate(Request $request, LicenceType $plan): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->savingsService->estimateForCart($user, $plan),
        ]);
    }

    /**
     * Get authenticated user's credit transactions history.
     */
    public function creditHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $transactions = CreditTransaction::query()
            ->where('user_id', $user->id)
            ->with(['media:id,preview_url,full_url,type,price', 'grant:id,tier_name,tier_rank'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $transactions,
        ]);
    }
}
