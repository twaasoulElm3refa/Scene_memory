<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanRequest;
use App\Jobs\TranslatePlanJob;
use App\Models\CreditTransaction;
use App\Models\LicenceType;
use App\Models\MediaTier;
use App\Models\PlanFinancialSetting;
use App\Models\UserCreditGrant;
use App\Repositories\Contracts\Plans\PlanRepositoryInterface;
use App\Services\MediaTierService;
use App\Services\MinorMoney;
use App\Services\PlanFinancialEngine;
use App\Services\PlanRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminPlanController extends Controller
{
    use ApiResponse;

    private $cacheTime = 60 * 24 * 7;

    public function __construct(
        private readonly PlanRepositoryInterface $planRepository,
        private readonly PlanFinancialEngine $financialEngine,
        private readonly PlanRecommendationService $recommendations,
        private readonly MinorMoney $money,
    ) {}

    private function clearCache()
    {
        Cache::tags(['plans'])->flush();
    }

    public function all()
    {
        $cacheKey = 'plans_admin_'.$this->cacheTime.''.app()->getLocale();
        $plans = Cache::tags(['plans'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->planRepository->allForAdmin();
        });

        return $this->success($plans, 'All plans');
    }

    public function create(PlanRequest $request)
    {
        $data = $request->validated();
        try {
            $tierLimits = $this->requestedTierLimits($data);
            $val = $this->financialEngine->analyze(
                $this->money->fromDecimal((string) $data['price']),
                (int) ($data['photo_quota'] ?? 0),
                (int) ($data['video_quota'] ?? 0),
                $tierLimits,
            );
            if (! $val['is_valid']) {
                return response()->json([
                    'status' => 'error',
                    'message' => implode(' ', $val['errors']),
                    'validation' => $val,
                ], 422);
            }
            $data['media_tier_id'] = collect($val['tier_limits'])->sortByDesc('tier_rank')->first()['media_tier_id'] ?? null;

            $plan = DB::transaction(function () use ($data, $tierLimits, $val) {
                unset($data['tier_limits']);
                $data['slug'] = Str::slug($data['name']).'-'.Str::random(5).'-'.time();
                $data['financial_status'] = $val['financial_safety_status'];
                $data['financial_settings_version'] = $val['settings_version'];
                $data['financial_analysis'] = $val;
                $data['financially_validated_at'] = now();
                $plan = LicenceType::create($data);
                $this->syncTierLimits($plan, $tierLimits);

                return $plan->load('tierLimits.mediaTier', 'mediaTier');
            });
            TranslatePlanJob::dispatch($plan->id, $data['name']);
            $this->clearCache();

            return $this->success($plan, 'plan Created Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function update(PlanRequest $request)
    {
        $data = $request->validated();
        try {
            $id = (int) $request->input('id', $request->route('id'));
            $plan = $this->planRepository->findOrFail($id);

            $mediaTierId = $data['media_tier_id'] ?? $plan->media_tier_id;
            $photoQuota = isset($data['photo_quota']) ? (int) $data['photo_quota'] : (int) $plan->photo_quota;
            $videoQuota = isset($data['video_quota']) ? (int) $data['video_quota'] : (int) $plan->video_quota;
            $price = isset($data['price']) ? (float) $data['price'] : (float) $plan->price;

            $tierLimits = array_key_exists('tier_limits', $data)
                ? $data['tier_limits']
                : ($plan->tierLimits()->exists()
                    ? $this->financialEngine->limitsForPlan($plan)
                    : $this->financialEngine->legacyTierLimits($mediaTierId, $photoQuota, $videoQuota));
            $val = $this->financialEngine->analyze(
                $this->money->fromDecimal((string) $price),
                $photoQuota,
                $videoQuota,
                $tierLimits,
            );
            if (! $val['is_valid']) {
                return response()->json([
                    'status' => 'error',
                    'message' => implode(' ', $val['errors']),
                    'validation' => $val,
                ], 422);
            }
            $data['media_tier_id'] = collect($val['tier_limits'])->sortByDesc('tier_rank')->first()['media_tier_id'] ?? null;

            DB::transaction(function () use ($plan, $data, $tierLimits, $val) {
                unset($data['tier_limits']);
                $data['slug'] = Str::slug($data['name']).'-'.Str::random(5).'-'.time();
                $data['financial_status'] = $val['financial_safety_status'];
                $data['financial_settings_version'] = $val['settings_version'];
                $data['financial_analysis'] = $val;
                $data['financially_validated_at'] = now();
                $plan->update($data);
                $this->syncTierLimits($plan, $tierLimits);
            });
            TranslatePlanJob::dispatch($plan->id, $plan->name);
            $this->clearCache();

            return $this->success($plan, 'plan Updated Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $plan = $this->planRepository->findOrFail((int) $id);
            $plan->delete();

            $this->clearCache();

            return $this->success($plan, 'plan Deleted Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function single()
    {
        try {
            $id = (int) (request('id') ?: request()->route('id'));
            $cacheKey = 'plans_single_admin_'.$id.'_'.app()->getLocale();

            $plans = Cache::tags(['plans'])->remember($cacheKey, $this->cacheTime, function () use ($id) {
                return LicenceType::with(['advantges', 'mediaTier', 'tierLimits.mediaTier'])->find($id);
            });

            return $this->success($plans, 'Single plan');

        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function validatePricing(Request $request)
    {
        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'photo_quota' => ['nullable', 'integer', 'min:0'],
            'video_quota' => ['nullable', 'integer', 'min:0'],
            'media_tier_id' => ['nullable', 'integer', 'exists:media_tiers,id'],
            'tier_limits' => ['nullable', 'array'],
            'tier_limits.*.media_tier_id' => ['required_with:tier_limits', 'integer', 'distinct', 'exists:media_tiers,id'],
            'tier_limits.*.photo_maximum' => ['required_with:tier_limits', 'integer', 'min:0'],
            'tier_limits.*.video_maximum' => ['required_with:tier_limits', 'integer', 'min:0'],
        ]);

        $price = (float) $validated['price'];
        $tierId = $validated['media_tier_id'] ?? null;
        $photoQuota = (int) ($validated['photo_quota'] ?? 0);
        $videoQuota = (int) ($validated['video_quota'] ?? 0);

        $limits = $validated['tier_limits']
            ?? $this->financialEngine->legacyTierLimits($tierId, $photoQuota, $videoQuota);
        $result = $this->financialEngine->analyze(
            $this->money->fromDecimal((string) $price),
            $photoQuota,
            $videoQuota,
            $limits,
        );

        return response()->json($result);
    }

    public function recommend(Request $request)
    {
        $validated = $request->validate(['price' => ['required', 'numeric', 'gt:0']]);

        return response()->json($this->recommendations->recommend($validated['price']));
    }

    public function financialSettings()
    {
        $status = $this->financialEngine->configurationStatus();

        return response()->json([
            'is_complete' => $status['is_complete'],
            'missing' => $status['missing'],
            'settings' => $status['settings'],
            'tiers' => MediaTier::query()->where('is_active', true)->where('is_plan_eligible', true)->orderBy('rank')->get(),
        ]);
    }

    public function saveFinancialSettings(Request $request)
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'size:3'],
            'minimum_profit' => ['required', 'numeric', 'min:0'],
            'minimum_margin_bps' => ['required', 'integer', 'between:0,10000'],
            'operating_cost_bps' => ['required', 'integer', 'between:0,10000'],
            'fixed_operating_cost' => ['required', 'numeric', 'min:0'],
            'risk_reserve_bps' => ['required', 'integer', 'between:0,10000'],
            'tax_bps' => ['required', 'integer', 'between:0,10000'],
            'photo_allocation_bps' => ['required', 'integer', 'between:0,10000'],
            'tier_distribution' => ['required', 'array'],
            'tier_distribution.*' => ['required', 'integer', 'between:0,10000'],
            'payment_fees' => ['required', 'array'],
            'payment_fees.*.payment_method' => ['required', Rule::in(PlanFinancialEngine::REQUIRED_PAYMENT_METHODS), 'distinct'],
            'payment_fees.*.percentage_bps' => ['required', 'integer', 'between:0,10000'],
            'payment_fees.*.fixed_fee' => ['required', 'numeric', 'min:0'],
            'payment_fees.*.is_enabled' => ['required', 'boolean'],
        ]);

        $eligibleIds = MediaTier::query()->where('is_active', true)->where('is_plan_eligible', true)
            ->pluck('id')->map(fn ($id) => (string) $id)->sort()->values();
        $providedIds = collect($validated['tier_distribution'])->keys()->map(fn ($id) => (string) $id)->sort()->values();
        if ($providedIds->all() !== $eligibleIds->all() || collect($validated['tier_distribution'])->sum() !== 10000) {
            throw ValidationException::withMessages([
                'tier_distribution' => ['Provide every eligible tier exactly once and make the weights total 10,000 basis points.'],
            ]);
        }
        $providedMethods = collect($validated['payment_fees'])->pluck('payment_method')->sort()->values()->all();
        $requiredMethods = collect(PlanFinancialEngine::REQUIRED_PAYMENT_METHODS)->sort()->values()->all();
        if ($providedMethods !== $requiredMethods) {
            throw ValidationException::withMessages([
                'payment_fees' => ['Wallet and PayPal fee rules must both be supplied explicitly.'],
            ]);
        }
        if ((float) $validated['minimum_profit'] <= 0 && (int) $validated['minimum_margin_bps'] <= 0) {
            throw ValidationException::withMessages([
                'minimum_profit' => ['Set a positive minimum dollar profit, a positive minimum margin, or both.'],
            ]);
        }

        $settings = DB::transaction(function () use ($validated, $request) {
            PlanFinancialSetting::query()->where('is_active', true)->update(['is_active' => false]);
            $settings = PlanFinancialSetting::create([
                'version' => ((int) PlanFinancialSetting::max('version')) + 1,
                'currency' => strtoupper($validated['currency']),
                'minimum_profit_minor' => $this->money->fromDecimal((string) $validated['minimum_profit']),
                'minimum_margin_bps' => $validated['minimum_margin_bps'],
                'operating_cost_bps' => $validated['operating_cost_bps'],
                'fixed_operating_cost_minor' => $this->money->fromDecimal((string) $validated['fixed_operating_cost']),
                'risk_reserve_bps' => $validated['risk_reserve_bps'],
                'tax_bps' => $validated['tax_bps'],
                'photo_allocation_bps' => $validated['photo_allocation_bps'],
                'tier_distribution' => $validated['tier_distribution'],
                'is_active' => true,
                'configured_by' => $request->user()?->id,
            ]);
            foreach ($validated['payment_fees'] as $fee) {
                $settings->paymentFees()->create([
                    'payment_method' => $fee['payment_method'],
                    'percentage_bps' => $fee['percentage_bps'],
                    'fixed_fee_minor' => $this->money->fromDecimal((string) $fee['fixed_fee']),
                    'is_enabled' => $fee['is_enabled'],
                ]);
            }

            return $settings->load('paymentFees');
        });

        $impact = $this->revalidatePlans();
        $this->clearCache();

        return response()->json(['settings' => $settings, 'impact' => $impact], 201);
    }

    public function financialImpact()
    {
        return response()->json($this->revalidatePlans(false));
    }

    public function allTiers()
    {
        $tiers = MediaTier::orderBy('rank', 'asc')->get();

        return $this->success($tiers, 'Media tiers retrieved successfully');
    }

    public function saveTier(Request $request)
    {
        $tierId = $request->integer('id') ?: null;
        $validated = $request->validate([
            'id' => 'nullable|exists:media_tiers,id',
            'name' => ['required', 'string', 'max:50', Rule::unique('media_tiers', 'name')->ignore($tierId)],
            'rank' => ['required', 'integer', 'min:1', Rule::unique('media_tiers', 'rank')->ignore($tierId)],
            'min_photo_price' => 'required|numeric|min:0',
            'max_photo_price' => 'required|numeric|gte:min_photo_price',
            'max_photo_payout_cap' => 'required|numeric|min:0|lte:max_photo_price',
            'min_video_price' => 'required|numeric|min:0',
            'max_video_price' => 'required|numeric|gte:min_video_price',
            'max_video_payout_cap' => 'required|numeric|min:0|lte:max_video_price',
            'is_plan_eligible' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $tier = DB::transaction(function () use ($validated) {
                $tier = MediaTier::updateOrCreate(
                    ['id' => $validated['id'] ?? null],
                    [
                        'name' => $validated['name'],
                        'rank' => $validated['rank'],
                        'min_price_photo' => $validated['min_photo_price'],
                        'max_price_photo' => $validated['max_photo_price'],
                        'payout_cap_photo' => $validated['max_photo_payout_cap'],
                        'min_price_video' => $validated['min_video_price'],
                        'max_price_video' => $validated['max_video_price'],
                        'payout_cap_video' => $validated['max_video_payout_cap'],
                        'is_plan_eligible' => $validated['is_plan_eligible'] ?? true,
                        'is_active' => $validated['is_active'] ?? true,
                    ]
                );

                if (! app(MediaTierService::class)->validateTiersIntegrity()) {
                    throw ValidationException::withMessages([
                        'tier_boundaries' => ['Media tier price ranges must be ordered and must not overlap.'],
                    ]);
                }

                LicenceType::query()->where('is_active', true)->where(function ($query) {
                    $query->where('photo_quota', '>', 0)->orWhere('video_quota', '>', 0);
                })->each(function (LicenceType $plan) {
                    $result = $this->financialEngine->analyzePlan($plan);
                    if (! $result['is_valid']) {
                        throw ValidationException::withMessages([
                            'financial_validation' => ["The tier change would make active plan '{$plan->name}' financially invalid. ".implode(' ', $result['errors'])],
                        ]);
                    }
                });

                return $tier;
            });

            Cache::forget('media_tiers_active_ordered');

            return $this->success($tier, 'Tier saved successfully');
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function deleteTier($id)
    {
        try {
            $tier = MediaTier::findOrFail((int) $id);
            if (LicenceType::where('media_tier_id', $tier->id)->exists() || $tier->planLimits()->exists()) {
                return $this->error('Cannot delete tier: one or more plans are using this tier.');
            }
            $tier->delete();
            Cache::forget('media_tiers_active_ordered');

            return $this->success(null, 'Tier deleted successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function creditReports()
    {
        try {
            $totalGrants = UserCreditGrant::count();
            $totalPhotosGranted = (int) UserCreditGrant::sum('photo_count');
            $totalVideosGranted = (int) UserCreditGrant::sum('video_count');
            $totalPhotosRemaining = (int) UserCreditGrant::sum('remaining_photos');
            $totalVideosRemaining = (int) UserCreditGrant::sum('remaining_videos');

            $totalPhotosRedeemed = $totalPhotosGranted - $totalPhotosRemaining;
            $totalVideosRedeemed = $totalVideosGranted - $totalVideosRemaining;

            $redemptions = CreditTransaction::where('action', 'redemption')
                ->latest()
                ->take(50)
                ->get();

            $totalCreatorPayouts = 0.0;
            $totalSavings = 0.0;
            foreach ($redemptions as $redemption) {
                $snapshot = $redemption->snapshot ?? [];
                $totalCreatorPayouts += (float) ($snapshot['creator_payout'] ?? 0);
                $totalSavings += (float) ($snapshot['reference_price_difference'] ?? 0);
            }

            return $this->success([
                'grants' => [
                    'total_grants_issued' => $totalGrants,
                    'photos' => [
                        'granted' => $totalPhotosGranted,
                        'redeemed' => $totalPhotosRedeemed,
                        'remaining' => $totalPhotosRemaining,
                    ],
                    'videos' => [
                        'granted' => $totalVideosGranted,
                        'redeemed' => $totalVideosRedeemed,
                        'remaining' => $totalVideosRemaining,
                    ],
                ],
                'financials' => [
                    'recent_creator_payouts_sample' => round($totalCreatorPayouts, 2),
                    'recent_reference_difference_sample' => round($totalSavings, 2),
                ],
                'recent_redemptions' => $redemptions,
            ], 'Credit reports loaded');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    private function requestedTierLimits(array $data): array
    {
        if (array_key_exists('tier_limits', $data)) {
            return $data['tier_limits'] ?? [];
        }

        return $this->financialEngine->legacyTierLimits(
            isset($data['media_tier_id']) ? (int) $data['media_tier_id'] : null,
            (int) ($data['photo_quota'] ?? 0),
            (int) ($data['video_quota'] ?? 0),
        );
    }

    private function syncTierLimits(LicenceType $plan, array $limits): void
    {
        $plan->tierLimits()->delete();
        foreach ($limits as $limit) {
            $plan->tierLimits()->create([
                'media_tier_id' => (int) $limit['media_tier_id'],
                'photo_maximum' => (int) $limit['photo_maximum'],
                'video_maximum' => (int) $limit['video_maximum'],
            ]);
        }
    }

    private function revalidatePlans(bool $persist = true): array
    {
        $summary = ['safe' => [], 'unsafe' => [], 'unconfigured' => []];
        LicenceType::query()->with('tierLimits')->get()->each(function (LicenceType $plan) use (&$summary, $persist) {
            $analysis = $this->financialEngine->analyzePlan($plan);
            $status = $analysis['financial_safety_status'];
            $summary[$status][] = [
                'id' => $plan->id,
                'name' => $plan->name,
                'is_active' => (bool) $plan->is_active,
                'minimum_sustainable_price' => $analysis['minimum_sustainable_price'],
                'errors' => $analysis['errors'],
            ];
            if ($persist) {
                $plan->forceFill([
                    'financial_status' => $status,
                    'financial_settings_version' => $analysis['settings_version'],
                    'financial_analysis' => $analysis,
                    'financially_validated_at' => now(),
                ])->save();
            }
        });
        $summary['sold_grants_unchanged'] = true;

        return $summary;
    }
}
