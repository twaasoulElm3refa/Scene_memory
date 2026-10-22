<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\userResource;
use App\Mail\SubscriptionSuccessMail;
use App\Models\Subscriptions;
use App\Models\User;
use App\Repositories\Contracts\Plans\PlanRepositoryInterface;
use App\Repositories\Contracts\Subscriptions\SubscriptionRepositoryInterface;
use App\Support\SecurityMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class IncomeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PlanRepositoryInterface $planRepository,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository
    ) {}

    public function subscribe()
    {
        $id = (int) request('id');
        $license = $this->planRepository->find($id);
        abort_unless($license, 404, SecurityMessage::get('notFound'));
        // There is no verified paid-plan fulfillment path in this controller.
        abort_if((float) $license->price > 0, 422, SecurityMessage::get('paymentRequired'));
        $user = auth()->user();
        DB::transaction(function () use ($user, $id, $license) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            if (Subscriptions::where('user_id', $user->id)->where('licence_id', $id)->exists()) {
                return;
            }
            $user->update(['licence_type_id' => $id]);
            $this->subscriptionRepository->create(['user_id' => $user->id, 'licence_id' => $id]);
            $points = match ($license->name) {
                'basic' => 1000, 'professional' => 2500, 'premium' => 5000, default => 0
            };
            if ($points) {
                $user->increment('points', $points);
            }
        }, 5);
        $user->refresh();

        $this->clearUserProfileCache($user->id);
        Mail::to($user->email)->send(
            new SubscriptionSuccessMail($user, $license)
        );

        return $this->success(new userResource($user), 'Subscribed Successfully');
    }

    public function clearUserProfileCache($id)
    {
        $cacheKey = 'user_profile_'.$id;

        Cache::tags(['user_profile', 'user_'.$id])->forget($cacheKey);
    }
}
