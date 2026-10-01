<?php

namespace App\Services;

use App\Enums\ProcessEscalationStatus;
use App\Enums\ProcessExecutionStatus;
use App\Enums\ProcessStatus;
use App\Models\Department;
use App\Models\Process;
use App\Models\ProcessEscalationEvent;
use App\Models\ProcessExecution;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ProcessAccountabilityService
{
    /**
     * Resolve a date range preset or custom start/end into Carbon boundaries.
     *
     * @return array{start: Carbon, end: Carbon, label: string, preset: string}
     */
    public function resolveDateRange(?string $preset = 'this_month', ?string $customStart = null, ?string $customEnd = null): array
    {
        $preset = $preset ?: 'this_month';

        switch ($preset) {
            case 'today':
                $start = today()->startOfDay();
                $end = today()->endOfDay();
                $label = 'Today ('.$start->format('d M Y').')';
                break;

            case 'yesterday':
                $start = Carbon::yesterday()->startOfDay();
                $end = Carbon::yesterday()->endOfDay();
                $label = 'Yesterday ('.$start->format('d M Y').')';
                break;

            case 'this_week':
                $start = now()->startOfWeek();
                $end = now()->endOfWeek();
                $label = 'This Week ('.$start->format('d M').' - '.$end->format('d M Y').')';
                break;

            case 'last_7_days':
                $start = now()->subDays(6)->startOfDay();
                $end = now()->endOfDay();
                $label = 'Last 7 Days ('.$start->format('d M').' - '.$end->format('d M Y').')';
                break;

            case 'last_month':
                $start = now()->subMonth()->startOfMonth();
                $end = now()->subMonth()->endOfMonth();
                $label = 'Last Month ('.$start->format('M Y').')';
                break;

            case 'custom':
                if ($customStart && $customEnd) {
                    $parsedStart = Carbon::parse($customStart)->startOfDay();
                    $parsedEnd = Carbon::parse($customEnd)->endOfDay();
                    if ($parsedStart->lte($parsedEnd)) {
                        $start = $parsedStart;
                        $end = $parsedEnd;
                        $label = $start->format('d M Y').' - '.$end->format('d M Y');
                        break;
                    }
                }
                // Fallback to this month if custom range is invalid
                $preset = 'this_month';
                $start = now()->startOfMonth();
                $end = now()->endOfMonth();
                $label = 'This Month ('.$start->format('M Y').')';
                break;

            case 'this_month':
            default:
                $preset = 'this_month';
                $start = now()->startOfMonth();
                $end = now()->endOfMonth();
                $label = 'This Month ('.$start->format('M Y').')';
                break;
        }

        return [
            'start' => $start,
            'end' => $end,
            'label' => $label,
            'preset' => $preset,
        ];
    }

    /**
     * Calculate summary metrics from a base query of ProcessExecution.
     *
     * @param  Builder  $query
     * @return array{total: int, completed: int, pending: int, in_progress: int, overdue: int, missed: int, completion_rate: float}
     */
    public function calculateMetrics($query): array
    {
        $executions = (clone $query)->get(['id', 'status', 'scheduled_for']);

        $total = $executions->count();
        $completed = 0;
        $pending = 0;
        $inProgress = 0;
        $overdue = 0;
        $missed = 0;

        foreach ($executions as $exec) {
            if ($exec->status === ProcessExecutionStatus::COMPLETED) {
                $completed++;
            } elseif ($exec->status === ProcessExecutionStatus::MISSED) {
                $missed++;
            } else {
                if ($exec->scheduled_for->isPast()) {
                    $overdue++;
                }

                if ($exec->status === ProcessExecutionStatus::PENDING) {
                    $pending++;
                } elseif ($exec->status === ProcessExecutionStatus::IN_PROGRESS) {
                    $inProgress++;
                }
            }
        }

        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'overdue' => $overdue,
            'missed' => $missed,
            'completion_rate' => $completionRate,
        ];
    }

    /**
     * Get management dashboard summary for today.
     */
    public function getDashboardSummary(?User $user = null): array
    {
        $todayStart = today()->startOfDay();
        $todayEnd = today()->endOfDay();

        $query = ProcessExecution::query()->whereBetween('scheduled_for', [$todayStart, $todayEnd]);

        if ($user && $user->isEmployee()) {
            $query->forUser($user->id);
        }

        $metrics = $this->calculateMetrics($query);

        $todayExecutions = (clone $query)
            ->with(['process.department', 'process.responsibleUser', 'completedBy'])
            ->orderBy('scheduled_for')
            ->take(8)
            ->get();

        // Escalation metrics
        $escalationQuery = ProcessEscalationEvent::query();
        if ($user && $user->isEmployee()) {
            $escalationQuery->whereHas('execution.process', fn ($q) => $q->where('responsible_user_id', $user->id));
        }

        $activeEscalations = (clone $escalationQuery)
            ->whereIn('status', [ProcessEscalationStatus::TRIGGERED, ProcessEscalationStatus::ACKNOWLEDGED])
            ->count();

        $todayEscalations = (clone $escalationQuery)
            ->whereBetween('triggered_at', [$todayStart, $todayEnd])
            ->count();

        $unacknowledgedEscalations = (clone $escalationQuery)
            ->where('status', ProcessEscalationStatus::TRIGGERED)
            ->count();

        $resolvedTodayEscalations = (clone $escalationQuery)
            ->where('status', ProcessEscalationStatus::RESOLVED)
            ->whereBetween('updated_at', [$todayStart, $todayEnd])
            ->count();

        $recentEscalations = (clone $escalationQuery)
            ->with([
                'execution.process.department',
                'execution.process.responsibleUser',
                'rule.escalateToUser',
                'acknowledgedBy',
            ])
            ->latest('triggered_at')
            ->take(5)
            ->get();

        return array_merge($metrics, [
            'todayExecutions' => $todayExecutions,
            'active_escalations' => $activeEscalations,
            'today_escalations' => $todayEscalations,
            'unacknowledged_escalations' => $unacknowledgedEscalations,
            'resolved_today_escalations' => $resolvedTodayEscalations,
            'recent_escalations' => $recentEscalations,
        ]);
    }

    /**
     * Get department-level accountability statistics.
     */
    public function getDepartmentStatistics(?string $dateRange = 'this_month', ?string $customStart = null, ?string $customEnd = null): array
    {
        $dateInfo = $this->resolveDateRange($dateRange, $customStart, $customEnd);
        $departments = Department::withCount(['processes' => fn ($q) => $q->where('status', ProcessStatus::ACTIVE)])->get();

        $departmentStats = $departments->map(function ($dept) use ($dateInfo) {
            $query = ProcessExecution::forDepartment($dept->id)
                ->whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);

            $metrics = $this->calculateMetrics($query);

            return [
                'id' => $dept->id,
                'name' => $dept->name,
                'code' => $dept->code,
                'active_processes_count' => $dept->processes_count,
                'metrics' => $metrics,
            ];
        });

        // Global summary for all departments combined in this range
        $globalQuery = ProcessExecution::whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);
        $globalMetrics = $this->calculateMetrics($globalQuery);

        return [
            'departments' => $departmentStats,
            'summary' => $globalMetrics,
            'date_info' => $dateInfo,
        ];
    }

    /**
     * Get detailed accountability for a specific department.
     */
    public function getDepartmentDetail(Department $department, ?string $dateRange = 'this_month', ?string $customStart = null, ?string $customEnd = null): array
    {
        $dateInfo = $this->resolveDateRange($dateRange, $customStart, $customEnd);

        $execQuery = ProcessExecution::forDepartment($department->id)
            ->whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);

        $metrics = $this->calculateMetrics($execQuery);

        $processes = Process::where('department_id', $department->id)
            ->with(['responsibleUser'])
            ->get()
            ->map(function ($proc) use ($dateInfo) {
                $procQuery = ProcessExecution::where('process_id', $proc->id)
                    ->whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);

                return [
                    'process' => $proc,
                    'metrics' => $this->calculateMetrics($procQuery),
                ];
            });

        $responsibleUsers = User::whereIn('id', Process::where('department_id', $department->id)->pluck('responsible_user_id'))
            ->get()
            ->map(function ($u) use ($department, $dateInfo) {
                $userDeptQuery = ProcessExecution::forUser($u->id)
                    ->forDepartment($department->id)
                    ->whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);

                return [
                    'user' => $u,
                    'metrics' => $this->calculateMetrics($userDeptQuery),
                ];
            });

        $recentExecutions = (clone $execQuery)
            ->with(['process.responsibleUser', 'completedBy'])
            ->latest('scheduled_for')
            ->paginate(15);

        return [
            'department' => $department,
            'metrics' => $metrics,
            'processes' => $processes,
            'responsible_users' => $responsibleUsers,
            'executions' => $recentExecutions,
            'date_info' => $dateInfo,
        ];
    }

    /**
     * Get user-level (responsible person) accountability statistics.
     * Purely factual reporting; no rankings or leaderboards.
     */
    public function getUserStatistics(?string $dateRange = 'this_month', ?string $customStart = null, ?string $customEnd = null): array
    {
        $dateInfo = $this->resolveDateRange($dateRange, $customStart, $customEnd);

        // Get users who are assigned as responsible for at least one process
        $users = User::whereIn('id', Process::pluck('responsible_user_id'))->get();

        $userStats = $users->map(function ($u) use ($dateInfo) {
            $query = ProcessExecution::forUser($u->id)
                ->whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);

            $metrics = $this->calculateMetrics($query);

            $departments = Department::whereIn('id', Process::where('responsible_user_id', $u->id)->pluck('department_id'))->pluck('name');

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'departments' => $departments,
                'metrics' => $metrics,
            ];
        });

        $globalQuery = ProcessExecution::whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);
        $globalMetrics = $this->calculateMetrics($globalQuery);

        return [
            'users' => $userStats,
            'summary' => $globalMetrics,
            'date_info' => $dateInfo,
        ];
    }

    /**
     * Get detailed accountability for a specific user.
     */
    public function getUserDetail(User $user, ?string $dateRange = 'this_month', ?string $customStart = null, ?string $customEnd = null): array
    {
        $dateInfo = $this->resolveDateRange($dateRange, $customStart, $customEnd);

        $execQuery = ProcessExecution::forUser($user->id)
            ->whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);

        $metrics = $this->calculateMetrics($execQuery);

        $assignedProcesses = Process::where('responsible_user_id', $user->id)
            ->with('department')
            ->get()
            ->map(function ($proc) use ($dateInfo) {
                $procQuery = ProcessExecution::where('process_id', $proc->id)
                    ->whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);

                return [
                    'process' => $proc,
                    'metrics' => $this->calculateMetrics($procQuery),
                ];
            });

        $recentExecutions = (clone $execQuery)
            ->with(['process.department', 'completedBy'])
            ->latest('scheduled_for')
            ->paginate(15);

        return [
            'user' => $user,
            'metrics' => $metrics,
            'assigned_processes' => $assignedProcesses,
            'executions' => $recentExecutions,
            'date_info' => $dateInfo,
        ];
    }

    /**
     * Get process performance accountability statistics.
     */
    public function getProcessStatistics(?string $dateRange = 'this_month', ?string $customStart = null, ?string $customEnd = null, ?int $departmentId = null, ?int $userId = null): array
    {
        $dateInfo = $this->resolveDateRange($dateRange, $customStart, $customEnd);

        $processQuery = Process::with(['department', 'responsibleUser']);

        if ($departmentId) {
            $processQuery->where('department_id', $departmentId);
        }

        if ($userId) {
            $processQuery->where('responsible_user_id', $userId);
        }

        $processes = $processQuery->get()->map(function ($proc) use ($dateInfo) {
            $query = ProcessExecution::where('process_id', $proc->id)
                ->whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);

            $metrics = $this->calculateMetrics($query);

            return [
                'id' => $proc->id,
                'name' => $proc->name,
                'code' => $proc->code,
                'frequency' => $proc->frequency->value,
                'status' => $proc->status->value,
                'department_name' => $proc->department->name,
                'responsible_user_name' => $proc->responsibleUser->name,
                'metrics' => $metrics,
            ];
        });

        $globalQuery = ProcessExecution::whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);
        if ($departmentId) {
            $globalQuery->forDepartment($departmentId);
        }
        if ($userId) {
            $globalQuery->forUser($userId);
        }
        $globalMetrics = $this->calculateMetrics($globalQuery);

        return [
            'processes' => $processes,
            'summary' => $globalMetrics,
            'date_info' => $dateInfo,
        ];
    }

    /**
     * Get detailed execution performance for a specific process.
     */
    public function getProcessDetail(Process $process, ?string $dateRange = 'this_month', ?string $customStart = null, ?string $customEnd = null): array
    {
        $dateInfo = $this->resolveDateRange($dateRange, $customStart, $customEnd);

        $execQuery = ProcessExecution::where('process_id', $process->id)
            ->whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);

        $metrics = $this->calculateMetrics($execQuery);

        $executions = (clone $execQuery)
            ->with(['completedBy', 'items'])
            ->latest('scheduled_for')
            ->paginate(15);

        return [
            'process' => $process->load(['department', 'responsibleUser', 'items']),
            'metrics' => $metrics,
            'executions' => $executions,
            'date_info' => $dateInfo,
        ];
    }
}
