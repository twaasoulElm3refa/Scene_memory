<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SpecialCoverageRequest;
use Illuminate\Http\Request;

class SpecialCoverageRequestController extends Controller
{
    use ApiResponse;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
            'event_description' => ['required', 'string', 'max:5000'],
        ]);

        $specialCoverageRequest = SpecialCoverageRequest::query()->create([
            'user_id' => $request->user()->id,
            'event_name' => trim($validated['event_name']),
            'event_description' => trim($validated['event_description']),
            'status' => SpecialCoverageRequest::STATUS_PENDING,
        ]);

        return $this->success(
            $specialCoverageRequest->load('user'),
            'Your special coverage request has been submitted successfully.'
        );
    }
}
