<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\eventsImges;
use App\Models\purchase_items;
use App\Models\purchases;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    use ApiResponse;
    public function downloads(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->error('Unauthenticated', 401);
            }
            $purchaseIds = purchases::where('user_id', $user->id)->pluck('id');
            if ($purchaseIds->isEmpty()) {
                return $this->success([], 'No downloads found');
            }
            $imageIds = purchase_items::whereIn('purchase_id', $purchaseIds)
                ->pluck('image_id');
            if ($imageIds->isEmpty()) {
                return $this->success([], 'No images found');
            }
            $media = eventsImges::whereIn('id', $imageIds)->select(['id','full_url','price','height','width'])->latest()->get('');
            return $this->success($media, 'Downloaded successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
