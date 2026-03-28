<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\licenceType;
use Illuminate\Support\Facades\Cache;

class PlanController extends Controller
{
    use ApiResponse;

    private $cacheTime = 60 * 60 * 24;

    public function all()
    {
        $cacheKey = 'plans'.$this->cacheTime.''.app()->getLocale();
        $plans = Cache::tags(['plans'])->remember($cacheKey, $this->cacheTime, function () {
            return licenceType::with('translation','advantges')->get();
        });

        return $this->success($plans, 'All plans');
    }

    public function single()
    {
        $cacheKey = 'plans_single_' . request('id') . '_' . app()->getLocale();

        $plans = Cache::tags(['plans'])->remember($cacheKey, $this->cacheTime, function () {
            return licenceType::with('translation', 'advantges')
                ->where('id', request('id'))
                ->get();
        });

        return $this->success($plans, 'Single plan');
    }
}
