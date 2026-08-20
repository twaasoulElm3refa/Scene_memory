<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Repositories\Contracts\Requests\RequestRepositoryInterface;
use App\Services\EventModeration\EventRequestModerationService;
use App\Services\EventTagCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RequestController extends Controller
{
    use ApiResponse;

    private $cacheTime = 60 * 60;

    public function __construct(
        private readonly RequestRepositoryInterface $requestRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventRequestModerationService $moderationService,
        private readonly EventTagCacheService $cacheService,
    ) {}

    /**
     * عرض كل الـ requests مع pagination + counts
     */
    public function allPaginated()
    {
        try {
            $page = request('page', 1);
            $perPage = 5;
            $aiFlagged = $this->aiFlaggedFilter();
            $aiFlaggedCacheValue = $aiFlagged === null ? 'all' : (int) $aiFlagged;

            $cacheKey = "requests:page_{$page}:per_{$perPage}:ai_flagged_{$aiFlaggedCacheValue}";

            $requests = Cache::tags(['requests'])->remember($cacheKey, $this->cacheTime, function () use ($perPage, $aiFlagged) {
                return $this->requestRepository->paginatedWithEvent($perPage, $aiFlagged);
            });

            $countsKey = 'requests:counts';
            $counts = Cache::tags(['requests'])->remember($countsKey, $this->cacheTime, function () {
                return $this->requestRepository->counts();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'All requests',

                'data' => $requests,

                'counts' => [
                    'pending' => $counts['pending'],
                    'approved' => $counts['approved'],
                    'rejected' => $counts['rejected'],
                ],
            ]);

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    private function aiFlaggedFilter(): ?bool
    {
        if (! request()->has('ai_flagged')) {
            return null;
        }

        $value = request('ai_flagged');

        if ($value === '1' || $value === 1 || $value === true || $value === 'true') {
            return true;
        }

        if ($value === '0' || $value === 0 || $value === false || $value === 'false') {
            return false;
        }

        return null;
    }

    /**
     * عرض request واحد مع بيانات الحدث المرتبط
     */
    public function show($id)
    {
        try {
            $request = $this->requestRepository->find((int) $id);
            if (! $request) {
                return $this->error('Request not found', 404);
            }

            $event = $this->eventRepository->findWithAdminRelationsById((int) $request->event_id);

            return $this->success(['request' => $request, 'event' => $event], 'Request retrieved');

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * اعتماد request وتفعيل الحدث
     */
    public function approve($request_id)
    {
        try {
            $request = $this->moderationService->approveManually((int) $request_id);

            return $this->success($request, 'Request Approved Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * رفض request
     */
    public function decline(Request $req, $request_id)
    {
        try {
            $validated = $req->validate([
                'reason' => ['nullable', 'string', 'max:5000'],
            ]);
            $request = $this->moderationService->rejectManually(
                (int) $request_id,
                trim((string) ($validated['reason'] ?? ''))
            );

            return $this->success($request, 'Request Declined Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * حذف request والحدث المرتبط
     */
    public function destroy($id)
    {
        try {
            $request = $this->requestRepository->findOrFail((int) $id);
            $event = $this->eventRepository->findByIdOrFail((int) $request->event_id);

            $event->delete();
            $request->delete();

            $this->cacheService->invalidateModerationState((int) $event->id);

            return $this->success($request, 'Request Deleted Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
