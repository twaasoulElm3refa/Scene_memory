<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CommentReport;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function reports()
    {
        $cache = 'reports_all';
        $reports = Cache::remember($cache, $this->cacheTime, function () {
            return CommentReport::with('user', 'comment')->paginate(5);
        });

        return $this->success($reports, 'all reports');
    }

    public function delete()
    {
        $report = CommentReport::find(request('id'));
        $report->delete();
        $this->clearCache();

        return $this->success($report, 'report deleted successfully');
    }

    private function clearCache()
    {
        Cache::forget('reports_all');
        Cache::flush();
    }
}
