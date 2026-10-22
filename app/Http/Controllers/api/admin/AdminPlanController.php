<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanRequest;
use App\Jobs\TranslatePlanJob;
use App\Models\CreditTransaction;
use App\Models\LicenceType;
use App\Models\MediaTier;
use App\Models\UserCreditGrant;
use App\Repositories\Contracts\Plans\PlanRepositoryInterface;
use App\Services\MediaTierService;
use App\Services\PlanFinancialValidator;
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

    public function __construct(private readonly PlanRepositoryInterface $planRepository) {}

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
            $tier = ! empty($data['media_tier_id']) ? MediaTier::find($data['media_tier_id']) : null;
            $val = PlanFinancialValidator::validatePlan(
                (float) $data['price'],
                $tier,
                (int) ($data['photo_quota'] ?? 0),
                (int) ($data['video_quota'] ?? 0)
            );
            if (! $val['is_valid']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $val['message'],
                    'validation' => $val,
                ], 422);
            }

            $data['slug'] = Str::slug($data['name']).'-'.Str::random(5).'-'.time();
            $plan = $this->planRepository->firstOrCreate($data);
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

            // Financial validation check
            $mediaTierId = $data['media_tier_id'] ?? $plan->media_tier_id;
            $photoQuota = isset($data['photo_quota']) ? (int) $data['photo_quota'] : (int) $plan->photo_quota;
            $videoQuota = isset($data['video_quota']) ? (int) $data['video_quota'] : (int) $plan->video_quota;
            $price = isset($data['price']) ? (float) $data['price'] : (float) $plan->price;

            $tier = $mediaTierId ? MediaTier::find($mediaTierId) : null;
            $val = PlanFinancialValidator::validatePlan($price, $tier, $photoQuota, $videoQuota);
            if (! $val['is_valid']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $val['message'],
                    'validation' => $val,
                ], 422);
            }

            $data['slug'] = Str::slug($data['name']).'-'.Str::random(5).'-'.time();
            $plan->update($data);
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
                return LicenceType::with(['advantges', 'mediaTier'])->find($id);
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
            'media_tier_id' => ['nullable', 'integer', 'exists:media_tiers,id'],
            'photo_quota' => ['nullable', 'integer', 'min:0'],
            'video_quota' => ['nullable', 'integer', 'min:0'],
        ]);

        $price = (float) $validated['price'];
        $tierId = $validated['media_tier_id'] ?? null;
        $photoQuota = (int) ($validated['photo_quota'] ?? 0);
        $videoQuota = (int) ($validated['video_quota'] ?? 0);

        $tier = $tierId ? MediaTier::find($tierId) : null;
        $result = PlanFinancialValidator::validatePlan($price, $tier, $photoQuota, $videoQuota);

        return response()->json($result);
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

                LicenceType::query()->where(function ($query) {
                    $query->where('photo_quota', '>', 0)->orWhere('video_quota', '>', 0);
                })->each(function (LicenceType $plan) {
                    $planTier = $plan->media_tier_id ? MediaTier::find($plan->media_tier_id) : null;
                    $result = PlanFinancialValidator::validatePlan(
                        (float) $plan->price,
                        $planTier,
                        (int) $plan->photo_quota,
                        (int) $plan->video_quota
                    );
                    if (! $result['is_valid']) {
                        throw ValidationException::withMessages([
                            'financial_validation' => ["The tier change would make plan '{$plan->name}' financially invalid. {$result['message']}"],
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
            if (LicenceType::where('media_tier_id', $tier->id)->exists()) {
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
}
