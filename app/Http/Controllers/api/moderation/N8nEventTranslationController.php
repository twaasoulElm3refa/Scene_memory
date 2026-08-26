<?php

namespace App\Http\Controllers\api\moderation;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventTranslationsRequest;
use App\Models\Events;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class N8nEventTranslationController extends Controller
{
    public function store(
        StoreEventTranslationsRequest $request,
        Events $event
    ): JsonResponse {
        $translations = $request->validated('translations');

        DB::transaction(function () use ($event, $translations): void {
            foreach ($translations as $translation) {
                $event->translations()->updateOrCreate(
                    ['locale' => $translation['locale']],
                    [
                        'title' => $translation['title'],
                        'description' => $translation['description'],
                    ]
                );
            }
        });

        return response()->json([
            'status' => 'success',
            'event_id' => $event->id,
            'translations_saved' => count($translations),
        ]);
    }
}
