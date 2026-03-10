<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyRequest;
use App\Models\CommentReplies;
use App\Models\comments;
use App\Models\Events;
use Illuminate\Support\Facades\Cache;

class CommentReplyController extends Controller
{
    use ApiResponse;

    public function create(ReplyRequest $request)
    {
        $comment=comments::find(request("id"));
        $event=Events::find($comment->event_id);
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $data['comment_id'] = request('id');
        $reply = CommentReplies::create($data);
        $this->clearCache($event->slug);
        return $this->success($reply, 'Comment Created Successfully');
    }

    private function clearCache($slug = '')
    {
        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];
        foreach ($locales as $locale) {
            Cache::forget("events_single_{$slug}_".$locale);
        }
        Cache::flush();
    }
}
