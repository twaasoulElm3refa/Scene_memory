<?php

namespace App\Http\Controllers\api\userDshboard;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadMediaRequest;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Services\EventMediaIngest;
use App\Services\ImageAnalysisService;
use App\Support\EventAccess;
use Illuminate\Support\Facades\Cache;

class MediaRequestController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventImageRepositoryInterface $eventImageRepository
    ) {}

    public function all()
    {
        EventAccess::authorize($this->eventRepository->findById((int) request('id')), request()->user());
        $cacheKey = 'mediaRequest_'.request('id').'_'.request()->input('page', 1);
        $mediaRequest = Cache::remember($cacheKey, $this->cacheTime, function () {
            return $this->eventImageRepository->findActiveByEventIdPaginated((int) request('id'), 10);
        });

        return $this->success($mediaRequest, 'get media request successfully');
    }

    public function upload(UploadMediaRequest $request, ImageAnalysisService $imageAnalysisService)
    {
        $event = $this->eventRepository->findByIdOrFail((int) $request->id);
        EventAccess::authorize($event, $request->user());
        $createdMedia = app(EventMediaIngest::class)->upload($event, $request->file('url', []), true);

        return $this->success([
            'media' => $createdMedia,
        ], '?? ????? ??????? ?????');
    }
}
