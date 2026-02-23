<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Mail\ApproveMail;
use App\Mail\RejectMail;
use App\Models\EventRequestCreate;
use App\Models\Events;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class RequestController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function allPaginated()
    {
        try {
            $cacheKey = 'requests_page_'.request('page', 1);
            $requests = Cache::remember($cacheKey, $this->cacheTime, function () {
                return EventRequestCreate::with('events:id,title')->latest()->paginate(5);
            });
            $PendingCounts = EventRequestCreate::where('status', 'pending')->count();
            $approvedCounts = EventRequestCreate::where('status', 'approved')->count();
            $rejectedCounts = EventRequestCreate::where('status', 'rejected')->count();

            return $this->success(['requests' => $requests,
                'PendingCounts' => $PendingCounts,
                'approvedCounts' => $approvedCounts,
                'rejectedCounts' => $rejectedCounts],
                'All requests');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $request = EventRequestCreate::find($id)->select('id', 'event_id', 'status')->first();
            $event = Events::with('city:id,name', 'sub_categorey:id,name', 'user:id,name')->where('id', $request->event_id)->first();

            return $this->success(['request' => $request, 'event' => $event], 'request');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function approve($request_id)
    {
        try {
            $request = EventRequestCreate::find($request_id);
            $request->status = 'approved';
            $request->save();
            $event = Events::find($request->event_id);
            $event->is_active = 1;
            $event->save();
            $this->clearEventsCache();
            Mail::to($event->user->email)->send(new ApproveMail($event));

            return $this->success($request, 'Request Approved Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function decline(Request $req,$request_id)
    {
        try {
            $request = EventRequestCreate::find($request_id);
            $request->status = 'rejected';
            $request->save();
            $event = Events::find($request->event_id);
            $this->clearEventsCache();
            $reason=$req->reason;
            Mail::to($event->user->email)->send(new RejectMail($event,$reason));

            return $this->success($request, 'Request Declined Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $request = EventRequestCreate::find($id);
            $event = Events::find($request->event_id);
            $event->delete();
            $request->delete();
            $this->clearEventsCache();

            return $this->success($request, 'Request Deleted Successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    private function clearEventsCache()
    {
        $perPage = 8;

        for ($page = 1; $page <= 10; $page++) {
            Cache::forget("events_page_{$page}_per_{$perPage}");
        }
        for ($page = 1; $page <= 10; $page++) {
            Cache::forget("requests_page_{$page}");
        }
    }
}
