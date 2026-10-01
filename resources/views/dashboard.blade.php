@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">
                Welcome back, {{ auth()->user()->name }}.
                @if(auth()->user()->isAdmin())
                    Viewing <strong>organisation-wide</strong> data.
                @elseif(auth()->user()->isManager())
                    Viewing tasks & processes you manage.
                @else
                    Viewing your assigned tasks & processes.
                @endif
            </p>
        </div>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.organization.show') }}" class="inline-flex items-center px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                Organization Settings &rarr;
            </a>
        @endif
    </div>

    @if(auth()->user()->isAdmin() && isset($seatUsage))
        {{-- Admin Organization Overview Card --}}
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-2xl p-5 mb-8 shadow-sm border border-slate-700">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-blue-400">Organization Administration</span>
                    <h2 class="text-xl font-bold text-white mt-0.5">{{ auth()->user()->organization->name ?? 'Organization' }}</h2>
                    <p class="text-xs text-slate-300 mt-1">
                        Active Seats: <span class="font-semibold text-white">{{ $seatUsage['active_seats'] }} / {{ $seatUsage['max_seats'] }}</span> ({{ $seatUsage['available_seats'] }} seats available)
                        &bull; Unlimited Telegram Staff: <span class="font-semibold text-white">{{ $telegramEmployeesCount }}</span>
                        &bull; Departments: <span class="font-semibold text-white">{{ $departmentsCount }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('users.index') }}" class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-xs font-semibold text-white transition">
                        Manage Web Seats ({{ $seatUsage['active_seats'] }}/{{ $seatUsage['max_seats'] }})
                    </a>
                    <a href="{{ route('admin.telegram-employees.index') }}" class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-xs font-semibold text-white transition">
                        Telegram Staff ({{ $telegramEmployeesCount }})
                    </a>
                    <a href="{{ route('admin.departments.index') }}" class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-xs font-semibold text-white transition">
                        Departments ({{ $departmentsCount }})
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Task Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h3 class="text-gray-500 text-xs font-semibold uppercase tracking-wide">Total Projects</h3>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalProjects }}</p>
            <p class="text-sm text-green-600 mt-1">{{ $activeProjects }} active</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h3 class="text-gray-500 text-xs font-semibold uppercase tracking-wide">Total Tasks</h3>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalTasks }}</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h3 class="text-gray-500 text-xs font-semibold uppercase tracking-wide">Task Breakdown</h3>
            <div class="mt-2 space-y-1">
                <p class="text-sm text-yellow-600">{{ $pendingTasks }} Pending</p>
                <p class="text-sm text-blue-600">{{ $inProgressTasks }} In Progress</p>
                <p class="text-sm text-green-600">{{ $completedTasks }} Completed</p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-red-200">
            <h3 class="text-red-500 text-xs font-semibold uppercase tracking-wide">Overdue Tasks</h3>
            <p class="text-3xl font-bold text-red-600 mt-1">{{ $overdueTasks }}</p>
            @if($overdueTasks > 0)
                <p class="text-sm text-red-400 mt-1">Requires attention</p>
            @else
                <p class="text-sm text-green-400 mt-1">None — looking good!</p>
            @endif
        </div>
    </div>

    {{-- Process Accountability Summary Cards for Today --}}
    <div class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold text-slate-800">Today's Process Accountability</h2>
            <a href="{{ route('process-executions.index', ['date_range' => 'today']) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                View All Today's Executions →
            </a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold uppercase text-slate-400">Today's Total</span>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ $processStats['total'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold uppercase text-emerald-600">Completed</span>
                <p class="text-2xl font-bold text-emerald-700 mt-1">{{ $processStats['completed'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold uppercase text-slate-500">Pending</span>
                <p class="text-2xl font-bold text-slate-700 mt-1">{{ $processStats['pending'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold uppercase text-amber-600">Overdue</span>
                <p class="text-2xl font-bold text-amber-700 mt-1">{{ $processStats['overdue'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold uppercase text-rose-600">Missed</span>
                <p class="text-2xl font-bold text-rose-700 mt-1">{{ $processStats['missed'] }}</p>
            </div>
        </div>
    </div>

    {{-- Escalation Metrics (for Admin and Manager) --}}
    @if(! auth()->user()->isEmployee())
        <div class="mb-8">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-bold text-slate-800">Escalation & Accountability Status</h2>
                    @if(($processStats['unacknowledged_escalations'] ?? 0) > 0)
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                            {{ $processStats['unacknowledged_escalations'] }} Pending Ack
                        </span>
                    @endif
                </div>
                <a href="{{ route('accountability.escalations.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                    View Escalation Monitoring →
                </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm">
                    <span class="text-xs font-bold uppercase text-amber-700">Active Escalations</span>
                    <p class="text-2xl font-bold text-amber-800 mt-1">{{ $processStats['active_escalations'] ?? 0 }}</p>
                    <p class="text-[11px] text-amber-600 mt-0.5">Triggered or in-review</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-rose-200 shadow-sm">
                    <span class="text-xs font-bold uppercase text-rose-700">Unacknowledged</span>
                    <p class="text-2xl font-bold text-rose-800 mt-1">{{ $processStats['unacknowledged_escalations'] ?? 0 }}</p>
                    <p class="text-[11px] text-rose-600 mt-0.5">Requires manager review</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-bold uppercase text-slate-500">Triggered Today</span>
                    <p class="text-2xl font-bold text-slate-900 mt-1">{{ $processStats['today_escalations'] ?? 0 }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Events since 00:00</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-emerald-200 shadow-sm">
                    <span class="text-xs font-bold uppercase text-emerald-700">Resolved Today</span>
                    <p class="text-2xl font-bold text-emerald-800 mt-1">{{ $processStats['resolved_today_escalations'] ?? 0 }}</p>
                    <p class="text-[11px] text-emerald-600 mt-0.5">Completed by responsible</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Today's Process Accountability Table --}}
    @if(! empty($processStats['todayExecutions']) && $processStats['todayExecutions']->isNotEmpty())
        <div class="bg-white shadow-sm rounded-xl border border-slate-200 overflow-hidden mb-8">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-800">Today's Process Executions</h2>
                <a href="{{ route('process-executions.index', ['date_range' => 'today']) }}" class="text-xs font-semibold text-blue-600 hover:underline">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3">Process</th>
                            <th class="px-5 py-3">Department</th>
                            <th class="px-5 py-3">Responsible</th>
                            <th class="px-5 py-3">Due Time</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach($processStats['todayExecutions'] as $exec)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3 font-medium text-slate-900">
                                    <a href="{{ route('process-executions.show', $exec) }}" class="hover:text-blue-600">
                                        {{ $exec->process->name }}
                                    </a>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">
                                        {{ $exec->process->department->name }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-slate-800">
                                    {{ $exec->process->responsibleUser->name }}
                                </td>
                                <td class="px-5 py-3 text-slate-600 font-mono text-xs">
                                    {{ $exec->scheduled_for->format('H:i') }} UTC
                                </td>
                                <td class="px-5 py-3">
                                    @php
                                        $statusBadges = [
                                            'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'in_progress' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            'pending' => 'bg-slate-50 text-slate-600 border-slate-200',
                                            'missed' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusBadges[$exec->status->value] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ ucfirst(str_replace('_', ' ', $exec->status->value)) }}
                                    </span>
                                    @if($exec->isOverdue())
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 ml-1">
                                            Overdue
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('process-executions.show', $exec) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                                        {{ $exec->isCompleted() ? 'View' : 'Open' }} →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Recent Tasks --}}
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-800">Recent Tasks</h2>
                <a href="{{ route('tasks.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
            </div>
            @if($recentTasks->isEmpty())
                <p class="text-sm text-gray-500 px-5 py-4">No tasks found.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($recentTasks as $task)
                        <li class="px-5 py-3 flex items-center justify-between">
                            <div>
                                <a href="{{ route('tasks.show', $task) }}" class="text-sm font-medium text-blue-600 hover:underline">
                                    {{ $task->title }}
                                </a>
                                <p class="text-xs text-gray-400">
                                    {{ $task->project->name ?? 'No Project' }} &bull; Due {{ $task->due_date?->format('Y-m-d') ?? 'None' }}
                                </p>
                            </div>
                            @php $tc = ['low'=>'bg-gray-100 text-gray-600','medium'=>'bg-blue-100 text-blue-700','high'=>'bg-orange-100 text-orange-700','urgent'=>'bg-red-100 text-red-700'][$task->priority->value] ?? 'bg-gray-100 text-gray-600'; @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $tc }}">{{ ucfirst($task->priority->value) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Recent Projects --}}
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-800">Recent Projects</h2>
                <a href="{{ route('projects.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
            </div>
            @if($recentProjects->isEmpty())
                <p class="text-sm text-gray-500 px-5 py-4">No projects found.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($recentProjects as $project)
                        <li class="px-5 py-3">
                            <div class="flex items-center justify-between">
                                <a href="{{ route('projects.show', $project) }}" class="text-sm font-medium text-blue-600 hover:underline">
                                    {{ $project->code }} — {{ $project->name }}
                                </a>
                                @php $sc = ['active'=>'bg-green-100 text-green-700','draft'=>'bg-gray-100 text-gray-600','on_hold'=>'bg-yellow-100 text-yellow-700','completed'=>'bg-blue-100 text-blue-700','cancelled'=>'bg-red-100 text-red-700'][$project->status->value] ?? 'bg-gray-100 text-gray-600'; @endphp
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $sc }}">{{ ucfirst(str_replace('_',' ',$project->status->value)) }}</span>
                            </div>
                            @php $progress = $project->progress(); @endphp
                            <div class="mt-1">
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $progress['percentage'] }}%"></div>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $progress['completed'] }}/{{ $progress['total'] }} tasks complete</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Upcoming Reminders --}}
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-800">Upcoming Reminders</h2>
                <a href="{{ route('reminders.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
            </div>
            @if($upcomingReminders->isEmpty())
                <p class="text-sm text-gray-500 px-5 py-4">No upcoming reminders.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($upcomingReminders as $reminder)
                        <li class="px-5 py-3">
                            <a href="{{ route('tasks.show', $reminder->task) }}" class="text-sm font-medium text-blue-600 hover:underline">
                                {{ $reminder->task->title }}
                            </a>
                            <p class="text-xs text-gray-400">
                                {{ $reminder->task->project->name ?? '—' }} &bull;
                                <strong>{{ $reminder->remind_at->format('Y-m-d H:i') }}</strong>
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Recent Notifications --}}
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-800">Recent Notifications</h2>
                <a href="{{ route('notifications.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
            </div>
            @if($recentNotifications->isEmpty())
                <p class="text-sm text-gray-500 px-5 py-4">No unread notifications.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($recentNotifications as $notification)
                        <li class="px-5 py-3 flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $notification->data['message'] ?? 'Task reminder.' }}</p>
                                <p class="text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                            </div>
                            <span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0 mt-1.5"></span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Upcoming Recurring Tasks --}}
    <div class="mb-6">
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-800">Upcoming Recurring Tasks</h2>
                <a href="{{ route('recurring-tasks.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
            </div>
            @if($upcomingRecurringTasks->isEmpty())
                <p class="text-sm text-gray-500 px-5 py-4">No upcoming recurring tasks.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($upcomingRecurringTasks as $task)
                        <li class="px-5 py-3 flex items-center justify-between">
                            <div>
                                <a href="{{ route('recurring-tasks.show', $task) }}" class="text-sm font-medium text-blue-600 hover:underline">{{ $task->title }}</a>
                                <p class="text-xs text-gray-500">Next Run: {{ $task->next_run_at?->format('Y-m-d H:i') }} | Frequency: Every {{ $task->interval }} {{ $task->frequency->value }}</p>
                            </div>
                            <span class="text-xs text-gray-500">{{ $task->assignee->name ?? 'Unassigned' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection