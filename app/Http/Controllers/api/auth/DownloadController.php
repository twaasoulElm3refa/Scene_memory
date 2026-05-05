<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;
use App\Repositories\Contracts\Purchases\PurchaseRepositoryInterface;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PurchaseRepositoryInterface $purchaseRepository,
        private readonly EventImageRepositoryInterface $eventImageRepository
    ) {
    }

    public function downloads(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->error('Unauthenticated', 401);
            }
            $purchaseIds = $this->purchaseRepository->pluckIdsByUserId($user->id);
            if ($purchaseIds->isEmpty()) {
                return $this->success([], 'No downloads found');
            }
            $imageIds = $this->purchaseRepository->pluckImageIdsByPurchaseIds($purchaseIds);
            if ($imageIds->isEmpty()) {
                return $this->success([], 'No images found');
            }
            $media = $this->eventImageRepository->whereInIds($imageIds)->select(['id', 'full_url', 'price', 'height', 'width'])->latest()->get('');
            return $this->success($media, 'Downloaded successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
