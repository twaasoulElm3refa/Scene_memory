<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventsRequest;
use App\Models\Events;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EventAdminController extends Controller
{
    use ApiResponse;

    public function create(EventsRequest $request): JsonResponse
    {
        $data = $request->validated();
        try {
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(5).'-'.time();
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $data['image'] = $image->store('events', 'public');
            }
            $data['user_id'] = auth()->user()->id;
            $event = Events::create($data);
            $this->clearEventsCache();

            return $this->success($event, 'Event Created Successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->all();

        try {
            $event = Events::findOrFail($request->id);

            $oldSlug = $event->slug;

            $data['slug'] = Str::slug($data['title']).'-'.Str::random(5).'-'.time();

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $data['image'] = $image->store('events', 'public');
            }
            Cache::forget("events_single_{$oldSlug}");

            $event->update($data);

            return $this->success($event, 'Event Updated Successfully');

        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function destroy(): JsonResponse
    {
        $oldSlug = request('id');
        $event = Events::where('slug', '=', request('id'))->first();
        $event->delete();
        $this->clearEventsCache($oldSlug);

        return $this->success($event,'Event Deleted Successfully');
    }

    private function clearEventsCache($slug = null)
    {
        $perPage = 8;

        for ($page = 1; $page <= 10; $page++) {
            Cache::forget("events_page_{$page}_per_{$perPage}");
        }
        Cache::forget("events_single_{$slug}");
        Cache::forget('events_count');
        Cache::forget('memories');
    }
}
