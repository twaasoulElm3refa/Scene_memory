<?php

namespace App\Repositories\Contracts\Plans;

interface PlanRepositoryInterface
{
    public function allWithTranslationsAndBenefits();
    public function allForAdmin();
    public function firstOrCreate(array $data);
    public function find(int $id);
    public function findOrFail(int $id);
    public function bySlugWithTranslations(string $slug);
    public function byIdWithBenefits(int $id);
}
