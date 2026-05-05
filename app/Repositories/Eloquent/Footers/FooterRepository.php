<?php

namespace App\Repositories\Eloquent\Footers;

use App\Models\footer;
use App\Repositories\Contracts\Footers\FooterRepositoryInterface;

class FooterRepository implements FooterRepositoryInterface
{
    public function first()
    {
        return footer::find(1);
    }

    public function findOrFail(int $id)
    {
        return footer::findOrFail($id);
    }
}
