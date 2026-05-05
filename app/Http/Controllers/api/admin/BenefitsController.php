<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\BenefitRequest;
use App\Repositories\Contracts\Benefits\BenefitRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class BenefitsController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly BenefitRepositoryInterface $benefitRepository)
    {
    }

    public function create(BenefitRequest $request)
    {
        $data=$request->validated();
        try {
            $data['plan_id']=request('id');
            $benefit = $this->benefitRepository->create($data);
            $this->clearCache();
            return $this->success($benefit,'Benefit Created Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }

    }

      public function update(BenefitRequest $request)
    {
        $data=$request->validated();
        try {
           $benefit = $this->benefitRepository->find((int) request('id'));
           $benefit->update($data);
            $this->clearCache();
            return $this->success($benefit,'Benefit Updated Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }

    }

    public function delete()
    {
        $benefit = $this->benefitRepository->find((int) request('id'));
        $benefit->delete();
        $this->clearCache();
        return $this->success($benefit,'Benefit Deleted Successfully');
    }
    private function clearCache()
    {
        Cache::tags(['plans'])->flush();
    }
}
