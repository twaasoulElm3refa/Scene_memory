<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\EventRequestCreate;
use App\Models\Events;
use Illuminate\Support\Facades\Cache;

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
            $PendingCounts= EventRequestCreate::where('status','pending')->count();
            $approvedCounts= EventRequestCreate::where('status','approved')->count();
            $rejectedCounts= EventRequestCreate::where('status','rejected')->count();
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
            $request = EventRequestCreate::find($id)->select('id','event_id','status')->first();
            $event=Events::with('city:id,name','sub_categorey:id,name','user:id,name')->where('id',$request->event_id)->first();
            return $this->success(['request' => $request, 'event' => $event], 'request');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

}
