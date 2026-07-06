<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
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
}
