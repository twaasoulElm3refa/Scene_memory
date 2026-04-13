<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\purchases;
use Illuminate\Support\Facades\Cache;

class PurchasesController extends Controller
{
    use ApiResponse;
    private $cacheTime = 60;
    public function index()
    {
        try {
            $page = (int) request()->get('page', 1);

            $cacheKey = 'purchases_all_page_' . $page;

            $purchases = Cache::tags(['purchases'])->remember(
                $cacheKey,
                now()->addMinutes($this->cacheTime),
                fn () => purchases::query()
                    ->select([
                        'id',
                        'user_id',
                        'amount',
                        'status',
                        'type',
                        'mail_sent',
                        'paid_at',
                        'created_at',
                    ])
                    ->with([
                        'user:id,name,email',
                    ])
                    ->latest('id')
                    ->paginate(10)
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
                fn () => purchases::query()
                    ->select([
                        'id',
                        'user_id',
                        'amount',
                        'status',
                        'type',
                        'mail_sent',
                        'paid_at',
                        'created_at',
                    ])
                    ->with([
                        'user:id,name,email',
                    ])
                    ->where('type', $type)
                    ->latest('id')
                    ->paginate(10)
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
                fn () => purchases::query()
                    ->select([
                        'id',
                        'user_id',
                        'amount',
                        'status',
                        'type',
                        'mail_sent',
                        'paid_at',
                        'created_at',
                    ])
                    ->with([
                        'user:id,name,email',
                    ])
                    ->where('status', $status)
                    ->latest('id')
                    ->paginate(10)
            );
            return $this->success($purchases, 'All Purchases');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $purchase = purchases::query()->findOrFail($id);
            return $this->success($purchase, 'Single purchase');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function update($id)
    {
        try {
            $purchase = purchases::query()->findOrFail($id);
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
            $purchase = purchases::query()->findOrFail($id);
            $purchase->delete();
            $this->clearCache();
            return $this->success($purchase, 'purchase deleted');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    private function clearCache()
    {
        Cache::tags(['purchases'])->flush();
    }
}
