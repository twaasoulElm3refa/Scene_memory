<?php

namespace App\Http\Controllers\api\userDshboard;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Events;
use Illuminate\Support\Facades\Cache;

class UserDashboardController extends Controller
{
    use ApiResponse;

    private $cacheTime = 1 * 3600 * 24;

    public function myEvents()
    {
        $userId = auth()->id();
        $cacheKey = 'my_events_user_id_'.$userId;

        $events = Cache::remember($cacheKey, $this->cacheTime, function () use ($userId) {
            return Events::with('city:id,name', 'sub_categorey:id,name')
                ->where('user_id', $userId)
                ->withCount('images')
                ->orderBy('created_at', 'desc')
                ->select([
                    'id', 'user_id', 'title', 'slug',
                    'start_date', 'image',
                    'city_id', 'sub_categorey_id',
                ])
                ->get();
        });

        $totalImages = $events->sum('images_count');
        $count = Events::where('user_id', $userId)->count();

        return $this->success([
            'events' => $events,
            'count' => $count,
            'totalImages' => $totalImages,
        ], 'My events');
    }
}
