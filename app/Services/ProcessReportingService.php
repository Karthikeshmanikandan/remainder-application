<?php

namespace App\Services;

use App\Enums\ProcessEscalationStatus;
use App\Enums\ProcessExecutionStatus;
use App\Enums\ProcessResponseType;
use App\Enums\ProcessStatus;
use App\Models\Department;
use App\Models\Process;
use App\Models\ProcessEscalationEvent;
use App\Models\ProcessExecution;
use App\Models\ProcessExecutionItem;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProcessReportingService
{
    /**
     * Resolve a date range preset or custom start/end into Carbon boundaries.
     *
     * @return array{start: Carbon, end: Carbon, label: string, preset: string}
     */
    public function resolvePeriod(?string $preset = 'this_month', ?string $customStart = null, ?string $customEnd = null): array
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

            case 'last_7_days':
                $start = now()->subDays(6)->startOfDay();
                $end = now()->endOfDay();
                $label = 'Last 7 Days ('.$start->format('d M').' - '.$end->format('d M Y').')';
                break;

            case 'this_week':
                $start = now()->startOfWeek();
                $end = now()->endOfWeek();
                $label = 'This Week ('.$start->format('d M').' - '.$end->format('d M Y').')';
                break;

            case 'last_week':
                $start = now()->subWeek()->startOfWeek();
                $end = now()->subWeek()->endOfWeek();
                $label = 'Last Week ('.$start->format('d M').' - '.$end->format('d M Y').')';
                break;

            case 'last_30_days':
                $start = now()->subDays(29)->startOfDay();
                $end = now()->endOfDay();
                $label = 'Last 30 Days ('.$start->format('d M').' - '.$end->format('d M Y').')';
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
     * Resolve the equivalent previous period for comparisons.
     *
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public function resolvePreviousPeriod(string $preset, Carbon $currentStart, Carbon $currentEnd): array
    {
        switch ($preset) {
            case 'today':
                $start = Carbon::yesterday()->startOfDay();
                $end = Carbon::yesterday()->endOfDay();
                $label = 'Yesterday';
                break;

            case 'yesterday':
                $start = Carbon::yesterday()->subDay()->startOfDay();
                $end = Carbon::yesterday()->subDay()->endOfDay();
                $label = 'Day Before Yesterday';
                break;

            case 'last_7_days':
                $start = (clone $currentStart)->subDays(7);
                $end = (clone $currentStart)->subSecond();
                $label = 'Prior 7 Days';
                break;

            case 'this_week':
                $start = now()->subWeek()->startOfWeek();
                $end = now()->subWeek()->endOfWeek();
                $label = 'Last Week';
                break;

            case 'last_week':
                $start = now()->subWeeks(2)->startOfWeek();
                $end = now()->subWeeks(2)->endOfWeek();
                $label = '2 Weeks Ago';
                break;

            case 'this_month':
                $start = now()->subMonth()->startOfMonth();
                $end = now()->subMonth()->endOfMonth();
                $label = 'Last Month';
                break;

            case 'last_month':
                $start = now()->subMonths(2)->startOfMonth();
                $end = now()->subMonths(2)->endOfMonth();
                $label = '2 Months Ago';
                break;

            case 'last_30_days':
                $start = (clone $currentStart)->subDays(30);
                $end = (clone $currentStart)->subSecond();
                $label = 'Prior 30 Days';
                break;

            case 'custom':
            default:
                $days = $currentStart->copy()->startOfDay()->diffInDays($currentEnd->copy()->startOfDay()) + 1;
                $start = $currentStart->copy()->subDays($days)->startOfDay();
                $end = $currentStart->copy()->subDay()->endOfDay();
                $label = 'Prior Period ('.$days.' days)';
                break;
        }

        return [
            'start' => $start,
            'end' => $end,
            'label' => $label,
        ];
    }

    /**
     * Calculate core operational metrics from a ProcessExecution query.
     *
     * @param  Builder  $query
     * @return array{scheduled: int, completed: int, pending: int, in_progress: int, overdue: int, missed: int, escalations: int, completion_rate: float}
     */
    public function calculateMetrics($query): array
    {
        $executions = (clone $query)->withCount('escalationEvents')->get(['id', 'status', 'scheduled_for']);

        $scheduled = $executions->count();
        $completed = 0;
        $pending = 0;
        $inProgress = 0;
        $overdue = 0;
        $missed = 0;
        $escalations = 0;

        foreach ($executions as $exec) {
            $escalations += $exec->escalation_events_count;

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

        $completionRate = $scheduled > 0 ? round(($completed / $scheduled) * 100, 1) : 0.0;

        return [
            'scheduled' => $scheduled,
            'completed' => $completed,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'overdue' => $overdue,
            'missed' => $missed,
            'escalations' => $escalations,
            'completion_rate' => $completionRate,
        ];
    }

    /**
     * Calculate period comparison deltas between current and previous metrics.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     * @return array<string, mixed>
     */
    public function calculatePeriodComparison(array $current, array $previous): array
    {
        return [
            'scheduled_diff' => $current['scheduled'] - $previous['scheduled'],
            'completed_diff' => $current['completed'] - $previous['completed'],
            'pending_diff' => $current['pending'] - $previous['pending'],
            'overdue_diff' => $current['overdue'] - $previous['overdue'],
            'missed_diff' => $current['missed'] - $previous['missed'],
            'escalations_diff' => $current['escalations'] - $previous['escalations'],
            'completion_rate_diff' => round($current['completion_rate'] - $previous['completion_rate'], 1),
        ];
    }

    /**
     * Get management overview report data for a period.
     *
     * @return array<string, mixed>
     */
    public function getOverviewReport(array $dateInfo, ?User $user = null): array
    {
        $currentQuery = ProcessExecution::query()->whereBetween('scheduled_for', [$dateInfo['start'], $dateInfo['end']]);

        if ($user && $user->isEmployee()) {
            $currentQuery->forUser($user->id);
        }

        $currentMetrics = $this->calculateMetrics($currentQuery);

        // Previous Period
        $prevPeriod = $this->resolvePreviousPeriod($dateInfo['preset'], $dateInfo['start'], $dateInfo['end']);
        $prevQuery = ProcessExecution::query()->whereBetween('scheduled_for', [$prevPeriod['start'], $prevPeriod['end']]);
        if ($user && $user->isEmployee()) {
            $prevQuery->forUser($user->id);
        }
        $prevMetrics = $this->calculateMetrics($prevQuery);
        $comparison = $this->calculatePeriodComparison($currentMetrics, $prevMetrics);

        // Daily Trends
        $trends = $this->getDailyExecutionTrends($dateInfo['start'], $dateInfo['end'], null, null, $user && $user->isEmployee() ? $user->id : null);

        // Department Workload Overview
        $departmentWorkload = $this->getDepartmentWorkload($dateInfo['start'], $dateInfo['end']);

        // Attention Required Items
        $attentionRequired = $this->getAttentionRequired($user);

        // Repeated Escalations
        $repeatedEscalations = $this->getRepeatedEscalations($dateInfo['start'], $dateInfo['end'], 2);

        return [
            'date_info' => $dateInfo,
            'prev_period' => $prevPeriod,
            'current' => $currentMetrics,
            'previous' => $prevMetrics,
            'comparison' => $comparison,
            'trends' => $trends,
            'department_workload' => $departmentWorkload,
            'attention_required' => $attentionRequired,
            'repeated_escalations' => $repeatedEscalations,
        ];
    }

    /**
     * Generate daily execution trends between two dates.
     *
     * @return array<int, array{date: string, label: string, scheduled: int, completed: int, pending: int, overdue: int, missed: int, escalations: int}>
     */
    public function getDailyExecutionTrends(Carbon $start, Carbon $end, ?int $departmentId = null, ?int $processId = null, ?int $responsibleUserId = null): array
    {
        $query = ProcessExecution::query()
            ->whereBetween('scheduled_for', [$start, $end])
            ->withCount('escalationEvents');

        if ($departmentId) {
            $query->whereHas('process', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($processId) {
            $query->where('process_id', $processId);
        }

        if ($responsibleUserId) {
            $query->whereHas('process', fn ($q) => $q->where('responsible_user_id', $responsibleUserId));
        }

        $executions = $query->get(['id', 'status', 'scheduled_for', 'scheduled_date']);

        $executionsByDate = $executions->groupBy(function ($exec) {
            return $exec->scheduled_for->format('Y-m-d');
        });

        $period = CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay());
        $trendData = [];

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dayExecs = $executionsByDate->get($dateStr, collect());

            $scheduled = $dayExecs->count();
            $completed = 0;
            $pending = 0;
            $overdue = 0;
            $missed = 0;
            $escalations = 0;

            foreach ($dayExecs as $exec) {
                $escalations += $exec->escalation_events_count ?? 0;

                if ($exec->status === ProcessExecutionStatus::COMPLETED) {
                    $completed++;
                } elseif ($exec->status === ProcessExecutionStatus::MISSED) {
                    $missed++;
                } else {
                    if ($exec->scheduled_for->isPast()) {
                        $overdue++;
                    }
                    if ($exec->status === ProcessExecutionStatus::PENDING || $exec->status === ProcessExecutionStatus::IN_PROGRESS) {
                        $pending++;
                    }
                }
            }

            $trendData[] = [
                'date' => $dateStr,
                'label' => $date->format('d M'),
                'scheduled' => $scheduled,
                'completed' => $completed,
                'pending' => $pending,
                'overdue' => $overdue,
                'missed' => $missed,
                'escalations' => $escalations,
            ];
        }

        return $trendData;
    }

    /**
     * Get department workload summary for a period.
     *
     * @return array<int, array{id: int, name: string, code: string, active_processes: int, scheduled: int, pending: int, overdue: int, completed: int, missed: int, escalations: int, completion_rate: float}>
     */
    public function getDepartmentWorkload(Carbon $start, Carbon $end): array
    {
        $departments = Department::withCount([
            'processes' => fn ($q) => $q->where('status', ProcessStatus::ACTIVE),
        ])->orderBy('name')->get();

        $executions = ProcessExecution::query()
            ->whereBetween('scheduled_for', [$start, $end])
            ->with(['process.department'])
            ->withCount('escalationEvents')
            ->get();

        $executionsByDept = $executions->groupBy(fn ($e) => $e->process->department_id ?? 0);

        $result = [];

        foreach ($departments as $dept) {
            $deptExecs = $executionsByDept->get($dept->id, collect());
            $scheduled = $deptExecs->count();
            $completed = 0;
            $pending = 0;
            $overdue = 0;
            $missed = 0;
            $escalations = 0;

            foreach ($deptExecs as $exec) {
                $escalations += $exec->escalation_events_count ?? 0;

                if ($exec->status === ProcessExecutionStatus::COMPLETED) {
                    $completed++;
                } elseif ($exec->status === ProcessExecutionStatus::MISSED) {
                    $missed++;
                } else {
                    if ($exec->scheduled_for->isPast()) {
                        $overdue++;
                    }
                    if ($exec->status === ProcessExecutionStatus::PENDING || $exec->status === ProcessExecutionStatus::IN_PROGRESS) {
                        $pending++;
                    }
                }
            }

            $completionRate = $scheduled > 0 ? round(($completed / $scheduled) * 100, 1) : 0.0;

            $result[] = [
                'id' => $dept->id,
                'name' => $dept->name,
                'code' => $dept->code,
                'active_processes' => $dept->processes_count,
                'scheduled' => $scheduled,
                'completed' => $completed,
                'pending' => $pending,
                'overdue' => $overdue,
                'missed' => $missed,
                'escalations' => $escalations,
                'completion_rate' => $completionRate,
            ];
        }

        return $result;
    }

    /**
     * Get department reports with sorting.
     *
     * @return array{departments: array<int, array<string, mixed>>, total_summary: array<string, mixed>}
     */
    public function getDepartmentReports(Carbon $start, Carbon $end, string $sortBy = 'name', string $direction = 'asc'): array
    {
        $workload = $this->getDepartmentWorkload($start, $end);

        // Sorting
        usort($workload, function ($a, $b) use ($sortBy, $direction) {
            $valA = $a[$sortBy] ?? $a['name'];
            $valB = $b[$sortBy] ?? $b['name'];

            if ($direction === 'desc') {
                return $valB <=> $valA;
            }

            return $valA <=> $valB;
        });

        $totalScheduled = array_sum(array_column($workload, 'scheduled'));
        $totalCompleted = array_sum(array_column($workload, 'completed'));
        $totalPending = array_sum(array_column($workload, 'pending'));
        $totalOverdue = array_sum(array_column($workload, 'overdue'));
        $totalMissed = array_sum(array_column($workload, 'missed'));
        $totalEscalations = array_sum(array_column($workload, 'escalations'));
        $totalCompletionRate = $totalScheduled > 0 ? round(($totalCompleted / $totalScheduled) * 100, 1) : 0.0;

        return [
            'departments' => $workload,
            'total_summary' => [
                'scheduled' => $totalScheduled,
                'completed' => $totalCompleted,
                'pending' => $totalPending,
                'overdue' => $totalOverdue,
                'missed' => $totalMissed,
                'escalations' => $totalEscalations,
                'completion_rate' => $totalCompletionRate,
            ],
        ];
    }

    /**
     * Get detailed report for a specific department.
     *
     * @return array<string, mixed>
     */
    public function getDepartmentDetailReport(Department $department, Carbon $start, Carbon $end): array
    {
        $execQuery = ProcessExecution::query()
            ->whereBetween('scheduled_for', [$start, $end])
            ->whereHas('process', fn ($q) => $q->where('department_id', $department->id));

        $metrics = $this->calculateMetrics($execQuery);

        $processes = Process::where('department_id', $department->id)
            ->with(['responsibleUser'])
            ->withCount(['escalationRules' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        $executions = (clone $execQuery)->with(['process', 'completedBy', 'escalationEvents'])
            ->latest('scheduled_for')
            ->take(15)
            ->get();

        $processStats = [];
        foreach ($processes as $proc) {
            $pQuery = ProcessExecution::query()
                ->where('process_id', $proc->id)
                ->whereBetween('scheduled_for', [$start, $end]);
            $processStats[] = array_merge(['process' => $proc], $this->calculateMetrics($pQuery));
        }

        $trends = $this->getDailyExecutionTrends($start, $end, $department->id);

        return [
            'department' => $department,
            'metrics' => $metrics,
            'processes' => $processStats,
            'trends' => $trends,
            'recent_executions' => $executions,
        ];
    }

    /**
     * Get paginated process reports with filter support.
     */
    public function getProcessReports(Carbon $start, Carbon $end, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Process::with(['department', 'responsibleUser', 'activeEscalationRules']);

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['responsible_user_id'])) {
            $query->where('responsible_user_id', $filters['responsible_user_id']);
        }

        if (! empty($filters['frequency'])) {
            $query->where('frequency', $filters['frequency']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $processes = $query->orderBy('name')->paginate($perPage)->withQueryString();

        // Attach period metrics to each process
        $processIds = $processes->pluck('id')->toArray();
        $executions = ProcessExecution::query()
            ->whereIn('process_id', $processIds)
            ->whereBetween('scheduled_for', [$start, $end])
            ->withCount('escalationEvents')
            ->get();

        $grouped = $executions->groupBy('process_id');

        $processes->getCollection()->transform(function ($process) use ($grouped) {
            $pExecs = $grouped->get($process->id, collect());
            $scheduled = $pExecs->count();
            $completed = 0;
            $pending = 0;
            $overdue = 0;
            $missed = 0;
            $escalations = 0;

            foreach ($pExecs as $exec) {
                $escalations += $exec->escalation_events_count ?? 0;
                if ($exec->status === ProcessExecutionStatus::COMPLETED) {
                    $completed++;
                } elseif ($exec->status === ProcessExecutionStatus::MISSED) {
                    $missed++;
                } else {
                    if ($exec->scheduled_for->isPast()) {
                        $overdue++;
                    }
                    if ($exec->status === ProcessExecutionStatus::PENDING || $exec->status === ProcessExecutionStatus::IN_PROGRESS) {
                        $pending++;
                    }
                }
            }

            $completionRate = $scheduled > 0 ? round(($completed / $scheduled) * 100, 1) : 0.0;

            $process->period_stats = [
                'scheduled' => $scheduled,
                'completed' => $completed,
                'pending' => $pending,
                'overdue' => $overdue,
                'missed' => $missed,
                'escalations' => $escalations,
                'completion_rate' => $completionRate,
            ];

            return $process;
        });

        return $processes;
    }

    /**
     * Get detailed report for a specific process.
     *
     * @return array<string, mixed>
     */
    public function getProcessDetailReport(Process $process, Carbon $start, Carbon $end, string $preset = 'this_month'): array
    {
        $execQuery = ProcessExecution::query()
            ->where('process_id', $process->id)
            ->whereBetween('scheduled_for', [$start, $end]);

        $metrics = $this->calculateMetrics($execQuery);

        // Previous period comparison
        $prevPeriod = $this->resolvePreviousPeriod($preset, $start, $end);
        $prevQuery = ProcessExecution::query()
            ->where('process_id', $process->id)
            ->whereBetween('scheduled_for', [$prevPeriod['start'], $prevPeriod['end']]);
        $prevMetrics = $this->calculateMetrics($prevQuery);
        $comparison = $this->calculatePeriodComparison($metrics, $prevMetrics);

        // Daily Trend
        $trends = $this->getDailyExecutionTrends($start, $end, null, $process->id);

        // Executions History in period
        $executions = (clone $execQuery)
            ->with(['completedBy', 'items', 'escalationEvents.acknowledgedBy', 'escalationEvents.rule.escalateToUser'])
            ->latest('scheduled_for')
            ->paginate(15)
            ->withQueryString();

        // Escalation History in period
        $escalations = ProcessEscalationEvent::query()
            ->whereHas('execution', fn ($q) => $q->where('process_id', $process->id))
            ->whereBetween('triggered_at', [$start, $end])
            ->with(['rule.escalateToUser', 'acknowledgedBy', 'execution'])
            ->latest('triggered_at')
            ->get();

        // Checklist Response Breakdown
        $responseBreakdown = $this->getChecklistResponseBreakdown($process, $start, $end);

        return [
            'process' => $process->load(['department', 'responsibleUser', 'escalationRules.escalateToUser']),
            'metrics' => $metrics,
            'prev_metrics' => $prevMetrics,
            'comparison' => $comparison,
            'trends' => $trends,
            'executions' => $executions,
            'escalations' => $escalations,
            'response_breakdown' => $responseBreakdown,
            'prev_period' => $prevPeriod,
        ];
    }

    /**
     * Calculate checklist response distribution for a process over a period.
     *
     * @return array<int, array{question: string, response_type: string, is_required: bool, total_answered: int, total_unanswered: int, distribution: array<string, int>}>
     */
    public function getChecklistResponseBreakdown(Process $process, Carbon $start, Carbon $end): array
    {
        $executionIds = ProcessExecution::where('process_id', $process->id)
            ->whereBetween('scheduled_for', [$start, $end])
            ->pluck('id');

        if ($executionIds->isEmpty()) {
            return [];
        }

        $items = ProcessExecutionItem::whereIn('process_execution_id', $executionIds)
            ->orderBy('sort_order')
            ->get();

        $groupedByQuestion = $items->groupBy('question_snapshot');
        $breakdown = [];

        foreach ($groupedByQuestion as $questionText => $questionItems) {
            $sample = $questionItems->first();
            $responseType = $sample->response_type;
            $isRequired = $sample->is_required;

            $totalAnswered = 0;
            $totalUnanswered = 0;
            $distribution = [];

            foreach ($questionItems as $item) {
                if ($item->isAnswered()) {
                    $totalAnswered++;
                    $val = strtoupper(trim((string) $item->response));

                    if ($responseType === ProcessResponseType::YES_NO || $responseType === ProcessResponseType::YES_NO_NA) {
                        $distribution[$val] = ($distribution[$val] ?? 0) + 1;
                    } else {
                        $distribution['Answered'] = ($distribution['Answered'] ?? 0) + 1;
                    }
                } else {
                    $totalUnanswered++;
                    $distribution['Unanswered'] = ($distribution['Unanswered'] ?? 0) + 1;
                }
            }

            $breakdown[] = [
                'question' => $questionText,
                'response_type' => $responseType->value,
                'is_required' => $isRequired,
                'total_answered' => $totalAnswered,
                'total_unanswered' => $totalUnanswered,
                'distribution' => $distribution,
            ];
        }

        return $breakdown;
    }

    /**
     * Get escalation reporting summary and paginated events.
     *
     * @return array<string, mixed>
     */
    public function getEscalationReports(Carbon $start, Carbon $end, array $filters = [], int $perPage = 15): array
    {
        $baseQuery = ProcessEscalationEvent::query()
            ->whereBetween('triggered_at', [$start, $end]);

        if (! empty($filters['department_id'])) {
            $baseQuery->whereHas('execution.process', fn ($q) => $q->where('department_id', $filters['department_id']));
        }

        if (! empty($filters['process_id'])) {
            $baseQuery->whereHas('execution', fn ($q) => $q->where('process_id', $filters['process_id']));
        }

        if (! empty($filters['responsible_user_id'])) {
            $baseQuery->whereHas('execution.process', fn ($q) => $q->where('responsible_user_id', $filters['responsible_user_id']));
        }

        if (! empty($filters['recipient_id'])) {
            $baseQuery->whereHas('rule', fn ($q) => $q->where('escalate_to_user_id', $filters['recipient_id']));
        }

        if (! empty($filters['level']) && $filters['level'] !== 'all') {
            $baseQuery->where('level', (int) $filters['level']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $baseQuery->where('status', $filters['status']);
        }

        $allEventsInPeriod = (clone $baseQuery)->get(['id', 'status', 'level', 'triggered_at']);

        $summary = [
            'total' => $allEventsInPeriod->count(),
            'triggered' => $allEventsInPeriod->where('status', ProcessEscalationStatus::TRIGGERED)->count(),
            'acknowledged' => $allEventsInPeriod->where('status', ProcessEscalationStatus::ACKNOWLEDGED)->count(),
            'resolved' => $allEventsInPeriod->where('status', ProcessEscalationStatus::RESOLVED)->count(),
            'cancelled' => $allEventsInPeriod->where('status', ProcessEscalationStatus::CANCELLED)->count(),
        ];

        // Daily Escalation Trend
        $escalationTrend = $this->getEscalationDailyTrend($start, $end, $filters['department_id'] ?? null, $filters['process_id'] ?? null);

        // Paginated Events Table
        $events = (clone $baseQuery)
            ->with([
                'execution.process.department',
                'execution.process.responsibleUser',
                'rule.escalateToUser',
                'acknowledgedBy',
            ])
            ->latest('triggered_at')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'summary' => $summary,
            'trend' => $escalationTrend,
            'events' => $events,
        ];
    }

    /**
     * Get daily escalation event trend.
     *
     * @return array<int, array{date: string, label: string, count: int}>
     */
    public function getEscalationDailyTrend(Carbon $start, Carbon $end, ?int $departmentId = null, ?int $processId = null): array
    {
        $query = ProcessEscalationEvent::query()->whereBetween('triggered_at', [$start, $end]);

        if ($departmentId) {
            $query->whereHas('execution.process', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($processId) {
            $query->whereHas('execution', fn ($q) => $q->where('process_id', $processId));
        }

        $events = $query->get(['id', 'triggered_at']);
        $eventsByDate = $events->groupBy(fn ($e) => $e->triggered_at->format('Y-m-d'));

        $period = CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay());
        $trend = [];

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $count = $eventsByDate->get($dateStr, collect())->count();

            $trend[] = [
                'date' => $dateStr,
                'label' => $date->format('d M'),
                'count' => $count,
            ];
        }

        return $trend;
    }

    /**
     * Get attention-required operational items.
     *
     * @return array{overdue_executions: Collection, unacknowledged_escalations: Collection, missed_executions: Collection, pending_near_due: Collection}
     */
    public function getAttentionRequired(?User $user = null): array
    {
        // 1. Overdue Executions
        $overdueQuery = ProcessExecution::query()
            ->whereIn('status', [ProcessExecutionStatus::PENDING, ProcessExecutionStatus::IN_PROGRESS])
            ->where('scheduled_for', '<', now())
            ->with(['process.department', 'process.responsibleUser']);

        if ($user && $user->isEmployee()) {
            $overdueQuery->forUser($user->id);
        }

        $overdueExecutions = $overdueQuery->orderBy('scheduled_for')->take(10)->get();

        // 2. Unacknowledged Escalations (Status = TRIGGERED)
        $unackQuery = ProcessEscalationEvent::query()
            ->where('status', ProcessEscalationStatus::TRIGGERED)
            ->with(['execution.process.department', 'execution.process.responsibleUser', 'rule.escalateToUser']);

        if ($user && $user->isEmployee()) {
            $unackQuery->whereHas('execution.process', fn ($q) => $q->where('responsible_user_id', $user->id));
        }

        $unacknowledgedEscalations = $unackQuery->latest('triggered_at')->take(10)->get();

        // 3. Missed Executions (recent 7 days)
        $missedQuery = ProcessExecution::query()
            ->where('status', ProcessExecutionStatus::MISSED)
            ->where('scheduled_for', '>=', now()->subDays(7))
            ->with(['process.department', 'process.responsibleUser']);

        if ($user && $user->isEmployee()) {
            $missedQuery->forUser($user->id);
        }

        $missedExecutions = $missedQuery->latest('scheduled_for')->take(10)->get();

        // 4. Pending Near Due (due today or within next 2 hours)
        $pendingQuery = ProcessExecution::query()
            ->where('status', ProcessExecutionStatus::PENDING)
            ->whereBetween('scheduled_for', [now(), now()->endOfDay()])
            ->with(['process.department', 'process.responsibleUser']);

        if ($user && $user->isEmployee()) {
            $pendingQuery->forUser($user->id);
        }

        $pendingNearDue = $pendingQuery->orderBy('scheduled_for')->take(10)->get();

        return [
            'overdue_executions' => $overdueExecutions,
            'unacknowledged_escalations' => $unacknowledgedEscalations,
            'missed_executions' => $missedExecutions,
            'pending_near_due' => $pendingNearDue,
        ];
    }

    /**
     * Get processes with repeated escalations within the selected period.
     *
     * @return array<int, array{process: Process, department: string, responsible: string, executions_count: int, escalations_count: int, overdue_count: int, missed_count: int}>
     */
    public function getRepeatedEscalations(Carbon $start, Carbon $end, int $threshold = 2): array
    {
        $events = ProcessEscalationEvent::query()
            ->whereBetween('triggered_at', [$start, $end])
            ->with(['execution.process.department', 'execution.process.responsibleUser'])
            ->get();

        $eventsByProcess = $events->groupBy(fn ($e) => $e->execution->process_id ?? 0);
        $repeated = [];

        foreach ($eventsByProcess as $processId => $procEvents) {
            if ($procEvents->count() >= $threshold && $processId > 0) {
                $firstEvent = $procEvents->first();
                $process = $firstEvent->execution->process;

                if (! $process) {
                    continue;
                }

                $execsInPeriod = ProcessExecution::where('process_id', $processId)
                    ->whereBetween('scheduled_for', [$start, $end])
                    ->get(['id', 'status', 'scheduled_for']);

                $overdue = 0;
                $missed = 0;

                foreach ($execsInPeriod as $ex) {
                    if ($ex->status === ProcessExecutionStatus::MISSED) {
                        $missed++;
                    } elseif ($ex->scheduled_for->isPast() && $ex->status !== ProcessExecutionStatus::COMPLETED) {
                        $overdue++;
                    }
                }

                $repeated[] = [
                    'process' => $process,
                    'department' => $process->department->name ?? 'General',
                    'responsible' => $process->responsibleUser->name ?? 'Unassigned',
                    'executions_count' => $execsInPeriod->count(),
                    'escalations_count' => $procEvents->count(),
                    'overdue_count' => $overdue,
                    'missed_count' => $missed,
                ];
            }
        }

        // Sort by escalations count descending
        usort($repeated, fn ($a, $b) => $b['escalations_count'] <=> $a['escalations_count']);

        return $repeated;
    }
}
