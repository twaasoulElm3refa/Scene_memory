<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanRequest;
use App\Jobs\TranslatePlanJob;
use App\Models\licenceType;

class AdminPlanController extends Controller
{
    use ApiResponse;

    public function create(PlanRequest $request)
    {
        $data=$request->validated();
        try {
            $plan = licenceType::firstOrCreate($data);
            TranslatePlanJob::dispatch($plan->id,$data['name']);
            return $this->success($plan,'plan Created Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function update()
    {
        try {
            $plan = licenceType::find(request('id'));
            $plan->update(request()->all());
              TranslatePlanJob::dispatch($plan->id,$plan->name);
            return $this->success($plan,'plan Updated Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function delete()
    {
        try {
            $plan = licenceType::find(request('id'));
            $plan->delete();
            return $this->success($plan,'plan Deleted Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }


}
