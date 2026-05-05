<?php

namespace App\Repositories\Eloquent\Benefits;

use App\Models\PlanBenefits;
use App\Repositories\Contracts\Benefits\BenefitRepositoryInterface;

class BenefitRepository implements BenefitRepositoryInterface
{
    public function create(array $data)
    {
        return PlanBenefits::create($data);
    }

    public function find(int $id)
    {
        return PlanBenefits::find($id);
    }
}
