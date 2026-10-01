@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                {{ auth()->user()->isEmployee() ? 'My Checklists' : 'Process Execution Monitoring' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ auth()->user()->isEmployee() ? 'Review your scheduled operational checklists and record human confirmation.' : 'Monitor operational process checklist executions across departments in real time.' }}
            </p>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <form method="GET" action="{{ route('process-executions.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            {{-- Date Range Preset --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Date Range</label>
                <select name="date_range" class="w-full text-sm border-slate-200 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 border">
                    <option value="all" {{ request('date_range') == 'all' ? 'selected' : '' }}>All Time</option>
                    <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                    <option value="yesterday" {{ request('date_range') == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                    <option value="this_week" {{ request('date_range') == 'this_week' ? 'selected' : '' }}>This Week</option>
                    <option value="last_7_days" {{ request('date_range') == 'last_7_days' ? 'selected' : '' }}>Last 7 Days</option>
                    <option value="this_month" {{ request('date_range', 'this_month') == 'this_month' && !request()->filled('start_date') ? 'selected' : '' }}>This Month</option>
                    <option value="last_month" {{ request('date_range') == 'last_month' ? 'selected' : '' }}>Last Month</option>
                    <option value="custom" {{ request('date_range') == 'custom' || request()->filled('start_date') ? 'selected' : '' }}>Custom Range</option>
                </select>
            </div>

            {{-- Status Filter --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Status</label>
                <select name="status" class="w-full text-sm border-slate-200 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 border">
                    <option value="all" {{ request('status') == 'all' || !request()->filled('status') ? 'selected' : '' }}>All Statuses</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="missed" {{ request('status') == 'missed' ? 'selected' : '' }}>Missed</option>
                </select>
            </div>

            @if(! auth()->user()->isEmployee())
                {{-- Department Filter --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Department</label>
                    <select name="department_id" class="w-full text-sm border-slate-200 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 border">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Responsible Person Filter --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Responsible Person</label>
                    <select name="responsible_user_id" class="w-full text-sm border-slate-200 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 border">
                        <option value="">All People</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ request('responsible_user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Process Filter --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Process</label>
                <select name="process_id" class="w-full text-sm border-slate-200 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 border">
                    <option value="">All Processes</option>
                    @foreach($processes as $proc)
                        <option value="{{ $proc->id }}" {{ request('process_id') == $proc->id ? 'selected' : '' }}>{{ $proc->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2 pt-2 sm:col-span-2 lg:col-span-5">
                <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors">
                    Apply Filters
                </button>
                <a href="{{ route('process-executions.index') }}" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Execution List --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5">Checklist / Process</th>
                        <th class="px-6 py-3.5">Department</th>
                        <th class="px-6 py-3.5">Responsible</th>
                        <th class="px-6 py-3.5">Scheduled</th>
                        <th class="px-6 py-3.5">Progress</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($executions as $exec)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-6 py-4">
                                <a href="{{ route('process-executions.show', $exec) }}" class="font-semibold text-slate-900 hover:text-blue-600">
                                    {{ $exec->process->name }}
                                </a>
                                <div class="text-xs text-slate-400 font-mono mt-0.5">
                                    {{ $exec->process->code }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">
                                    {{ $exec->process->department->name }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-900">{{ $exec->process->responsibleUser->name }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-slate-800 font-medium">{{ $exec->scheduled_for->format('d M Y') }}</div>
                                <div class="text-xs text-slate-400">{{ $exec->scheduled_for->format('H:i') }} UTC</div>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $percentage = $exec->progressPercentage();
                                    $answered = $exec->answeredCount();
                                    $total = $exec->totalCount();
                                @endphp
                                <div class="w-32">
                                    <div class="flex justify-between text-xs font-medium text-slate-600 mb-1">
                                        <span>{{ $answered }}/{{ $total }}</span>
                                        <span>{{ $percentage }}%</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full {{ $exec->isCompleted() ? 'bg-emerald-500' : 'bg-blue-600' }}" style="width: {{ $percentage }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1 items-start">
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
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            Overdue
                                        </span>
                                    @endif

                                    @if($exec->isCompleted() && $exec->completedBy)
                                        <div class="text-[10px] text-slate-400">
                                            Confirmed by {{ $exec->completedBy->name }}
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('process-executions.show', $exec) }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-lg transition-colors">
                                    <span>{{ $exec->isCompleted() ? 'View' : 'Open' }}</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                <p class="font-medium text-slate-600">No process checklist executions found.</p>
                                <p class="text-xs mt-1">Try adjusting your filters or search date range.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($executions->hasPages())
            <div class="p-4 border-t border-slate-200 bg-slate-50">
                {{ $executions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
