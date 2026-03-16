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
        $comment = comments::findOrFail(request('id'));
        $event = Events::findOrFail($comment->event_id);

        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $data['comment_id'] = $comment->id;

        $reply = CommentReplies::create($data);

        // مسح cache الحدث + التعليقات
        $this->clearCache($event->slug);

        return $this->success($reply, 'Reply Created Successfully');
    }

    private function clearCache($slug = '')
    {
        // مسح cache الحدث الفردي لكل اللغات
        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];
        foreach ($locales as $locale) {
            Cache::tags(['events'])->forget("events_single_{$slug}_".$locale);
        }

        // مسح cache التعليقات المرتبطة بالحدث
        Cache::tags(['comments'])->flush();
    }
}
