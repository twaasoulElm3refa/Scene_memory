<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\Controller;
use App\Http\Resources\MonthlyLeaderboardResource;
use App\Services\MonthlyLeaderboardService;
use Illuminate\Http\JsonResponse;

class LeaderboardController extends Controller
{
    public function __construct(
        private readonly MonthlyLeaderboardService $leaderboardService
    ) {}

    public function monthlyPreview(): JsonResponse
    {
        $period = now();
        $leaders = $this->leaderboardService->preview();

        return response()->json([
            'month' => $period->format('F'),
            'year' => $period->year,
            'users' => MonthlyLeaderboardResource::collection($leaders)->resolve(request()),
        ]);
    }
}
