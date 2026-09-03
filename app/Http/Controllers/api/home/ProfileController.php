<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProfileService $service
    )
    {}


    public function activity(Request $request)
    {

        $user = auth()->user();


        return $this->success($this->service->activity($user->id));

    }

}