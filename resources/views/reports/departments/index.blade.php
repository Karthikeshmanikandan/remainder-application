@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                <span>/</span>
                <span>Departments</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Department Activity & Workload</h1>
            <p class="text-sm text-slate-500 mt-1">Factual operational activity, execution volume, and completion percentages per department.</p>
        </div>
    </div>

    {{-- Filter Bar --}}
    @include('reports.components.filter-bar', ['action' => route('reports.departments.index'), 'dateInfo' => $dateInfo])

    {{-- Departments Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Departments</h2>
            <span class="text-xs text-slate-500 font-medium">{{ count($report['departments']) }} Departments</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">Department</th>
                        <th class="px-5 py-3.5 text-center">Active Processes</th>
                        <th class="px-5 py-3.5 text-center">Scheduled</th>
                        <th class="px-5 py-3.5 text-center text-emerald-700">Completed</th>
                        <th class="px-5 py-3.5 text-center text-slate-600">Pending</th>
                        <th class="px-5 py-3.5 text-center text-amber-700">Overdue</th>
                        <th class="px-5 py-3.5 text-center text-rose-700">Missed</th>
                        <th class="px-5 py-3.5 text-center text-orange-700">Escalations</th>
                        <th class="px-5 py-3.5 text-center text-blue-700">Completion %</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($report['departments'] as $dept)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-4">
                                <a href="{{ route('reports.departments.show', $dept['id']) }}" class="font-bold text-slate-900 hover:text-blue-600">
                                    {{ $dept['name'] }}
                                </a>
                                <div class="text-xs text-slate-400 font-mono">{{ $dept['code'] }}</div>
                            </td>
                            <td class="px-5 py-4 text-center font-semibold text-slate-700">
                                {{ $dept['active_processes'] }}
                            </td>
                            <td class="px-5 py-4 text-center font-bold text-slate-900">
                                {{ $dept['scheduled'] }}
                            </td>
                            <td class="px-5 py-4 text-center font-bold text-emerald-700">
                                {{ $dept['completed'] }}
                            </td>
                            <td class="px-5 py-4 text-center font-medium text-slate-600">
                                {{ $dept['pending'] }}
                            </td>
                            <td class="px-5 py-4 text-center font-medium text-amber-700">
                                {{ $dept['overdue'] }}
                            </td>
                            <td class="px-5 py-4 text-center font-medium text-rose-700">
                                {{ $dept['missed'] }}
                            </td>
                            <td class="px-5 py-4 text-center font-bold text-orange-700">
                                {{ $dept['escalations'] }}
                            </td>
                            <td class="px-5 py-4 text-center font-bold text-blue-700">
                                {{ $dept['completion_rate'] }}%
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <a href="{{ route('reports.departments.show', $dept['id']) }}" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">
                                    View Detail →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center text-slate-400">
                                No department activity recorded for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                {{-- Total Summary Row --}}
                @if(count($report['departments']) > 0)
                    <tfoot class="bg-slate-50 font-bold text-slate-900 text-xs border-t-2 border-slate-200">
                        <tr>
                            <td class="px-5 py-3.5 uppercase tracking-wider">Total / Overall</td>
                            <td class="px-5 py-3.5 text-center">{{ array_sum(array_column($report['departments'], 'active_processes')) }}</td>
                            <td class="px-5 py-3.5 text-center">{{ $report['total_summary']['scheduled'] }}</td>
                            <td class="px-5 py-3.5 text-center text-emerald-700">{{ $report['total_summary']['completed'] }}</td>
                            <td class="px-5 py-3.5 text-center">{{ $report['total_summary']['pending'] }}</td>
                            <td class="px-5 py-3.5 text-center text-amber-700">{{ $report['total_summary']['overdue'] }}</td>
                            <td class="px-5 py-3.5 text-center text-rose-700">{{ $report['total_summary']['missed'] }}</td>
                            <td class="px-5 py-3.5 text-center text-orange-700">{{ $report['total_summary']['escalations'] }}</td>
                            <td class="px-5 py-3.5 text-center text-blue-700">{{ $report['total_summary']['completion_rate'] }}%</td>
                            <td class="px-5 py-3.5"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
