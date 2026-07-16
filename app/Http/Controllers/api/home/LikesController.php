<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Likes\LikeRepositoryInterface;
use Illuminate\Support\Facades\Log;

class LikesController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly LikeRepositoryInterface $likeRepository)
    {
    }

    public function count()
    {
        try {
            $eventId = (int) request('id');

            $count = $this->likeRepository->countByEventId($eventId);

            $liked = false;

            if (auth()->check()) {
                $liked = $this->likeRepository->countByUserAndEvent(
                    auth()->id(),
                    $eventId
                ) > 0;
            }

            return $this->success([
                'liked' => $liked,
                'count' => $count
            ], 'Likes Number for Event');

        } catch (\Throwable $th) {
            Log::error('Likes Error', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
            ]);

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
