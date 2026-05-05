<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Plans\PlanRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class PlanController extends Controller
{
    use ApiResponse;

    private $cacheTime = 60 * 60 * 24;

    public function __construct(private readonly PlanRepositoryInterface $planRepository)
    {
    }

    public function all()
    {
        $cacheKey = 'plans'.$this->cacheTime.''.app()->getLocale();
        $plans = Cache::tags(['plans'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->planRepository->allWithTranslationsAndBenefits();
        });

        return $this->success($plans, 'All plans');
    }

    public function single()
    {
        $cacheKey = 'plans_single_' . request('slug') . '_' . app()->getLocale();
        $plans = Cache::tags(['plans'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->planRepository->bySlugWithTranslations((string) request('slug'));
        });

        return $this->success($plans, 'Single plan');
    }
}
