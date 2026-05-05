<?php

namespace App\Repositories\Eloquent\Subscriptions;

use App\Models\Subscriptions;
use App\Repositories\Contracts\Subscriptions\SubscriptionRepositoryInterface;

class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function create(array $data)
    {
        return Subscriptions::create($data);
    }
}
