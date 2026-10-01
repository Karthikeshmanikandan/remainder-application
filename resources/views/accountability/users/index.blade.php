@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">People Accountability</h1>
            <p class="text-sm text-slate-500 mt-1">Factual monitoring of assigned process checklist activities and confirmations.</p>
        </div>
    </div>

    {{-- Date Filter --}}
    <x-date-range-filter :dateInfo="$date_info" :actionUrl="route('accountability.users.index')" />

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

    {{-- People Accountability Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Responsible Members</h2>
            <span class="text-xs text-slate-500 font-medium">{{ $users->count() }} Members</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5">Employee</th>
                        <th class="px-6 py-3.5">Department(s)</th>
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
                    @forelse($users as $userItem)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center text-xs">
                                        {{ strtoupper(substr($userItem['name'], 0, 1)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('accountability.users.show', ['user' => $userItem['id'], 'date_range' => $date_info['preset']]) }}"
                                           class="font-semibold text-slate-900 hover:text-blue-600">
                                            {{ $userItem['name'] }}
                                        </a>
                                        <div class="text-xs text-slate-400">{{ $userItem['email'] }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($userItem['departments'] as $deptName)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                                            {{ $deptName }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-slate-900">
                                {{ $userItem['metrics']['total'] }}
                            </td>
                            <td class="px-6 py-4 text-center font-semibold text-emerald-700">
                                {{ $userItem['metrics']['completed'] }}
                            </td>
                            <td class="px-6 py-4 text-center text-slate-600">
                                {{ $userItem['metrics']['pending'] }}
                            </td>
                            <td class="px-6 py-4 text-center font-semibold text-amber-700">
                                {{ $userItem['metrics']['overdue'] }}
                            </td>
                            <td class="px-6 py-4 text-center font-semibold text-rose-700">
                                {{ $userItem['metrics']['missed'] }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="w-28">
                                    <div class="flex justify-between text-xs font-bold text-slate-700 mb-1">
                                        <span>{{ $userItem['metrics']['completion_rate'] }}%</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2">
                                        <div class="h-2 rounded-full {{ $userItem['metrics']['completion_rate'] >= 80 ? 'bg-emerald-500' : ($userItem['metrics']['completion_rate'] >= 50 ? 'bg-amber-500' : 'bg-slate-400') }}"
                                             style="width: {{ min(100, $userItem['metrics']['completion_rate']) }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('accountability.users.show', ['user' => $userItem['id'], 'date_range' => $date_info['preset']]) }}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-colors">
                                    <span>Details</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                                No assigned users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
