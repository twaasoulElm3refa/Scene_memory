<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Reports\ReportRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function __construct(private readonly ReportRepositoryInterface $reportRepository)
    {
    }

    /**
     * جلب جميع التقارير مع pagination
     */
    public function reports()
    {
        $cache = 'reports_all';
        $reports = Cache::tags(['reports'])->remember('reports_all', $this->cacheTime, function () {
            return $this->reportRepository->paginated(5);
        });

        return $this->success($reports, 'All reports fetched successfully');
    }

    /**
     * حذف تقرير
     */
    public function delete($id)
    {
        try {
            $report = $this->reportRepository->findOrFail((int) $id);
            $report->delete();
            Cache::tags(['reports'])->flush();


            return $this->success($report, 'Report deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Report not found or already deleted');
        }
    }

}
