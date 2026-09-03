<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpecialCoverageRequest;
use App\Models\SpecialCoverageRequest;

class SpecialCoverageRequestController extends Controller
{
    use ApiResponse;

    public function store(StoreSpecialCoverageRequest $request)
    {
        $validated = $request->validated();

        $specialCoverageRequest = SpecialCoverageRequest::query()->create([
            'user_id' => $request->user()->id,
            'event_name' => $validated['event_name'],
            'event_description' => $validated['event_description'],
            'country_id' => $validated['country_id'],
            'city_id' => $validated['city_id'],
            'start_date' => $validated['start_date'],
            'event_type' => $validated['event_type'],
            'status' => SpecialCoverageRequest::STATUS_PENDING,
        ]);

        return $this->success(
            $specialCoverageRequest->load(['user', 'country.translation', 'city.translation']),
            'Your special coverage request has been submitted successfully.'
        );
    }
}
