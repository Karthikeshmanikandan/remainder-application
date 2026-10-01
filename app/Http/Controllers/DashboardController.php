<?php

namespace App\Http\Controllers;

use App\Enums\ProcessStatus;
use App\Enums\ProjectStatus;
use App\Enums\RecurringTaskStatus;
use App\Enums\ReminderStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Process;
use App\Models\Project;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\TaskReminder;
use App\Models\TelegramEmployee;
use App\Services\OrganizationSeatService;
use App\Services\ProcessAccountabilityService;

class DashboardController extends Controller
{
    public function __construct(
        private ProcessAccountabilityService $accountabilityService,
        private OrganizationSeatService $seatService
    ) {}

    public function index()
    {
        $user = auth()->user();

        // Scope queries by role
        $projectQuery = Project::query();
        $taskQuery = Task::query();

        if ($user->role === UserRole::EMPLOYEE) {
            $taskQuery->where('assigned_to', $user->id);
        } elseif ($user->role === UserRole::MANAGER) {
            $taskQuery->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)->orWhere('assigned_to', $user->id);
            });
        }

        $totalProjects = (clone $projectQuery)->count();
        $activeProjects = (clone $projectQuery)->where('status', ProjectStatus::ACTIVE)->count();

        $totalTasks = (clone $taskQuery)->count();
        $pendingTasks = (clone $taskQuery)->where('status', TaskStatus::PENDING)->count();
        $inProgressTasks = (clone $taskQuery)->where('status', TaskStatus::IN_PROGRESS)->count();
        $completedTasks = (clone $taskQuery)->where('status', TaskStatus::COMPLETED)->count();

        $overdueTasks = (clone $taskQuery)
            ->where('due_date', '<', now())
            ->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value])
            ->count();

        $recentTasks = (clone $taskQuery)->with('project')->latest()->take(5)->get();
        $recentProjects = (clone $projectQuery)->latest()->take(5)->get();

        // Upcoming reminders for the authenticated user (own only — not others')
        $upcomingReminders = TaskReminder::with(['task.project'])
            ->where('user_id', $user->id)
            ->where('status', ReminderStatus::PENDING)
            ->orderBy('remind_at')
            ->take(5)
            ->get();

        // Recent unread notifications
        $recentNotifications = $user->unreadNotifications()->take(5)->get();

        // Upcoming recurring tasks
        $recurringQuery = RecurringTask::query()->where('status', RecurringTaskStatus::ACTIVE);
        if ($user->isEmployee()) {
            $recurringQuery->where('assigned_to', $user->id);
        }
        $upcomingRecurringTasks = $recurringQuery->orderBy('next_run_at')->take(5)->get();

        // Active processes for sidebar/compact list
        $processQuery = Process::query()
            ->where('status', ProcessStatus::ACTIVE)
            ->with(['department', 'responsibleUser']);

        if ($user->isEmployee()) {
            $processQuery->where('responsible_user_id', $user->id);
        }

        $todayProcesses = $processQuery->orderBy('reminder_time')->take(5)->get();

        // Process Accountability today summary
        $processStats = $this->accountabilityService->getDashboardSummary($user);

        // Admin organization metrics
        $org = $user->organization;
        $seatUsage = ($user->isAdmin() && $org) ? $this->seatService->getSeatUsage($org) : null;
        $telegramEmployeesCount = ($user->isAdmin() && $org) ? TelegramEmployee::where('organization_id', $org->id)->count() : 0;
        $departmentsCount = ($user->isAdmin() && $org) ? Department::where('organization_id', $org->id)->count() : 0;

        return view('dashboard', compact(
            'totalProjects', 'activeProjects', 'totalTasks',
            'pendingTasks', 'inProgressTasks', 'completedTasks',
            'overdueTasks', 'recentTasks', 'recentProjects',
            'upcomingReminders', 'recentNotifications', 'upcomingRecurringTasks',
            'todayProcesses', 'processStats',
            'seatUsage', 'telegramEmployeesCount', 'departmentsCount'
        ));
    }
}
