@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Department Accountability</h1>
            <p class="text-sm text-slate-500 mt-1">Cross-departmental monitoring of scheduled process execution and human confirmation.</p>
        </div>
    </div>

    {{-- Date Filter --}}
    <x-date-range-filter :dateInfo="$date_info" :actionUrl="route('accountability.departments.index')" />

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-400">Total Due</span>
            <div class="text-2xl font-bold text-slate-900 mt-1">{{ $summary['total'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-emerald-600">Completed</span>
            <div class="text-2xl font-bold text-emerald-700 mt-1">{{ $summary['completed'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-500">Pending</span>
            <div class="text-2xl font-bold text-slate-700 mt-1">{{ $summary['pending'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-amber-600">Overdue</span>
            <div class="text-2xl font-bold text-amber-700 mt-1">{{ $summary['overdue'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-rose-600">Missed</span>
            <div class="text-2xl font-bold text-rose-700 mt-1">{{ $summary['missed'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold uppercase text-blue-600">Completion %</span>
            <div class="text-2xl font-bold text-blue-700 mt-1">{{ $summary['completion_rate'] }}%</div>
        </div>
    </div>

    {{-- Department Accountability Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Departments Overview</h2>
            <span class="text-xs text-slate-500 font-medium">{{ $departments->count() }} Departments</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5">Department</th>
                        <th class="px-6 py-3.5 text-center">Active Processes</th>
                        <th class="px-6 py-3.5 text-center">Due in Period</th>
                        <th class="px-6 py-3.5 text-center text-emerald-700">Completed</th>
                        <th class="px-6 py-3.5 text-center text-slate-600">Pending</th>
                        <th class="px-6 py-3.5 text-center text-amber-700">Overdue</th>
                        <th class="px-6 py-3.5 text-center text-rose-700">Missed</th>
                        <th class="px-6 py-3.5">Completion %</th>
                        <th class="px-6 py-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($departments as $dept)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-6 py-4">
                                <a href="{{ route('accountability.departments.show', ['department' => $dept['id'], 'date_range' => $date_info['preset']]) }}"
                                   class="font-semibold text-slate-900 hover:text-blue-600">
                                    {{ $dept['name'] }}
                                </a>
                                <div class="text-xs text-slate-400 font-mono mt-0.5">{{ $dept['code'] }}</div>
                            </td>
                            <td class="px-6 py-4 text-center font-medium text-slate-800">
                                {{ $dept['active_processes_count'] }}
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-slate-900">
                                {{ $dept['metrics']['total'] }}
                            </td>
                            <td class="px-6 py-4 text-center font-semibold text-emerald-700">
                                {{ $dept['metrics']['completed'] }}
                            </td>
                            <td class="px-6 py-4 text-center text-slate-600">
                                {{ $dept['metrics']['pending'] }}
                            </td>
                            <td class="px-6 py-4 text-center font-semibold text-amber-700">
                                {{ $dept['metrics']['overdue'] }}
                            </td>
                            <td class="px-6 py-4 text-center font-semibold text-rose-700">
                                {{ $dept['metrics']['missed'] }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="w-28">
                                    <div class="flex justify-between text-xs font-bold text-slate-700 mb-1">
                                        <span>{{ $dept['metrics']['completion_rate'] }}%</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2">
                                        <div class="h-2 rounded-full {{ $dept['metrics']['completion_rate'] >= 80 ? 'bg-emerald-500' : ($dept['metrics']['completion_rate'] >= 50 ? 'bg-amber-500' : 'bg-slate-400') }}"
                                             style="width: {{ min(100, $dept['metrics']['completion_rate']) }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('accountability.departments.show', ['department' => $dept['id'], 'date_range' => $date_info['preset']]) }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-colors">
                                    <span>Details</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                                No departments found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
