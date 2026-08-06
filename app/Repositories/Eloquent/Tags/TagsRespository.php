<?php

namespace App\Repositories\Eloquent\Tags;

use App\Jobs\TranslateTagJob;
use App\Models\Tags;
use App\Repositories\Contracts\Tags\TagRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TagsRespository implements TagRepositoryInterface
{
    public function getAllTags()
    {
        return Cache::remember('all_tags_v2', now()->addHours(1), function () {
            return Tags::query()
                ->select('id', 'name', 'slug', 'mode')
                ->with([
                    'translation' => function ($query) {
                        $query->select(
                            'id',
                            'tag_id',
                            'locale',
                            'name'
                        );
                    }
                ])
                ->orderByDesc('id')
                ->get();
        });
    }

    public function paginated($perPage = 30)
    {
        return Tags::query()
            ->select('id', 'name', 'slug', 'mode')
            ->with([
                'translation' => function ($query) {
                    $query->select(
                        'id',
                        'tag_id',
                        'locale',
                        'name'
                    );
                }
            ])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getTagById($id)
    {
        return Tags::query()
            ->select('id', 'name', 'slug', 'mode')
            ->with([
                'translation' => function ($query) {
                    $query->select(
                        'id',
                        'tag_id',
                        'locale',
                        'name'
                    );
                }
            ])
            ->where('slug', $id)
            ->firstOrFail();
    }

    public function createTag(array $data)
    {
        $data['slug'] = str_replace(' ', '-', strtolower($data['name']));
        $tag = Tags::create($data);
        TranslateTagJob::dispatch($tag->id , $data['name']);
        return $tag;
    }

    public function updateTag($id, array $data)
    {
        try {
            $data['slug'] = str_replace(' ', '-', strtolower($data['name']));
            $tag = $this->getTagById($id);
            $tag->update($data);
            TranslateTagJob::dispatch($tag->id , $data['name']);
            return $tag;
        } catch (\Throwable $th) {
            Log::error('Error updating tag: ' . $th->getMessage());
            return null;
        }
    }

    public function deleteTag($id)
    {
        $tag = $this->getTagById($id);
        $tag->delete();
        return true;
    }
}
