<?php

namespace App\Repositories\Eloquent\Tags;

use App\Models\Tags;
use App\Repositories\Contracts\Tags\TagRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class TagsRespository implements TagRepositoryInterface
{
    public function getAllTags()
    {
        return Cache::remember('all_tags', now()->addHours(1), function () {
            return Tags::query()
                ->select('id', 'name', 'slug')
                ->orderBy('id', 'desc')
                ->get();
        });
    }
}
