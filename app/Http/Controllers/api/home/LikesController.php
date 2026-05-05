<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Likes\LikeRepositoryInterface;

class LikesController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly LikeRepositoryInterface $likeRepository)
    {
    }

    public function count()
    {
        try {
            $count = $this->likeRepository->countByEventId((int) request('id'));
            $liked = $this->likeRepository->countByUserAndEvent((int) auth()->user()->id, (int) request('id'));
            if($liked>0){
                return $this->success(['liked' => true, 'count' => $count], 'Likes Number for Event');
            }
            return $this->success($count, 'Likes Number for Event');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }

    }

    public function create()
    {
        try {
            $data = [];
            $data['user_id'] = auth()->user()->id;
            $data['event_id'] = request('id');
            $like = $this->likeRepository->create($data);

            return $this->success($like, 'Like Created Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }
}
