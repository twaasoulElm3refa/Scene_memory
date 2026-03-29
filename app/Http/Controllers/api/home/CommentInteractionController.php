<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CommentInteractions;
use App\Models\CommentReport;
use App\Models\comments;
use App\Models\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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


        foreach (['ar', 'en', 'fr','es','ja','zh','fa','ur','ru','it','de','hi'] as $locale) {
            $key = 'event_' . strtolower(trim($event->slug)) . '_' . $locale;
            Cache::tags(['events'])->forget($key);
        }
        Cache::tags(['comments'])->flush();


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

        foreach (['ar', 'en', 'fr'] as $locale) {
            $key = 'event_' . strtolower(trim($event->slug)) . '_' . $locale;
            Cache::tags(['events'])->forget($key);
        }
        Cache::tags(['comments'])->flush();

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

        foreach (['ar', 'en', 'fr'] as $locale) {
            $key = 'event_' . strtolower(trim($event->slug)) . '_' . $locale;
            Cache::tags(['events'])->forget($key);
        }
        Cache::tags(['comments'])->flush();

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
}
