<?php

namespace App\Repositories\Eloquent\Plans;

use App\Models\LicenceType;
use App\Repositories\Contracts\Plans\PlanRepositoryInterface;

class PlanRepository implements PlanRepositoryInterface
{
    public function allWithTranslationsAndBenefits()
    {
        return LicenceType::with('translation', 'advantges.translation')->get();
    }

    public function allForAdmin()
    {
        return LicenceType::select('id', 'name', 'price')->with(['translation:id,plan_id,name', 'advantges:id,plan_id,feature'])->get();
    }

    public function firstOrCreate(array $data)
    {
        return LicenceType::firstOrCreate($data);
    }

    public function find(int $id)
    {
        return LicenceType::find($id);
    }

    public function findOrFail(int $id)
    {
        return LicenceType::findOrFail($id);
    }

    public function bySlugWithTranslations(string $slug)
    {
        return LicenceType::with('translation', 'advantges.translation')->where('slug', $slug)->get();
    }

    public function byIdWithBenefits(int $id)
    {
        return LicenceType::with('advantges')->where('id', $id)->get();
    }
}
