<?php

namespace App\Repositories\Eloquent\Plans;

use App\Models\licenceType;
use App\Repositories\Contracts\Plans\PlanRepositoryInterface;

class PlanRepository implements PlanRepositoryInterface
{
    public function allWithTranslationsAndBenefits()
    {
        return licenceType::with('translation', 'advantges.translation')->get();
    }

    public function allForAdmin()
    {
        return licenceType::select('id', 'name', 'price')->with(['translation:id,plan_id,name', 'advantges:id,plan_id,feature'])->get();
    }

    public function firstOrCreate(array $data)
    {
        return licenceType::firstOrCreate($data);
    }

    public function find(int $id)
    {
        return licenceType::find($id);
    }

    public function findOrFail(int $id)
    {
        return licenceType::findOrFail($id);
    }

    public function bySlugWithTranslations(string $slug)
    {
        return licenceType::with('translation', 'advantges.translation')->where('slug', $slug)->get();
    }

    public function byIdWithBenefits(int $id)
    {
        return licenceType::with('advantges')->where('id', $id)->get();
    }
}
