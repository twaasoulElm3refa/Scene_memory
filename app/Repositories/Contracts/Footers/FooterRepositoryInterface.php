<?php

namespace App\Repositories\Contracts\Footers;

interface FooterRepositoryInterface
{
    public function first();
    public function findOrFail(int $id);
}
