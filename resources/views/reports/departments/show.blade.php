@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                <span>/</span>
                <a href="{{ route('reports.departments.index') }}" class="hover:underline">Departments</a>
                <span>/</span>
                <span>{{ $department->name }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $department->name }}</h1>
                <span class="font-mono text-xs px-2 py-0.5 bg-slate-100 text-slate-600 rounded">{{ $department->code }}</span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ $department->description ?? 'Department operational activity report.' }}</p>
        </div>
    </div>

    {{-- Filter Bar --}}
    @include('reports.components.filter-bar', ['action' => route('reports.departments.show', $department), 'dateInfo' => $dateInfo])

    {{-- Department Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-400">Scheduled</span>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $metrics['scheduled'] }}</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-emerald-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-emerald-700">Completed</span>
            <div class="text-2xl font-bold text-emerald-700 mt-1">{{ $metrics['completed'] }}</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-500">Pending</span>
            <div class="text-2xl font-bold text-slate-700 mt-1">{{ $metrics['pending'] }}</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-amber-700">Overdue</span>
            <div class="text-2xl font-bold text-amber-700 mt-1">{{ $metrics['overdue'] }}</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-rose-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-rose-700">Missed</span>
            <div class="text-2xl font-bold text-rose-700 mt-1">{{ $metrics['missed'] }}</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-orange-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-orange-700">Escalations</span>
            <div class="text-2xl font-bold text-orange-700 mt-1">{{ $metrics['escalations'] }}</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-blue-200 shadow-sm col-span-2 sm:col-span-1">
            <span class="text-xs font-bold uppercase text-blue-700">Completion %</span>
            <div class="text-2xl font-bold text-blue-700 mt-1">{{ $metrics['completion_rate'] }}%</div>
        </div>
    </div>

    {{-- Daily Execution Trends for Department --}}
    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
        <h2 class="text-base font-bold text-slate-900 mb-4">Department Execution Trend</h2>
        @if(empty($trends) || collect($trends)->sum('scheduled') === 0)
            <div class="p-6 text-center text-slate-400 text-sm">
                No executions recorded in this department for the selected period.
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

    {{-- Processes in Department Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Processes in {{ $department->name }}</h2>
            <span class="text-xs text-slate-500 font-medium">{{ count($processes) }} Processes</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">Process</th>
                        <th class="px-5 py-3.5">Responsible</th>
                        <th class="px-5 py-3.5 text-center">Frequency</th>
                        <th class="px-5 py-3.5 text-center">Scheduled</th>
                        <th class="px-5 py-3.5 text-center text-emerald-700">Completed</th>
                        <th class="px-5 py-3.5 text-center">Pending</th>
                        <th class="px-5 py-3.5 text-center text-amber-700">Overdue</th>
                        <th class="px-5 py-3.5 text-center text-rose-700">Missed</th>
                        <th class="px-5 py-3.5 text-center text-orange-700">Escalations</th>
                        <th class="px-5 py-3.5 text-center text-blue-700">Completion %</th>
                        <th class="px-5 py-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($processes as $item)
                        @php $proc = $item['process']; @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-4">
                                <a href="{{ route('reports.processes.show', $proc) }}" class="font-bold text-slate-900 hover:text-blue-600">
                                    {{ $proc->name }}
                                </a>
                                <div class="text-xs text-slate-400 font-mono">{{ $proc->code }}</div>
                            </td>
                            <td class="px-5 py-4 text-xs font-semibold text-slate-800">
                                {{ $proc->responsibleUser->name }}
                            </td>
                            <td class="px-5 py-4 text-center text-xs capitalize text-slate-600">
                                {{ $proc->frequency->value }}
                            </td>
                            <td class="px-5 py-4 text-center font-bold text-slate-900">
                                {{ $item['scheduled'] }}
                            </td>
                            <td class="px-5 py-4 text-center font-bold text-emerald-700">
                                {{ $item['completed'] }}
                            </td>
                            <td class="px-5 py-4 text-center text-slate-600">
                                {{ $item['pending'] }}
                            </td>
                            <td class="px-5 py-4 text-center text-amber-700">
                                {{ $item['overdue'] }}
                            </td>
                            <td class="px-5 py-4 text-center text-rose-700">
                                {{ $item['missed'] }}
                            </td>
                            <td class="px-5 py-4 text-center font-bold text-orange-700">
                                {{ $item['escalations'] }}
                            </td>
                            <td class="px-5 py-4 text-center font-bold text-blue-700">
                                {{ $item['completion_rate'] }}%
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <a href="{{ route('reports.processes.show', $proc) }}" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">
                                    Process Detail →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-6 py-8 text-center text-slate-400">
                                No processes configured for this department.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
