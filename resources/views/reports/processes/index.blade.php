@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                <span>/</span>
                <span>Processes</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Process Performance & Reliability</h1>
            <p class="text-sm text-slate-500 mt-1">Factual execution counts, completion percentages, and escalation occurrences per process definition.</p>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 space-y-3">
        <form method="GET" action="{{ route('reports.processes.index') }}" class="space-y-3">
            {{-- Preset Buttons --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 mr-1">Period:</span>
                    @php
                        $presets = [
                            'today' => 'Today',
                            'yesterday' => 'Yesterday',
                            'this_week' => 'This Week',
                            'last_week' => 'Last Week',
                            'last_7_days' => 'Last 7 Days',
                            'this_month' => 'This Month',
                            'last_month' => 'Last Month',
                            'last_30_days' => 'Last 30 Days',
                        ];
                        $currentPreset = $dateInfo['preset'] ?? request('date_range', 'this_month');
                    @endphp

                    @foreach($presets as $key => $label)
                        <a href="{{ request()->fullUrlWithQuery(['date_range' => $key, 'start_date' => null, 'end_date' => null, 'page' => null]) }}"
                           class="px-2.5 py-1 text-xs font-semibold rounded-lg transition-colors {{ $currentPreset === $key ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-2.5 py-1 rounded-md">
                        {{ $dateInfo['label'] ?? 'Selected Period' }}
                    </span>
                </div>
            </div>

            {{-- Custom Date Inputs & Dimension Filters --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 pt-2 border-t border-slate-100">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Process name or code..." class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Department</label>
                    <select name="department_id" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Responsible User</label>
                    <select name="responsible_user_id" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Responsible</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ request('responsible_user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Frequency</label>
                    <select name="frequency" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Frequencies</option>
                        <option value="daily" {{ request('frequency') === 'daily' ? 'selected' : '' }}>Daily</option>
                        <option value="weekly" {{ request('frequency') === 'weekly' ? 'selected' : '' }}>Weekly</option>
                        <option value="monthly" {{ request('frequency') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                    <select name="status" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="paused" {{ request('status') === 'paused' ? 'selected' : '' }}>Paused</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold rounded-lg transition">
                        Apply Filters
                    </button>
                    <a href="{{ route('reports.processes.index') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-lg text-center transition">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Processes Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Processes</h2>
            <span class="text-xs text-slate-500 font-medium">{{ $processes->total() }} Total Processes</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">Process & Code</th>
                        <th class="px-5 py-3.5">Department</th>
                        <th class="px-5 py-3.5">Responsible</th>
                        <th class="px-5 py-3.5 text-center">Frequency</th>
                        <th class="px-5 py-3.5 text-center">Scheduled</th>
                        <th class="px-5 py-3.5 text-center text-emerald-700">Completed</th>
                        <th class="px-5 py-3.5 text-center">Pending</th>
                        <th class="px-5 py-3.5 text-center text-amber-700">Overdue</th>
                        <th class="px-5 py-3.5 text-center text-rose-700">Missed</th>
                        <th class="px-5 py-3.5 text-center text-orange-700">Escalations</th>
                        <th class="px-5 py-3.5 text-center text-blue-700">Completion %</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($processes as $process)
                        @php $stats = $process->period_stats; @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-4">
                                <a href="{{ route('reports.processes.show', $process) }}" class="font-bold text-slate-900 hover:text-blue-600">
                                    {{ $process->name }}
                                </a>
                                <div class="text-xs text-slate-400 font-mono mt-0.5">{{ $process->code }}</div>
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-600">
                                {{ $process->department->name }}
                            </td>
                            <td class="px-5 py-4 text-xs font-semibold text-slate-800">
                                {{ $process->responsibleUser->name }}
                            </td>
                            <td class="px-5 py-4 text-center text-xs capitalize text-slate-600">
                                {{ $process->frequency->value }}
                            </td>
                            <td class="px-5 py-4 text-center font-bold text-slate-900">
                                {{ $stats['scheduled'] }}
                            </td>
                            <td class="px-5 py-4 text-center font-bold text-emerald-700">
                                {{ $stats['completed'] }}
                            </td>
                            <td class="px-5 py-4 text-center text-slate-600">
                                {{ $stats['pending'] }}
                            </td>
                            <td class="px-5 py-4 text-center text-amber-700">
                                {{ $stats['overdue'] }}
                            </td>
                            <td class="px-5 py-4 text-center text-rose-700">
                                {{ $stats['missed'] }}
                            </td>
                            <td class="px-5 py-4 text-center font-bold text-orange-700">
                                {{ $stats['escalations'] }}
                            </td>
                            <td class="px-5 py-4 text-center font-bold text-blue-700">
                                {{ $stats['completion_rate'] }}%
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <a href="{{ route('reports.processes.show', $process) }}" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">
                                    View Report →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-6 py-12 text-center text-slate-400">
                                No processes match the selected filters for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($processes->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50">
                {{ $processes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
