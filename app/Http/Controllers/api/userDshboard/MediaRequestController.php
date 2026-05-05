<?php

namespace App\Http\Controllers\api\userDshboard;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadMediaRequest;
use App\Jobs\ProcessEventVideoJob;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Services\ImageAnalysisService;
use Illuminate\Support\Facades\Cache;

class MediaRequestController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventImageRepositoryInterface $eventImageRepository
    ) {
    }

    public function all()
    {
        $cacheKey = 'mediaRequest_'.request()->input('page', 1);
        $mediaRequest = Cache::remember($cacheKey, $this->cacheTime, function () {
             return $this->eventImageRepository->findActiveByEventIdPaginated((int) request('id'), 10);
        });

        return $this->success($mediaRequest, 'get media request successfully');
    }

    public function upload(UploadMediaRequest $request, ImageAnalysisService $imageAnalysisService)
    {
        $event = $this->eventRepository->findByIdOrFail((int) $request->id);
        $createdMedia = [];

        $manager = new \Intervention\Image\ImageManager(
            new \Intervention\Image\Drivers\Gd\Driver()
        );

        if ($request->hasFile('url')) {
            foreach ($request->file('url') as $file) {
                $mime = $file->getMimeType();

                if (str_starts_with($mime, 'image/')) {
                    $analysis = $imageAnalysisService->process($file, $manager);

                    $image = $analysis['image'];
                    $width = $analysis['width'];
                    $height = $analysis['height'];
                    $price = $analysis['price'];
                    $plan = $analysis['plan'];

                    $filename = uniqid() . '.jpg';
                    $fullPath = 'events/full/' . $filename;
                    $previewPath = 'events/preview/' . $filename;

                    \Storage::disk('public')->put(
                        $fullPath,
                        $image->toJpeg(90)
                    );

                    $media = $this->eventImageRepository->create([
                        'event_id'     => $event->id,
                        'preview_url'  => $previewPath,
                        'full_url'     => $fullPath,
                        'width'        => $width,
                        'height'       => $height,
                        'size'         => $width * $height,
                        'price'        => $price,
                        'licence_type' => $plan,
                        'is_active'    => 1
                    ]);

                    $createdMedia[] = $media;
                } elseif (str_starts_with($mime, 'video/')) {
                    $path = $file->store('events/videos', 'public');
                    ProcessEventVideoJob::dispatch($event->id, $path);

                    $media = $this->eventImageRepository->create([
                        'event_id'  => $event->id,
                        'full_url'  => $path,
                        'is_active' => 1
                    ]);

                    $createdMedia[] = $media;
                }
            }

            Cache::tags(['events'])->flush();
        }

        return $this->success([
            'media' => $createdMedia
        ], '?? ????? ??????? ?????');
    }
}
