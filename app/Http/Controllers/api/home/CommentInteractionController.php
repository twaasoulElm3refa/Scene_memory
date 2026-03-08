<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CommentInteractions;
use App\Models\CommentReport;
use App\Models\comments;
use App\Models\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CommentInteractionController extends Controller
{
    use ApiResponse;

    public function support()
    {
        $comment = comments::find(request('id'));
        $event = Events::find($comment->event_id);
        $interctions = CommentInteractions::firstOrCreate([
            'comment_id' => $comment->id,
            'user_id' => auth()->user()->id,
            'type' => 'support',
        ]);
        $this->clearCache($event->slug);

        return $this->success($interctions, 'Interaction Created Successfully');
    }

    public function exhibitions()
    {
        $comment = comments::find(request('id'));
        $event = Events::find($comment->event_id);
        $interctions = CommentInteractions::firstOrCreate([
            'comment_id' => $comment->id,
            'user_id' => auth()->user()->id,
            'type' => 'Exhibitions',
        ]);
        $this->clearCache($event->slug);
        return $this->success($interctions, 'Interaction Created Successfully');
    }

    public function neutral()
    {
        $comment = comments::find(request('id'));
        $event = Events::find($comment->event_id);
        $interctions = CommentInteractions::firstOrCreate([
            'comment_id' => $comment->id,
            'user_id' => auth()->user()->id,
            'type' => 'neutral',
        ]);
        $this->clearCache($event->slug);
        return $this->success($interctions, 'Interaction Created Successfully');
    }

    public function report(Request $request)
    {
        $comment = comments::find(request('id'));
        $interctions = CommentReport::firstOrCreate([
            'comment_id' => $comment->id,
            'user_id' => auth()->user()->id,
            'reason' => $request->reason,
        ]);
        Cache::forget('reports_all');
        Cache::flush();
        return $this->success($interctions, 'Interaction Created Successfully');
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
