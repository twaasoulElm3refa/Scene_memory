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

    /**
     * جلب جميع التقارير مع pagination
     */
    public function reports()
    {
        $cache = 'reports_all';
        $reports = Cache::remember($cache, $this->cacheTime, function () {
            return CommentReport::with('user', 'comment')->paginate(5);
        });

        return $this->success($reports, 'All reports fetched successfully');
    }

    /**
     * حذف تقرير
     */
    public function delete()
    {
        try {
            $report = CommentReport::findOrFail(request('id'));
            $report->delete();
            $this->clearCache();

            return $this->success($report, 'Report deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Report not found or already deleted');
        }
    }

    /**
     * مسح الكاش
     */
    private function clearCache()
    {
        // مسح الكاش الرئيسي
        Cache::forget('reports_all');
    }
}
