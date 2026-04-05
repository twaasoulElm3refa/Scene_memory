<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Events;
use Illuminate\Support\Facades\Cache;

class GateController extends Controller
{
    use ApiResponse;


    public function random()
    {
        $events = Cache::remember('random_events', 60*60, function () {
            return Events::with('translation','city.translation','sub_categorey.translation','firstImage:id,full_url,event_id')
                ->inRandomOrder()
                ->take(10)
                ->get();
        });

        return $this->success($events, 'Get Random Events');
    }

    public function country($country)
    {
        return $this->success($country, 'Get Random Events');
    }
}
