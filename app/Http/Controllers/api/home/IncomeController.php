<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\userResource;
use App\Mail\SubscriptionSuccessMail;
use App\Models\licenceType;
use App\Models\Subscriptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class IncomeController extends Controller
{
    use ApiResponse;

    public function subscribe()
    {
        $id= request("id");
        $license = licenceType::where("id",$id)->first();
        $user= auth()->user();

        $user->update([
            "licence_type_id"=>$id,
        ]);

        Subscriptions::create([
            "user_id"=>$user->id,
            "licence_id"=>$id
        ]);

        if ($license->name == "basic") {
            $user->increment("points", 1000);
        }
        else if ($license->name == "professional") {
            $user->increment("points", 2500);
        }
        else if ($license->name == "premium") {
            $user->increment("points", 5000);
        }

        $this->clearUserProfileCache($user->id);
        Mail::to($user->email)->send(
        new SubscriptionSuccessMail($user, $license)
    );
        return $this->success(new userResource($user),'Subscribed Successfully');
    }

    public function clearUserProfileCache($id)
    {
        $cacheKey = 'user_profile_' . $id;

        Cache::tags(['user_profile', 'user_'.$id])->forget($cacheKey);
    }
}
