<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Models\contactResponds;
use App\Models\contacts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ContactController extends Controller
{
    use ApiResponse;

    protected $cacheTime = 600;

    public function all()
    {
        $page = request()->get('page', 1);
        $perPage = 5;

        $cacheKey = "contacts:paginated:p{$page}:pp{$perPage}";

        $paginated = Cache::remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return contacts::with('contactResponds')
                ->latest()
                ->paginate($perPage);
        });
        $statsCacheKey = 'contacts:stats:quick';
        $stats = Cache::remember($statsCacheKey, now()->addMinutes(10), function () {
            $allContacts = contacts::query()
                ->select('id', 'created_at')
                ->withCount('contactResponds')
                ->with(['contactResponds' => function ($q) {
                    $q->select('contact_id', 'created_at')
                        ->orderBy('created_at')
                        ->limit(1);
                }])
                ->get();

            $total = $allContacts->count();
            $unread = $allContacts->where('contact_responds_count', 0)->count();
            $replied = $total - $unread;
            $responseTimes = $allContacts
                ->filter(function ($c) {
                    return $c->relationLoaded('contactResponds')
                          && $c->contactResponds
                          && $c->contactResponds->isNotEmpty();
                })
                ->map(function ($contact) {
                    $inquiry = $contact->created_at;
                    $firstReply = $contact->contactResponds->first()->created_at;

                    return $firstReply->diffInSeconds($inquiry) / 3600;
                });

            $avgHours = $responseTimes->isNotEmpty()
                ? round($responseTimes->avg(), 1)
                : 0;

            return [
                'total' => $total,
                'unread' => $unread,
                'new' => $unread,
                'replied' => $replied,
                'avg_response_time_hours' => abs($avgHours),
                'avg_response_time_formatted' => $this->formatResponseTime($avgHours),
            ];
        });

        return $this->success([
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'stats' => $stats,
        ]);
    }

    public function single()
    {
        return $this->success(contacts::with('contactResponds')->find(request('id')), 'Contact');
    }

    public function create(ContactRequest $request)
    {
        $data = $request->validated();
        $contact = contacts::create($data);
        $this->clearCache(1, 5);

        return $this->success($contact, 'Contact Created Successfully');
    }

    public function respond(Request $request)
    {
        $data = $request->all();
        $data['contact_id'] = request('id');
        $respond = contactResponds::create($data);
        $this->clearCache(1, 5);

        return $this->success($respond, 'Respond Created Successfully');
    }

    private function formatResponseTime(float $hours): string
    {
        if ($hours < 0.01) {
            return '—';
        }

        $h = floor($hours);
        $m = round(($hours - $h) * 60);

        if ($h == 0) {
            return $m.'m';
        }
        if ($m == 0) {
            return $h.'h';
        }

        return $h.'h '.$m.'m';
    }

    public function delete()
    {
        $contact = contacts::findOrFail(request('id'));
        $contact->delete();
        $this->clearCache(1, 5);

        return $this->success(null, 'Contact Deleted Successfully');
    }

    private function clearCache($page = 1, $perPage = 5)
    {
        Cache::forget("categories:paginated:p{$page}:pp{$perPage}");
        Cache::flush();
    }
}
