<?php

namespace App\Services;

use App\Enums\ProcessExecutionStatus;
use App\Models\ProcessExecution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessExecutionStatusService
{
    /**
     * Determine if an execution is currently overdue.
     */
    public function isOverdue(ProcessExecution $execution): bool
    {
        return $execution->isOverdue();
    }

    /**
     * Mark uncompleted executions that have exceeded their allowable execution window as MISSED.
     *
     * Rule: An execution that was scheduled prior to today (or past the cutoff)
     * and has not been confirmed completed transitions from PENDING / IN_PROGRESS to MISSED.
     *
     * @return int Number of executions marked as missed.
     */
    public function markMissedExecutions(?Carbon $cutoff = null): int
    {
        $cutoffTime = $cutoff ?? today()->startOfDay();

        return DB::transaction(function () use ($cutoffTime) {
            $overdueExecutions = ProcessExecution::whereIn('status', [
                ProcessExecutionStatus::PENDING,
                ProcessExecutionStatus::IN_PROGRESS,
                ProcessExecutionStatus::INCOMPLETE,
            ])
                ->where('scheduled_for', '<', $cutoffTime)
                ->get();

            $count = 0;
            foreach ($overdueExecutions as $execution) {
                $execution->update([
                    'status' => ProcessExecutionStatus::MISSED,
                ]);
                $count++;
            }

            return $count;
        });
    }
}
