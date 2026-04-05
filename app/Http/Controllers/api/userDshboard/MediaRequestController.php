<?php

namespace App\Http\Controllers\api\userDshboard;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadMediaRequest;
use App\Jobs\ProcessEventVideoJob;
use App\Models\Events;
use App\Models\eventsImges;
use App\Services\ImageAnalysisService;
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

    public function upload(UploadMediaRequest $request, ImageAnalysisService $imageAnalysisService)
    {
        $event = Events::findOrFail($request->id);
        $createdMedia = [];

        $manager = new \Intervention\Image\ImageManager(
            new \Intervention\Image\Drivers\Gd\Driver()
        );

        if ($request->hasFile('url')) {

            foreach ($request->file('url') as $file) {

                $mime = $file->getMimeType();

                // =======================
                // 📸 IMAGE
                // =======================
                if (str_starts_with($mime, 'image/')) {

                    $analysis = $imageAnalysisService->process($file, $manager);

                    $image = $analysis['image'];
                    $width = $analysis['width'];
                    $height = $analysis['height'];
                    $price = $analysis['price'];
                    $plan = $analysis['plan'];

                    $filename = uniqid() . '.jpg';
                    $fullPath = 'events/full/' . $filename;

                    // حفظ الصورة فقط (no preview)
                    \Storage::disk('public')->put(
                        $fullPath,
                        $image->toJpeg(90)
                    );

                    $media = eventsImges::create([
                        'event_id'     => $event->id,
                        'full_url'     => $fullPath,
                        'width'        => $width,
                        'height'       => $height,
                        'size'         => $width * $height,
                        'price'        => $price,
                        'licence_type' => $plan,
                        'is_active'    => 1
                    ]);

                    $createdMedia[] = $media;

                }

                // =======================
                // 🎥 VIDEO
                // =======================
                elseif (str_starts_with($mime, 'video/')) {

                    $path = $file->store('events/videos', 'public');
                    ProcessEventVideoJob::dispatch($event->id, $path);

                    $media = eventsImges::create([
                        'event_id'  => $event->id,
                        'full_url'  => $path, // ✅ بدل url
                        'is_active' => 1
                    ]);

                    $createdMedia[] = $media;
                }
            }

            Cache::tags(['events'])->flush();
        }

        return $this->success([
            'media' => $createdMedia
        ], 'تم إضافة الوسائط بنجاح');
    }
}
