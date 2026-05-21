<?php

namespace App\Repositories\Eloquent\Footers;

use App\Models\Footer;
use App\Repositories\Contracts\Footers\FooterRepositoryInterface;

class FooterRepository implements FooterRepositoryInterface
{
    public function first()
    {
        return Footer::find(1);
    }

    public function findOrFail(int $id)
    {
        return Footer::findOrFail($id);
    }
}
