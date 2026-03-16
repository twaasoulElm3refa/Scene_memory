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

    /**
     * دعم التعليق
     */
    public function support()
    {
        $comment = comments::findOrFail(request('id'));
        $event = Events::findOrFail($comment->event_id);

        $interaction = CommentInteractions::firstOrCreate([
            'comment_id' => $comment->id,
            'user_id' => auth()->user()->id ?? null,
            'type' => 'support',
        ]);

        $this->clearEventCache($event->slug);

        return $this->success($interaction, 'Interaction Created Successfully');
    }

    /**
     * معرض التعليق
     */
    public function exhibitions()
    {
        $comment = comments::findOrFail(request('id'));
        $event = Events::findOrFail($comment->event_id);

        $interaction = CommentInteractions::firstOrCreate([
            'comment_id' => $comment->id,
            'user_id' => auth()->user()->id ?? null,
            'type' => 'Exhibitions',
        ]);

        $this->clearEventCache($event->slug);

        return $this->success($interaction, 'Interaction Created Successfully');
    }

    /**
     * التعليقات المحايدة
     */
    public function neutral()
    {
        $comment = comments::findOrFail(request('id'));
        $event = Events::findOrFail($comment->event_id);

        $interaction = CommentInteractions::firstOrCreate([
            'comment_id' => $comment->id,
            'user_id' => auth()->user()->id ?? null,
            'type' => 'neutral',
        ]);

        $this->clearEventCache($event->slug);

        return $this->success($interaction, 'Interaction Created Successfully');
    }

    /**
     * الإبلاغ عن التعليق
     */
    public function report(Request $request)
    {
        $comment = comments::findOrFail(request('id'));

        $interaction = CommentReport::firstOrCreate([
            'comment_id' => $comment->id,
            'user_id' => auth()->user()->id ?? null,
            'reason' => $request->reason,
        ]);

        // مسح cache كل التقارير
        Cache::tags(['reports'])->flush();

        return $this->success($interaction, 'Report Created Successfully');
    }

    /**
     * مسح cache بيانات الحدث الفردية لكل اللغات
     */
    private function clearEventCache($slug)
    {
        $locales = ['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];

        foreach ($locales as $locale) {
            Cache::tags(['events'])->forget("events_single_{$slug}_".$locale);
        }

        // مسح كاش التعليقات الخاصة بالحدث
        Cache::tags(['comments'])->flush();
    }
}
