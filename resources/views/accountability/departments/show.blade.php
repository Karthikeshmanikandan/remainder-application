@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('accountability.departments.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 flex items-center gap-1 mb-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Back to Departments
            </a>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $department->name }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                    {{ $department->code }}
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ $department->description ?? 'Operational accountability and process execution records.' }}</p>
        </div>
    </div>

    {{-- Date Filter --}}
    <x-date-range-filter :dateInfo="$date_info" :actionUrl="route('accountability.departments.show', $department)" />

    {{-- Department Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-400">Total Due</span>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $metrics['total'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-emerald-600">Completed</span>
            <div class="text-2xl font-bold text-emerald-700 mt-1">{{ $metrics['completed'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-500">Pending</span>
            <div class="text-2xl font-bold text-slate-700 mt-1">{{ $metrics['pending'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-amber-600">Overdue</span>
            <div class="text-2xl font-bold text-amber-700 mt-1">{{ $metrics['overdue'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-rose-600">Missed</span>
            <div class="text-2xl font-bold text-rose-700 mt-1">{{ $metrics['missed'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-blue-600">Completion %</span>
            <div class="text-2xl font-bold text-blue-700 mt-1">{{ $metrics['completion_rate'] }}%</div>
        </div>
    </div>

    {{-- Main 2 Column Section: Processes & People --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Department Processes --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Department Processes</h2>
            <div class="space-y-3">
                @forelse($processes as $item)
                    <div class="p-3 rounded-lg border border-slate-100 hover:border-slate-300 hover:bg-slate-50/50 transition-colors flex items-center justify-between gap-4">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">{{ $item['process']->name }}</div>
                            <div class="text-xs text-slate-400 mt-0.5">
                                Responsible: {{ $item['process']->responsibleUser->name }} &bull; {{ ucfirst($item['process']->frequency->value) }}
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold text-slate-800">{{ $item['metrics']['completed'] }}/{{ $item['metrics']['total'] }} Done</span>
                            <div class="text-[11px] font-semibold text-blue-600">{{ $item['metrics']['completion_rate'] }}%</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 py-4 text-center">No processes configured in this department.</p>
                @endforelse
            </div>
        </div>

        {{-- Responsible People --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Assigned Team Members</h2>
            <div class="space-y-3">
                @forelse($responsible_users as $item)
                    <div class="p-3 rounded-lg border border-slate-100 hover:border-slate-300 hover:bg-slate-50/50 transition-colors flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-700 font-bold flex items-center justify-center text-xs">
                                {{ strtoupper(substr($item['user']->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-900">{{ $item['user']->name }}</div>
                                <div class="text-xs text-slate-400">{{ $item['user']->email }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold text-slate-800">{{ $item['metrics']['completed'] }}/{{ $item['metrics']['total'] }} Done</span>
                            <div class="text-[11px] font-semibold text-blue-600">{{ $item['metrics']['completion_rate'] }}%</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 py-4 text-center">No members assigned to processes in this department.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent Executions in this Department --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Department Execution History</h2>
            <span class="text-xs text-slate-400">{{ $executions->total() }} Total Executions</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5">Process</th>
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
                            </td>
                            <td class="px-6 py-4">
                                {{ $exec->process->responsibleUser->name }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-slate-800 font-medium">{{ $exec->scheduled_for->format('d M Y') }}</div>
                                <div class="text-xs text-slate-400">{{ $exec->scheduled_for->format('H:i') }} UTC</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-mono">{{ $exec->answeredCount() }}/{{ $exec->totalCount() }} ({{ $exec->progressPercentage() }}%)</span>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $statusBadges = [
                                        'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'in_progress' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'pending' => 'bg-slate-50 text-slate-600 border-slate-200',
                                        'missed' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border {{ $statusBadges[$exec->status->value] ?? 'bg-slate-100 text-slate-600' }}">
                                    {{ ucfirst(str_replace('_', ' ', $exec->status->value)) }}
                                </span>
                                @if($exec->isOverdue())
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 ml-1">
                                        Overdue
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('process-executions.show', $exec) }}" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">
                                    View Checklist →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-400 text-xs">
                                No executions recorded for the selected period.
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
