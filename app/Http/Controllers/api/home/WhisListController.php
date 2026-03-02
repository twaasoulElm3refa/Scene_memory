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
            $events=Events::whereIn('id', $whishlists)->paginate(5);
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
}
