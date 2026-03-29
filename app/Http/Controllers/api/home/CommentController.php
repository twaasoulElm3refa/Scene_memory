<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CommentRequest;
use App\Jobs\TranslateCommentJob;
use App\Models\comments;
use App\Models\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CommentController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600; // 10 دقائق

    /**
     * إنشاء تعليق جديد
     */
    // في create()
    public function create(CommentRequest $request)
    {
        $data = $request->validated();
        try {
            $data['event_id'] = request('id');
            if (auth()->check()) {
                $data['user_id'] = auth()->user()->id;
            }

            $comment = comments::create($data);
            $event = Events::find($data['event_id']);

            if ($event) {
                // ✅ نمسح كل اللغات عشان منتجاهلش أي locale
                foreach (['ar', 'en', 'fr'] as $locale) {
                    $key = 'event_' . strtolower(trim($event->slug)) . '_' . $locale;
                    Cache::tags(['events'])->forget($key);
                }
            }

            TranslateCommentJob::dispatch($comment->id, $data['comment']);

            return $this->success($comment, 'Comment Created Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    /**
     * حذف تعليق
     */
    public function destroy(Request $request)
    {
        try {
            $comment = comments::findOrFail(request('id'));

            // إذا الادمن
            if (auth()->user()->role == 'admin') {
                $comment->delete();

                return $this->success([], 'Comment deleted successfully');
            }

            // التحقق من ملكية التعليق
            if ($request->user()->id != $comment->user_id) {
                return $this->unauthorized('You are not the owner of this comment');
            }

            $comment->delete();

            return $this->success([], 'Comment deleted successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    /**
     * جلب التعليقات مع pagination
     */
    public function allPaginated()
    {
        try {
            $event = Events::where('slug', request('slug'))->firstOrFail();
            $page = request('page', 1);
            $cacheKey = "comments_page_{$page}_event_{$event->id}_".app()->getLocale();

            $comments = Cache::tags(['comments'])->remember($cacheKey, $this->cacheTime, function () use ($event) {
                return comments::with([
                    'translation:id,comment_id,locale,comment,created_at',
                    'user:id,name',
                ])
                    ->withCount([
                        'interactions as support_count' => fn ($q) => $q->where('type', 'support'),
                        'interactions as exhibitions_count' => fn ($q) => $q->where('type', 'Exhibitions'),
                        'interactions as neutral_count' => fn ($q) => $q->where('type', 'neutral'),
                    ])
                    ->where('event_id', $event->id)
                    ->latest()
                    ->paginate(5);
            });

            return $this->success($comments, 'All comments');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }
}
