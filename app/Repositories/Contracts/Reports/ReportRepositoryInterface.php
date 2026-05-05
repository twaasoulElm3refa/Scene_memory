<?php

namespace App\Repositories\Contracts\Reports;

interface ReportRepositoryInterface
{
    public function paginated(int $perPage = 5);
    public function findOrFail(int $id);
}
