<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileTimelineRequest;
use App\Services\ProfileService;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProfileService $service
    ) {}

    public function activity(ProfileTimelineRequest $request)
    {
        return $this->success(
            $this->service->activity($request->user()->id, $request->validated()),
            'User activity fetched successfully.'
        );
    }
}
