<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CommentRequest;
use App\Models\comments;
use App\Models\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CommentController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function create(CommentRequest $request)
    {
        $data = $request->validated();
        try {
            $data['event_id'] = request('id');
            $data['user_id'] = auth()->user()->id;
            $comment = comments::create($data);
            $event = Events::find($data['event_id']);
            Cache::forget("events_single_{$event->slug}");
            Cache::flush();

            return $this->success($comment, 'Comment Created Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }

    }

    public function destroy(Request $request)
    {
        try {
            $comment = Comments::find(request('id'));

            if ($request->user()->id != $comment->user_id) {
                return $this->unauthorized('You are not the owner of this comment');
            }
            
            $this->clearCache($comment->event_id);
            $comment->delete();
            return $this->success([], 'comment deleted successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function allPaginated()
    {
        try {
            $cacheKey = 'comments_page_'.request('page', 1).'_event_'.request('id');
            $comments = Cache::remember($cacheKey, $this->cacheTime, function () {
                $comments = Comments::where('event_id', request('id'))->latest()->paginate(10);
                return $comments;
            });

            return $this->success($comments, 'all comments');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    private function clearCache($event_id)
    {
        for( $i = 0; $i < 10; $i++ ) {
            Cache::forget("comments_page_{$i}_event_{$event_id}");
        }
        Cache::flush();
    }
}
