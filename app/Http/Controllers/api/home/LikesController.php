<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Likes;

class LikesController extends Controller
{
    use ApiResponse;

    public function count()
    {
        try {
            $count = Likes::where('event_id', request('id'))->count();
            $liked = Likes::where('user_id', auth()->user()->id)->where('event_id', request('id'))->count();
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
            $like = Likes::create($data);

            return $this->success($like, 'Like Created Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }
}
