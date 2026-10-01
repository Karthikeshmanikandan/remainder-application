@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Management Reports & Business Intelligence</h1>
            <p class="text-sm text-slate-500 mt-1">Operational activity, completion trends, department workload, and process reliability.</p>
        </div>
    </div>

    {{-- Filter Bar --}}
    @include('reports.components.filter-bar', ['action' => route('reports.index'), 'dateInfo' => $date_info])

    {{-- Top-Level KPI Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-400">Scheduled</span>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $current['scheduled'] }}</div>
            <div class="text-[11px] text-slate-400 mt-1">
                @if($comparison['scheduled_diff'] >= 0) +{{ $comparison['scheduled_diff'] }} @else {{ $comparison['scheduled_diff'] }} @endif vs prev
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-emerald-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-emerald-700">Completed</span>
            <div class="text-2xl font-bold text-emerald-700 mt-1">{{ $current['completed'] }}</div>
            <div class="text-[11px] text-emerald-600 mt-1">
                @if($comparison['completed_diff'] >= 0) +{{ $comparison['completed_diff'] }} @else {{ $comparison['completed_diff'] }} @endif vs prev
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-500">Pending</span>
            <div class="text-2xl font-bold text-slate-700 mt-1">{{ $current['pending'] }}</div>
            <div class="text-[11px] text-slate-400 mt-1">
                @if($comparison['pending_diff'] >= 0) +{{ $comparison['pending_diff'] }} @else {{ $comparison['pending_diff'] }} @endif vs prev
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-amber-700">Overdue</span>
            <div class="text-2xl font-bold text-amber-700 mt-1">{{ $current['overdue'] }}</div>
            <div class="text-[11px] text-amber-600 mt-1">
                @if($comparison['overdue_diff'] >= 0) +{{ $comparison['overdue_diff'] }} @else {{ $comparison['overdue_diff'] }} @endif vs prev
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-rose-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-rose-700">Missed</span>
            <div class="text-2xl font-bold text-rose-700 mt-1">{{ $current['missed'] }}</div>
            <div class="text-[11px] text-rose-600 mt-1">
                @if($comparison['missed_diff'] >= 0) +{{ $comparison['missed_diff'] }} @else {{ $comparison['missed_diff'] }} @endif vs prev
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-orange-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-orange-700">Escalations</span>
            <div class="text-2xl font-bold text-orange-700 mt-1">{{ $current['escalations'] }}</div>
            <div class="text-[11px] text-orange-600 mt-1">
                @if($comparison['escalations_diff'] >= 0) +{{ $comparison['escalations_diff'] }} @else {{ $comparison['escalations_diff'] }} @endif vs prev
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-blue-200 shadow-sm col-span-2 sm:col-span-1">
            <span class="text-xs font-bold uppercase text-blue-700">Completion %</span>
            <div class="text-2xl font-bold text-blue-700 mt-1">{{ $current['completion_rate'] }}%</div>
            <div class="text-[11px] text-blue-600 mt-1">
                Prev: {{ $previous['completion_rate'] }}% ({{ $comparison['completion_rate_diff'] > 0 ? '+' : '' }}{{ $comparison['completion_rate_diff'] }}%)
            </div>
        </div>
    </div>

    {{-- Period Comparison Factual Summary --}}
    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <h2 class="text-base font-bold text-slate-900">Period Comparison</h2>
            <span class="text-xs text-slate-500 font-medium">{{ $date_info['label'] }} vs {{ $prev_period['label'] }}</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 text-center">
            <div class="p-3 bg-slate-50 rounded-lg">
                <span class="text-[11px] font-bold text-slate-400 uppercase">Scheduled</span>
                <div class="text-lg font-bold text-slate-800 mt-1">{{ $current['scheduled'] }} <span class="text-xs text-slate-400 font-normal">vs {{ $previous['scheduled'] }}</span></div>
            </div>
            <div class="p-3 bg-emerald-50/60 rounded-lg">
                <span class="text-[11px] font-bold text-emerald-700 uppercase">Completed</span>
                <div class="text-lg font-bold text-emerald-800 mt-1">{{ $current['completed'] }} <span class="text-xs text-emerald-600 font-normal">vs {{ $previous['completed'] }}</span></div>
            </div>
            <div class="p-3 bg-slate-50 rounded-lg">
                <span class="text-[11px] font-bold text-slate-500 uppercase">Pending</span>
                <div class="text-lg font-bold text-slate-800 mt-1">{{ $current['pending'] }} <span class="text-xs text-slate-400 font-normal">vs {{ $previous['pending'] }}</span></div>
            </div>
            <div class="p-3 bg-amber-50/60 rounded-lg">
                <span class="text-[11px] font-bold text-amber-700 uppercase">Overdue</span>
                <div class="text-lg font-bold text-amber-800 mt-1">{{ $current['overdue'] }} <span class="text-xs text-amber-600 font-normal">vs {{ $previous['overdue'] }}</span></div>
            </div>
            <div class="p-3 bg-rose-50/60 rounded-lg">
                <span class="text-[11px] font-bold text-rose-700 uppercase">Missed</span>
                <div class="text-lg font-bold text-rose-800 mt-1">{{ $current['missed'] }} <span class="text-xs text-rose-600 font-normal">vs {{ $previous['missed'] }}</span></div>
            </div>
            <div class="p-3 bg-orange-50/60 rounded-lg">
                <span class="text-[11px] font-bold text-orange-700 uppercase">Escalations</span>
                <div class="text-lg font-bold text-orange-800 mt-1">{{ $current['escalations'] }} <span class="text-xs text-orange-600 font-normal">vs {{ $previous['escalations'] }}</span></div>
            </div>
        </div>
    </div>

    {{-- Execution Trends Visualization --}}
    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">Daily Execution Trends</h2>
                <p class="text-xs text-slate-400 mt-0.5">Recorded executions per day across all processes</p>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 bg-emerald-500 rounded-sm"></span> Completed</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 bg-slate-400 rounded-sm"></span> Pending</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 bg-amber-500 rounded-sm"></span> Overdue</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 bg-rose-500 rounded-sm"></span> Missed</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 bg-orange-500 rounded-sm"></span> Escalated</span>
            </div>
        </div>

        @if(empty($trends) || collect($trends)->sum('scheduled') === 0)
            <div class="p-8 text-center text-slate-400 text-sm">
                No process executions were recorded for this period.
            </div>
        @else
            <div class="overflow-x-auto">
                <div class="min-w-[600px] h-64 flex items-end gap-2 pt-8 pb-4 border-b border-slate-100">
                    @php
                        $maxDayCount = max(collect($trends)->max('scheduled'), collect($trends)->max('escalations'), 1);
                    @endphp
                    @foreach($trends as $day)
                        <div class="flex-1 flex flex-col items-center gap-1 group relative h-full justify-end">
                            {{-- Tooltip --}}
                            <div class="absolute bottom-full mb-2 hidden group-hover:flex flex-col bg-slate-900 text-white text-[11px] rounded-lg p-2 shadow-lg z-30 whitespace-nowrap pointer-events-none">
                                <span class="font-bold">{{ $day['label'] }} ({{ $day['date'] }})</span>
                                <span>Scheduled: {{ $day['scheduled'] }}</span>
                                <span class="text-emerald-300">Completed: {{ $day['completed'] }}</span>
                                <span class="text-slate-300">Pending: {{ $day['pending'] }}</span>
                                <span class="text-amber-300">Overdue: {{ $day['overdue'] }}</span>
                                <span class="text-rose-300">Missed: {{ $day['missed'] }}</span>
                                <span class="text-orange-300">Escalations: {{ $day['escalations'] }}</span>
                            </div>

                            {{-- Stacked Bar --}}
                            <div class="w-full max-w-[28px] bg-slate-100 rounded-t flex flex-col-reverse overflow-hidden" style="height: {{ ($day['scheduled'] / $maxDayCount) * 100 }}%">
                                @if($day['completed'] > 0)
                                    <div class="bg-emerald-500 w-full" style="height: {{ ($day['completed'] / max($day['scheduled'], 1)) * 100 }}%"></div>
                                @endif
                                @if($day['overdue'] > 0)
                                    <div class="bg-amber-500 w-full" style="height: {{ ($day['overdue'] / max($day['scheduled'], 1)) * 100 }}%"></div>
                                @endif
                                @if($day['missed'] > 0)
                                    <div class="bg-rose-500 w-full" style="height: {{ ($day['missed'] / max($day['scheduled'], 1)) * 100 }}%"></div>
                                @endif
                                @if($day['pending'] > 0)
                                    <div class="bg-slate-400 w-full" style="height: {{ ($day['pending'] / max($day['scheduled'], 1)) * 100 }}%"></div>
                                @endif
                            </div>

                            <span class="text-[10px] text-slate-500 font-mono mt-1">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Attention Required Section --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-rose-50/30">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                <h2 class="text-base font-bold text-slate-900">Requires Attention</h2>
            </div>
            <span class="text-xs text-slate-500 font-medium">Unresolved & Overdue Items</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-slate-100">
            {{-- Overdue Executions --}}
            <div class="p-5 space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-amber-700 flex items-center justify-between">
                    <span>Overdue Executions ({{ $attention_required['overdue_executions']->count() }})</span>
                </h3>
                <div class="space-y-2">
                    @forelse($attention_required['overdue_executions'] as $overdueExec)
                        <div class="p-3 bg-amber-50/50 rounded-lg border border-amber-200 flex items-center justify-between gap-3 text-xs">
                            <div>
                                <a href="{{ route('process-executions.show', $overdueExec) }}" class="font-bold text-slate-900 hover:text-blue-600">
                                    {{ $overdueExec->process->name }}
                                </a>
                                <div class="text-slate-500 mt-0.5">
                                    {{ $overdueExec->process->department->name }} &bull; Responsible: {{ $overdueExec->process->responsibleUser->name }}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-0.5 rounded font-bold bg-amber-100 text-amber-800">
                                    Due {{ $overdueExec->scheduled_for->format('d M, H:i') }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 italic py-2">No overdue executions at this time.</p>
                    @endforelse
                </div>
            </div>

            {{-- Unacknowledged Escalations --}}
            <div class="p-5 space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-rose-700 flex items-center justify-between">
                    <span>Unacknowledged Escalations ({{ $attention_required['unacknowledged_escalations']->count() }})</span>
                </h3>
                <div class="space-y-2">
                    @forelse($attention_required['unacknowledged_escalations'] as $esc)
                        <div class="p-3 bg-rose-50/50 rounded-lg border border-rose-200 flex items-center justify-between gap-3 text-xs">
                            <div>
                                <a href="{{ route('process-executions.show', $esc->execution) }}" class="font-bold text-slate-900 hover:text-blue-600">
                                    {{ $esc->execution->process->name }}
                                </a>
                                <div class="text-slate-500 mt-0.5">
                                    Escalated to: <strong>{{ $esc->rule?->escalateToUser?->name ?? 'Manager' }}</strong> &bull; Level {{ $esc->level }}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-0.5 rounded font-bold bg-rose-100 text-rose-800">
                                    Triggered {{ $esc->triggered_at?->diffForHumans() }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 italic py-2">No unacknowledged escalations at this time.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Repeated Escalations & Department Workload --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Processes with Repeated Escalations --}}
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Processes with Repeated Escalations</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Processes with 2 or more escalation events in this period</p>
                </div>
                <span class="text-xs font-semibold px-2 py-0.5 bg-orange-50 text-orange-700 rounded border border-orange-200">
                    {{ count($repeated_escalations) }} Processes
                </span>
            </div>

            <div class="space-y-2.5">
                @forelse($repeated_escalations as $rep)
                    <div class="p-3 rounded-lg border border-slate-100 hover:border-slate-300 transition-colors flex items-center justify-between gap-4 text-xs">
                        <div>
                            <a href="{{ route('reports.processes.show', $rep['process']) }}" class="font-bold text-slate-900 hover:text-blue-600 text-sm">
                                {{ $rep['process']->name }}
                            </a>
                            <div class="text-slate-500 mt-0.5">
                                {{ $rep['department'] }} &bull; Responsible: {{ $rep['responsible'] }}
                            </div>
                        </div>
                        <div class="text-right space-y-0.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded font-bold bg-orange-100 text-orange-800">
                                {{ $rep['escalations_count'] }} Escalations
                            </span>
                            <div class="text-[11px] text-slate-400">
                                {{ $rep['executions_count'] }} Executions ({{ $rep['overdue_count'] }} overdue, {{ $rep['missed_count'] }} missed)
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 italic py-4 text-center">No processes experienced repeated escalations in this period.</p>
                @endforelse
            </div>
        </div>

        {{-- Department Workload Summary --}}
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Department Workload Summary</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Process volume and completion distribution</p>
                </div>
                <a href="{{ route('reports.departments.index') }}" class="text-xs font-semibold text-blue-600 hover:underline">
                    View Full Table →
                </a>
            </div>

            <div class="space-y-3">
                @forelse($department_workload as $dept)
                    <div class="p-3 rounded-lg border border-slate-100 hover:bg-slate-50 transition-colors flex items-center justify-between gap-4 text-xs">
                        <div>
                            <a href="{{ route('reports.departments.show', $dept['id']) }}" class="font-bold text-slate-900 hover:text-blue-600 text-sm">
                                {{ $dept['name'] }}
                            </a>
                            <div class="text-slate-500 mt-0.5">
                                {{ $dept['active_processes'] }} Active Processes &bull; {{ $dept['scheduled'] }} Scheduled
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="font-bold text-slate-800">{{ $dept['completion_rate'] }}%</span>
                            <div class="text-[11px] text-slate-400">
                                {{ $dept['completed'] }} done / {{ $dept['overdue'] }} overdue
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 italic py-4 text-center">No department activity recorded in this period.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
