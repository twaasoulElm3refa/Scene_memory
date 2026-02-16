<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\categoreyRequest;
use App\Models\Categories;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    use ApiResponse;

    protected $cacheTime = 3600;

    public function index()
    {
        $cacheKey = 'categories_index';
        $categories = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Categories::get(['id', 'name']);
        });
        if ($categories->isEmpty()) {
            return $this->error('No More categories', 404);
        }

        return $this->success($categories, 'All categories');
    }

    public function paginated()
    {
        $page = request()->get('page', 1);
        $perPage = 4;

        $version = Cache::get('categories_cache_version', 1);

        $cacheKey = "categories_v{$version}_page_{$page}_per_{$perPage}";

        $categories = Cache::remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return Categories::select('id', 'name', 'image')
                ->withCount('events')
                ->paginate($perPage);
        });

        if ($categories->isEmpty()) {
            return $this->error('No More categories', 404);
        }

        return $this->success($categories, 'All categories paginated');
    }

    public function single()
    {
        $categoreyId = request('id');
        $cacheKey = "categorey_single_{$categoreyId}";
        $categorey = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Categories::with('events')->find(request('id'));
        });
        if (! $categorey) {
            return $this->error('No More categories', 404);
        }

        return $this->success($categorey, 'category');
    }

    public function create(categoreyRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $data['slug'] = str_replace(' ', '-', strtolower($data['name'])).'-'.time();

        $categorey = Categories::create($data);

        Cache::increment('categories_cache_version'); // 👈 clear cache smart way

        return $this->success($categorey, 'category Created Successfully');
    }
}
