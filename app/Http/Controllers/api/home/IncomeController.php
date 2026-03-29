<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\userResource;
use App\Mail\SubscriptionSuccessMail;
use App\Models\licenceType;
use Illuminate\Http\Request;
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
        $user->load('licenceType');
        $this->clearUserProfileCache($user->id);
        Mail::to($user->email)->send(
        new SubscriptionSuccessMail($user, $license)
    );
        return $this->success(new userResource($user),'Subscribed Successfully');
    }

    public function clearUserProfileCache($id)
    {
        $cacheKey = 'user_profile_' . $id;

        Cache::tags(['user_profile'])->forget($cacheKey);

        return $this->success([], 'User profile cache cleared for user ' . $id);
    }
}
