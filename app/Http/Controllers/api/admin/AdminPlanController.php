<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanRequest;
use App\Jobs\TranslatePlanJob;
use App\Models\licenceType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdminPlanController extends Controller
{
    use ApiResponse;
    private $cacheTime = 60 * 24 * 7;

    private function clearCache()
    {
        Cache::tags(['plans'])->flush();
    }
    public function all()
    {
        $cacheKey = 'plans_admin_'.$this->cacheTime.''.app()->getLocale();
        $plans = Cache::tags(['plans'])->remember($cacheKey, $this->cacheTime, function () {
           return licenceType::select('id','name','price')
    ->with([
        'translation:id,plan_id,name',
        'advantges:id,plan_id,feature'
    ])
    ->get();
        });

        return $this->success($plans, 'All plans');
    }

    public function create(PlanRequest $request)
    {
        $data=$request->validated();
        try {
            $data['slug'] = Str::slug($data['name']).'-'.Str::random(5).'-'.time();
            $plan = licenceType::firstOrCreate($data);
            TranslatePlanJob::dispatch($plan->id, $data['name']);
            $this->clearCache();
            return $this->success($plan,'plan Created Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }


    public function update()
    {
        $data=request()->all();
        try {
            $data['slug'] = Str::slug($data['name']).'-'.Str::random(5).'-'.time();
            $plan = licenceType::find(request('id'));
            $plan->update(request()->all());
            TranslatePlanJob::dispatch($plan->id, $plan->name);
            $this->clearCache();
            return $this->success($plan,'plan Updated Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $plan = licenceType::findOrFail($id);
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
            $cacheKey = 'plans_single_admin_' . request('id') . '_' . app()->getLocale();

            $plans = Cache::tags(['plans'])->remember($cacheKey, $this->cacheTime, function () {
                return licenceType::with('advantges')
                    ->where('id', request('id'))
                    ->get();
            });

            return $this->success($plans, 'Single plan');

        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }
}
