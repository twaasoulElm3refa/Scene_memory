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
                ->with([
                    'translation' => function ($query) {
                        $query->select(
                            'id',
                            'tag_id', // غيرها لو اسم الـ FK مختلف
                            'locale',
                            'name'
                        );
                    }
                ])
                ->orderByDesc('id')
                ->get();
        });
    }
}
