<?php

namespace App\Repositories\Contracts\Benefits;

interface BenefitRepositoryInterface
{
    public function create(array $data);
    public function find(int $id);
}
