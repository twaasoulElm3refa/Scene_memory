<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Mail\ApproveMail;
use App\Mail\RejectMail;
use App\Models\EventRequestCreate;
use App\Models\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class RequestController extends Controller
{
    use ApiResponse;

    private $cacheTime = 60 * 60;

    /**
     * عرض كل الـ requests مع pagination + counts
     */
    public function allPaginated()
    {
        try {
            $page = request('page', 1);
            $perPage = 5;

            $cacheKey = "requests:page_{$page}:per_{$perPage}";

            $requests = Cache::tags(['requests'])->remember($cacheKey, $this->cacheTime, function () use ($perPage) {
                return EventRequestCreate::with('events:id,title')->latest()->paginate($perPage);
            });

            $countsKey = 'requests:counts';
            $counts = Cache::tags(['requests'])->remember($countsKey, $this->cacheTime, function () {
                return [
                    'pending' => EventRequestCreate::where('status', 'pending')->count(),
                    'approved' => EventRequestCreate::where('status', 'approved')->count(),
                    'rejected' => EventRequestCreate::where('status', 'rejected')->count(),
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'All requests',

                'data' => $requests,

                'counts' => [
                    'pending' => $counts['pending'],
                    'approved' => $counts['approved'],
                    'rejected' => $counts['rejected'],
                ]
            ]);

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * عرض request واحد مع بيانات الحدث المرتبط
     */
    public function show($id)
    {
        try {
            $request = EventRequestCreate::select('id', 'event_id', 'status')->find($id);
            if (! $request) {
                return $this->error('Request not found', 404);
            }

            $event = Events::with('city:id,name', 'sub_categorey:id,name', 'user:id,name', 'firstImage', 'adminTranslation')
                ->find($request->event_id);

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
            $request = EventRequestCreate::findOrFail($request_id);
            $request->status = 'approved';
            $request->save();

            $event = Events::findOrFail($request->event_id);
            $event->is_active = 1;
            $event->save();

            $this->clearEventsCache();

            Mail::to($event->user->email)->send(new ApproveMail($event));

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
            $request = EventRequestCreate::findOrFail($request_id);
            $request->status = 'rejected';
            $request->save();

            $event = Events::findOrFail($request->event_id);

            $this->clearEventsCache();

            $reason = $req->reason ?? '';
            Mail::to($event->user->email)->send(new RejectMail($event, $reason));

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
            $request = EventRequestCreate::findOrFail($id);
            $event = Events::findOrFail($request->event_id);

            $event->delete();
            $request->delete();

            $this->clearEventsCache();

            return $this->success($request, 'Request Deleted Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * مسح كل الكاش المتعلق بالـ events و requests
     */
    private function clearEventsCache()
    {
        Cache::tags(['events'])->flush();
        Cache::tags(['requests'])->flush();
        $locales=['ar', 'en', 'fr', 'es', 'zh', 'de', 'ru', 'it', 'ja', 'fa', 'ur', 'hi'];
        foreach ($locales as $locale) {
           Cache::forget('daily_events_'.$locale);
        }
    }


}
