<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardStatsService;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AdminDashboardStatsService $statsService)
    {
    }

    public function stats()
    {
        try {
            $stats = $this->statsService->getStats();

            return $this->success($stats, 'dashboard stats fetched successfully');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }
}
