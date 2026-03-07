<?php

namespace App\Http\Controllers\api\userDshboard;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadMediaRequest;
use App\Models\Events;
use App\Models\eventsImges;
use Illuminate\Support\Facades\Cache;

class MediaRequestController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function all()
    {
        $cacheKey = 'mediaRequest_'.request()->input('page', 1);
        $mediaRequest = Cache::remember($cacheKey, $this->cacheTime, function () {
             $eventimges =eventsImges::where('event_id', request('id'))->where('is_active', 0)->orderBy('created_at','desc')->paginate(10);
            return $eventimges;
        });

        return $this->success($mediaRequest, 'get media request successfully');
    }

    public function upload(UploadMediaRequest $request)
    {
        $data = $request->validated();
        $event = Events::find(request('id'));
        $createdMedia = [];

        if ($request->hasFile('url')) {
            foreach ($request->file('url') as $file) {
                $path = $file->store('EventMedia', 'public');
                $media = eventsImges::create([
                    'event_id' => $event->id,
                    'url' => $path,
                    'is_active' => 1
                ]);
                $createdMedia[] = $media;
            }
        }
        return $this->success($createdMedia, 'تم إضافة الوسائط بنجاح');
    }
}
