<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;

class WhisListController extends Controller
{
    use ApiResponse;

    public function me()
    {
        try {
            $whishlists = Wishlist::where('user_id', auth()->user()->id)->pluck('event_id');
            $events = Events::with(['city.translation', 'sub_categorey.translation','translation','firstImage:id,event_id,url'])
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

            return $this->success($events, 'My wishLists');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function add()
    {
        try {
            $wishlist = DB::transaction(function () {
                return Wishlist::firstOrCreate([
                    'user_id' => auth()->id(),
                    'event_id' => request('id'),
                ]);
            });

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
            return $this->success([], 'تم حذف العنصر من المفضلة بنجاح');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('العنصر غير موجود', 404);
        } catch (\Throwable $th) {
            return $this->error('حدث خطأ أثناء الحذف: '.$th->getMessage(), 500);
        }
    }
}
