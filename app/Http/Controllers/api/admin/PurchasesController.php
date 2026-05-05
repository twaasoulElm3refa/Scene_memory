<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Purchases\PurchaseRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class PurchasesController extends Controller
{
    use ApiResponse;
    private $cacheTime = 60;

    public function __construct(private readonly PurchaseRepositoryInterface $purchaseRepository)
    {
    }

    public function index()
    {
        try {
            $page = (int) request()->get('page', 1);

            $cacheKey = 'purchases_all_page_' . $page;

            $purchases = Cache::tags(['purchases'])->remember(
                $cacheKey,
                now()->addMinutes($this->cacheTime),
                fn () => $this->purchaseRepository->paginateAll(10)
            );
            return $this->success($purchases, 'All Purchases');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function filter($type)
    {
        try {
            $page = (int) request()->get('page', 1);
            $cacheKey = 'purchases_all_page_' . $page .'_'. $type;
            $purchases = Cache::tags(['purchases'])->remember(
                $cacheKey,
                now()->addMinutes($this->cacheTime),
                fn () => $this->purchaseRepository->paginateByType($type, 10)
            );
            return $this->success($purchases, 'All Purchases');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function status($status)
    {
        try {
            $page = (int) request()->get('page', 1);
            $cacheKey = 'purchases_all_page_' . $page .'_'. $status;
            $purchases = Cache::tags(['purchases'])->remember(
                $cacheKey,
                now()->addMinutes($this->cacheTime),
                fn () => $this->purchaseRepository->paginateByStatus($status, 10)
            );
            return $this->success($purchases, 'All Purchases');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $purchase = $this->purchaseRepository->findWithUserAndItemsOrFail((int) $id);
            return $this->success($purchase, 'Single purchase');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function update($id)
    {
        try {
            $purchase = $this->purchaseRepository->findOrFail((int) $id);
            $purchase->update(request()->all());
            $this->clearCache();
            return $this->success($purchase, 'purchase updated');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $purchase = $this->purchaseRepository->findOrFail((int) $id);
            $purchase->delete();
            $this->clearCache();
            return $this->success($purchase, 'purchase deleted');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

      public function count()
    {
        $cacheKey = 'purchases_count';

        $count = Cache::tags(['purchases'])->remember($cacheKey, $this->cacheTime, function () {
            return $this->purchaseRepository->count();
        });

        return $this->success($count, 'purchases count');
    }

    private function clearCache()
    {
        Cache::tags(['purchases'])->flush();
    }
}
