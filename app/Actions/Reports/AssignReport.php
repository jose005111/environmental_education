<?php

namespace App\Actions\Reports;

use App\Models\Report;
use Illuminate\Support\Facades\DB;

final readonly class AssignReport
{
    public function execute(Report $report, int|string|null $assignedTo): Report
    {
        return DB::transaction(function () use ($report, $assignedTo): Report {
            $report = Report::query()
                ->whereKey($report->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $report->assigned_to = $assignedTo === null ? null : (int) $assignedTo;
            $report->save();

            return $report;
        });
    }

    public function __invoke(Report $report, int|string|null $assignedTo): Report
    {
        return $this->execute($report, $assignedTo);
    }
}
