<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class WhisListController extends Controller
{
    use ApiResponse;

    public function me()
    {
        try {
            $userId = auth()->id();

            $events = Cache::tags(['wishlist', 'wishlist_user_' . $userId])
                ->remember("wishlist_user_{$userId}", 60 * 10, function () use ($userId) {

                    $whishlists = Wishlist::where('user_id', $userId)->pluck('event_id');
                    return Events::with([
                            'city.translation',
                            'sub_categorey.translation',
                            'translation',
                            'firstImage:id,event_id,preview_url'
                        ])
                        ->whereIn('id', $whishlists)
                        ->select([
                            'id',
                            'user_id',
                            'city_id',
                            'title',
                            'description',
                            'start_date',
                            'end_date',
                            'time',
                            'sub_categorey_id',
                            'image',
                            'slug',
                            'created_at',
                        ])
                        ->paginate(5);
                });

            return $this->success($events, 'My wishLists');

        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function add()
    {
        try {
            $userId = auth()->id();

            $wishlist = DB::transaction(function () use ($userId) {
                return Wishlist::firstOrCreate([
                    'user_id' => $userId,
                    'event_id' => request('id'),
                ]);
            });

            // 🔥 Clear Cache
            Cache::tags(['wishlist', 'wishlist_user_' . $userId])->flush();

            return $this->success($wishlist, 'Wishlist processed successfully');

        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $event=Events::findOrFail($id);
            $wishlist = Wishlist::where('event_id', $event->id)->where('user_id', auth()->id())->firstOrFail();
            if (auth()->id() !== $wishlist->user_id) {
                return $this->unauthorized('غير مسموح لك بحذف هذه القائمة');
            }
            $wishlist->delete();
            Cache::tags(['wishlist', 'wishlist_user_' . $userId])->flush();
            return $this->success([], 'تم حذف العنصر من المفضلة بنجاح');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('العنصر غير موجود', 404);
        } catch (\Throwable $th) {
            return $this->error('حدث خطأ أثناء الحذف: '.$th->getMessage(), 500);
        }
    }
}
