<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Tags;
use App\Repositories\Contracts\Tags\TagRepositoryInterface;
use Illuminate\Http\Request;

class TagsController extends Controller
{
    use ApiResponse;

    protected $cacheTime = 600;

    public function __construct(
        private readonly TagRepositoryInterface $tagRepository
    ) {
    }

    public function index()
    {
        return $this->success($this->tagRepository->getAllTags(),'Tags fetched successfully');
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $limit = min(max((int) $request->query('limit', 8), 1), 10);

        $query = Tags::query()
            ->select(['id', 'name', 'slug']);

        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

            $query->where(function ($tagQuery) use ($like) {
                $tagQuery->where('name', 'like', $like);
            });
        }

        return $this->success(
            $query
                ->orderBy('name')
                ->limit($limit)
                ->get(),
            'Tags fetched successfully'
        );
    }
}
