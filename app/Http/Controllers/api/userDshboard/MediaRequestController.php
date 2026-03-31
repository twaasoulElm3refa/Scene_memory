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
        $event = Events::findOrFail(request('id'));
        $createdMedia = [];

        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());

        if ($request->hasFile('url')) {
            foreach ($request->file('url') as $file) {

                $mime = $file->getMimeType();

                if (str_starts_with($mime, 'image/')) {

                    // 🔥 تحليل الصورة (نفس create function)
                    $analysis = $imageAnalysisService->process($file, $manager);

                    $image = $analysis['image'];
                    $width = $analysis['width'];
                    $height = $analysis['height'];
                    $price = $analysis['price'];
                    $plan = $analysis['plan'];

                    $filename = uniqid() . '.jpg';

                    $fullPath = 'events/full/' . $filename;
                    $previewPath = 'events/previews/' . $filename;

                    // حفظ الصورة الأصلية
                    \Storage::disk('public')->put(
                        $fullPath,
                        $image->toJpeg(90)
                    );

                    // Preview (blur + watermark)
                    $preview = $manager->read($file)
                        ->blur(25)
                        ->pixelate(10);

                    $watermarkPath = public_path('images/watermark.png');

                    if (file_exists($watermarkPath)) {
                        $preview->place($watermarkPath, 'bottom-right', 15, 15);
                    }

                    $preview->text('PREVIEW', 50, 50, function ($font) {
                        $font->size(40);
                        $font->color('#ffffff');
                        $font->angle(-30);
                    });

                    \Storage::disk('public')->put(
                        $previewPath,
                        $preview->toJpeg(80)
                    );

                    $media = eventsImges::create([
                        'event_id' => $event->id,
                        'preview_url' => $previewPath,
                        'full_url' => $fullPath,
                        'width' => $width,
                        'height' => $height,
                        'size' => $width * $height,
                        'price' => $price,
                        'licence_type' => $plan,
                        'is_active' => 1
                    ]);

                    $createdMedia[] = $media;

                } elseif (str_starts_with($mime, 'video/')) {

                    $path = $file->store('videos_temp', 'public');

                    ProcessEventVideoJob::dispatch($event->id, $path);

                    $media = eventsImges::create([
                        'event_id' => $event->id,
                        'url' => $path,
                        'is_active' => 1
                    ]);

                    $createdMedia[] = $media;
                }
            }
            Cache::tags(['events'])->flush();
        }

        return $this->success($createdMedia, 'تم إضافة الوسائط بنجاح');
    }
}
