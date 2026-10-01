@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Process Performance</h1>
            <p class="text-sm text-slate-500 mt-1">Operational accountability tracking and completion metrics per process definition.</p>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 space-y-3">
        <form method="GET" action="{{ route('accountability.processes.index') }}" class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Period:</span>
                @php
                    $presets = [
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'this_week' => 'This Week',
                        'last_7_days' => 'Last 7 Days',
                        'this_month' => 'This Month',
                        'last_month' => 'Last Month',
                    ];
                    $currentPreset = $date_info['preset'] ?? request('date_range', 'this_month');
                @endphp

                @foreach($presets as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['date_range' => $key, 'start_date' => null, 'end_date' => null]) }}"
                       class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors {{ $currentPreset === $key ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-3">
                <select name="department_id" onchange="this.form.submit()" class="text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>

                <select name="responsible_user_id" onchange="this.form.submit()" class="text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Responsible</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('responsible_user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

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

    {{-- Processes Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Process Definitions</h2>
            <span class="text-xs text-slate-500 font-medium">{{ $processes->count() }} Processes</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5">Process</th>
                        <th class="px-6 py-3.5">Department</th>
                        <th class="px-6 py-3.5">Responsible</th>
                        <th class="px-6 py-3.5 text-center">Frequency</th>
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
                    @forelse($processes as $proc)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-6 py-4">
                                <a href="{{ route('accountability.processes.show', ['process' => $proc['id'], 'date_range' => $date_info['preset']]) }}"
                                   class="font-semibold text-slate-900 hover:text-blue-600">
                                    {{ $proc['name'] }}
                                </a>
                                <div class="text-xs text-slate-400 font-mono mt-0.5">{{ $proc['code'] }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">
                                    {{ $proc['department_name'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-medium text-slate-800">
                                {{ $proc['responsible_user_name'] }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-xs font-mono uppercase bg-slate-100 px-2 py-0.5 rounded text-slate-600">
                                    {{ $proc['frequency'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-slate-900">
                                {{ $proc['metrics']['total'] }}
                            </td>
                            <td class="px-6 py-4 text-center font-semibold text-emerald-700">
                                {{ $proc['metrics']['completed'] }}
                            </td>
                            <td class="px-6 py-4 text-center text-slate-600">
                                {{ $proc['metrics']['pending'] }}
                            </td>
                            <td class="px-6 py-4 text-center font-semibold text-amber-700">
                                {{ $proc['metrics']['overdue'] }}
                            </td>
                            <td class="px-6 py-4 text-center font-semibold text-rose-700">
                                {{ $proc['metrics']['missed'] }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="w-28">
                                    <div class="flex justify-between text-xs font-bold text-slate-700 mb-1">
                                        <span>{{ $proc['metrics']['completion_rate'] }}%</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2">
                                        <div class="h-2 rounded-full {{ $proc['metrics']['completion_rate'] >= 80 ? 'bg-emerald-500' : ($proc['metrics']['completion_rate'] >= 50 ? 'bg-amber-500' : 'bg-slate-400') }}"
                                             style="width: {{ min(100, $proc['metrics']['completion_rate']) }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('accountability.processes.show', ['process' => $proc['id'], 'date_range' => $date_info['preset']]) }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-colors">
                                    <span>Details</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-6 py-12 text-center text-slate-400">
                                No processes found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
