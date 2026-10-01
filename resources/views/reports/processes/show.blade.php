@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                <span>/</span>
                <a href="{{ route('reports.processes.index') }}" class="hover:underline">Processes</a>
                <span>/</span>
                <span>{{ $process->name }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $process->name }}</h1>
                <span class="font-mono text-xs px-2 py-0.5 bg-slate-100 text-slate-600 rounded">{{ $process->code }}</span>
                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-blue-50 text-blue-700">
                    {{ $process->department->name }}
                </span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('processes.show', $process) }}" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg text-xs font-semibold hover:bg-slate-50 transition shadow-sm">
                Process Definition →
            </a>
        </div>
    </div>

    {{-- Filter Bar --}}
    @include('reports.components.filter-bar', ['action' => route('reports.processes.show', $process), 'dateInfo' => $dateInfo])

    {{-- Process Config & Reliability Overview --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Configuration</h3>
            <div class="space-y-2 text-xs text-slate-600">
                <div class="flex justify-between">
                    <span class="text-slate-400">Frequency:</span>
                    <span class="font-semibold text-slate-800 capitalize">{{ $process->frequency->value }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Reminder Time:</span>
                    <span class="font-semibold text-slate-800">{{ $process->reminder_time ?? '17:00' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Responsible:</span>
                    <span class="font-semibold text-slate-800">{{ $process->responsibleUser->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Status:</span>
                    <span class="font-semibold text-slate-800 capitalize">{{ $process->status->value }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Escalation Policy</h3>
            @if($process->escalationRules->isEmpty())
                <p class="text-xs text-slate-400 italic">No escalation rules configured.</p>
            @else
                <div class="space-y-2">
                    @foreach($process->escalationRules->sortBy('level') as $rule)
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-slate-800">Level {{ $rule->level }} ({{ $rule->delay_minutes }}m)</span>
                            <span class="text-slate-500">{{ $rule->escalateToUser?->name ?? 'Unassigned' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Channels & Activity</h3>
            <div class="space-y-2 text-xs text-slate-600">
                <div class="flex justify-between">
                    <span class="text-slate-400">In-App Notification:</span>
                    <span class="font-semibold {{ $process->in_app_enabled ? 'text-emerald-700' : 'text-slate-500' }}">{{ $process->in_app_enabled ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Telegram Bot:</span>
                    <span class="font-semibold {{ $process->telegram_enabled ? 'text-sky-700' : 'text-slate-500' }}">{{ $process->telegram_enabled ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Active Questions:</span>
                    <span class="font-semibold text-slate-800">{{ $process->enabledItems->count() }} of {{ $process->items->count() }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Period Metrics Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-400">Scheduled</span>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $metrics['scheduled'] }}</div>
            <div class="text-[11px] text-slate-400 mt-1">vs {{ $prev_metrics['scheduled'] }} prev</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-emerald-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-emerald-700">Completed</span>
            <div class="text-2xl font-bold text-emerald-700 mt-1">{{ $metrics['completed'] }}</div>
            <div class="text-[11px] text-emerald-600 mt-1">vs {{ $prev_metrics['completed'] }} prev</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-500">Pending</span>
            <div class="text-2xl font-bold text-slate-700 mt-1">{{ $metrics['pending'] }}</div>
            <div class="text-[11px] text-slate-400 mt-1">vs {{ $prev_metrics['pending'] }} prev</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-amber-700">Overdue</span>
            <div class="text-2xl font-bold text-amber-700 mt-1">{{ $metrics['overdue'] }}</div>
            <div class="text-[11px] text-amber-600 mt-1">vs {{ $prev_metrics['overdue'] }} prev</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-rose-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-rose-700">Missed</span>
            <div class="text-2xl font-bold text-rose-700 mt-1">{{ $metrics['missed'] }}</div>
            <div class="text-[11px] text-rose-600 mt-1">vs {{ $prev_metrics['missed'] }} prev</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-orange-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-orange-700">Escalations</span>
            <div class="text-2xl font-bold text-orange-700 mt-1">{{ $metrics['escalations'] }}</div>
            <div class="text-[11px] text-orange-600 mt-1">vs {{ $prev_metrics['escalations'] }} prev</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-blue-200 shadow-sm col-span-2 sm:col-span-1">
            <span class="text-xs font-bold uppercase text-blue-700">Completion %</span>
            <div class="text-2xl font-bold text-blue-700 mt-1">{{ $metrics['completion_rate'] }}%</div>
            <div class="text-[11px] text-blue-600 mt-1">Prev: {{ $prev_metrics['completion_rate'] }}%</div>
        </div>
    </div>

    {{-- Execution Trend --}}
    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
        <h2 class="text-base font-bold text-slate-900 mb-4">Execution Trend</h2>
        @if(empty($trends) || collect($trends)->sum('scheduled') === 0)
            <div class="p-6 text-center text-slate-400 text-sm">
                No executions recorded for this process in the selected period.
            </div>
        @else
            <div class="overflow-x-auto">
                <div class="min-w-[600px] h-48 flex items-end gap-2 pt-6 pb-2 border-b border-slate-100">
                    @php $maxCount = max(collect($trends)->max('scheduled'), 1); @endphp
                    @foreach($trends as $day)
                        <div class="flex-1 flex flex-col items-center gap-1 group relative h-full justify-end">
                            <div class="absolute bottom-full mb-1 hidden group-hover:flex flex-col bg-slate-900 text-white text-[10px] rounded p-1.5 shadow z-30 whitespace-nowrap">
                                <span class="font-bold">{{ $day['label'] }}</span>
                                <span>Scheduled: {{ $day['scheduled'] }}</span>
                                <span class="text-emerald-300">Completed: {{ $day['completed'] }}</span>
                                <span class="text-amber-300">Overdue: {{ $day['overdue'] }}</span>
                            </div>
                            <div class="w-full max-w-[20px] bg-slate-100 rounded-t flex flex-col-reverse overflow-hidden" style="height: {{ ($day['scheduled'] / $maxCount) * 100 }}%">
                                @if($day['completed'] > 0)
                                    <div class="bg-emerald-500 w-full" style="height: {{ ($day['completed'] / max($day['scheduled'], 1)) * 100 }}%"></div>
                                @endif
                                @if($day['overdue'] > 0)
                                    <div class="bg-amber-500 w-full" style="height: {{ ($day['overdue'] / max($day['scheduled'], 1)) * 100 }}%"></div>
                                @endif
                            </div>
                            <span class="text-[9px] text-slate-400 font-mono">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Checklist Response Breakdown Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-base font-bold text-slate-900">Checklist Question Responses Analysis</h2>
                <p class="text-xs text-slate-400 mt-0.5">Aggregated human responses across all executions in this period</p>
            </div>
            <span class="text-xs text-slate-500 font-medium">{{ count($response_breakdown) }} Questions</span>
        </div>

        @if(empty($response_breakdown))
            <p class="text-xs text-slate-400 italic py-4 text-center">No checklist execution items found in this period.</p>
        @else
            <div class="space-y-4">
                @foreach($response_breakdown as $item)
                    <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">{{ $item['question'] }}</h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] font-mono uppercase bg-slate-200 text-slate-700 px-1.5 py-0.5 rounded">{{ $item['response_type'] }}</span>
                                    @if($item['is_required'])
                                        <span class="text-[10px] font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">Required</span>
                                    @endif
                                    <span class="text-xs text-slate-500">{{ $item['total_answered'] }} answered &bull; {{ $item['total_unanswered'] }} unanswered</span>
                                </div>
                            </div>
                        </div>

                        {{-- Distribution pills --}}
                        <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-200/60">
                            @foreach($item['distribution'] as $val => $cnt)
                                <div class="px-2.5 py-1 bg-white rounded-md border border-slate-200 text-xs font-medium text-slate-700 flex items-center gap-1.5">
                                    <span class="font-bold {{ $val === 'YES' ? 'text-blue-600' : ($val === 'NO' ? 'text-red-600' : 'text-slate-600') }}">{{ $val }}:</span>
                                    <span>{{ $cnt }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Execution History & Escalation History Tabs/Columns --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Execution History --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="text-base font-bold text-slate-900">Execution History ({{ $executions->total() }})</h2>
            </div>

            <div class="space-y-3">
                @forelse($executions as $exec)
                    <div class="p-3.5 rounded-lg border border-slate-100 hover:border-slate-300 transition-colors flex items-center justify-between gap-4 text-xs">
                        <div>
                            <div class="font-semibold text-slate-900 flex items-center gap-2">
                                <span>{{ $exec->scheduled_for->format('d M Y, H:i') }}</span>
                                @if($exec->escalationEvents->isNotEmpty())
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-orange-100 text-orange-800">
                                        Escalated (L{{ $exec->escalationEvents->max('level') }})
                                    </span>
                                @endif
                            </div>
                            <div class="text-slate-400 mt-0.5">
                                @if($exec->isCompleted() && $exec->completedBy)
                                    Confirmed by {{ $exec->completedBy->name }} at {{ $exec->completed_at?->format('H:i') }}
                                @else
                                    Scheduled execution
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="px-2 py-0.5 rounded font-semibold
                                @if($exec->isCompleted()) bg-emerald-50 text-emerald-700
                                @elseif($exec->isMissed()) bg-rose-50 text-rose-700
                                @elseif($exec->isOverdue()) bg-amber-50 text-amber-700
                                @else bg-slate-100 text-slate-600 @endif">
                                {{ ucfirst($exec->status->value) }}
                            </span>
                            <a href="{{ route('process-executions.show', $exec) }}" class="text-blue-600 hover:underline font-semibold">Open →</a>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 italic py-4 text-center">No executions found in this period.</p>
                @endforelse
            </div>

            @if($executions->hasPages())
                <div class="pt-3 border-t border-slate-100">
                    {{ $executions->links() }}
                </div>
            @endif
        </div>

        {{-- Escalations History --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="text-base font-bold text-slate-900">Escalations History ({{ count($escalations) }})</h2>
            </div>

            <div class="space-y-3">
                @forelse($escalations as $esc)
                    <div class="p-3.5 rounded-lg border border-slate-100 bg-slate-50/50 space-y-1.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900">Level {{ $esc->level }} Escalation</span>
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold
                                @if($esc->isTriggered()) bg-amber-100 text-amber-800
                                @elseif($esc->isAcknowledged()) bg-blue-100 text-blue-800
                                @elseif($esc->isResolved()) bg-emerald-100 text-emerald-800
                                @else bg-slate-100 text-slate-700 @endif">
                                {{ ucfirst($esc->status->value) }}
                            </span>
                        </div>
                        <div class="text-slate-500">
                            Triggered: {{ $esc->triggered_at?->format('d M Y, H:i') }} &bull; Escalated to: <strong>{{ $esc->rule?->escalateToUser?->name ?? 'Manager' }}</strong>
                        </div>
                        @if($esc->acknowledged_at)
                            <div class="text-blue-700 text-[11px]">
                                Acknowledged by {{ $esc->acknowledgedBy?->name ?? 'Manager' }} at {{ $esc->acknowledged_at->format('d M, H:i') }}
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-slate-400 italic py-4 text-center">No escalation events recorded in this period.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
