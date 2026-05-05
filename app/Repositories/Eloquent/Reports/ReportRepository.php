<?php

namespace App\Repositories\Eloquent\Reports;

use App\Models\CommentReport;
use App\Repositories\Contracts\Reports\ReportRepositoryInterface;

class ReportRepository implements ReportRepositoryInterface
{
    public function paginated(int $perPage = 5)
    {
        return CommentReport::with('user', 'comment')->paginate($perPage);
    }

    public function findOrFail(int $id)
    {
        return CommentReport::findOrFail($id);
    }
}
