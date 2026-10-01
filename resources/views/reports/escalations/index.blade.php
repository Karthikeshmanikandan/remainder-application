@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                <span>/</span>
                <span>Escalations</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Escalation Reports & Incident Audit</h1>
            <p class="text-sm text-slate-500 mt-1">Audit log of automated process escalation triggers, managerial notifications, and response tracking.</p>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 space-y-3">
        <form method="GET" action="{{ route('reports.escalations.index') }}" class="space-y-3">
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

            {{-- Custom Date Inputs & Multi-dimension Filters --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 pt-2 border-t border-slate-100">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Department</label>
                    <select name="department_id" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Process</label>
                    <select name="process_id" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Processes</option>
                        @foreach($processes as $proc)
                            <option value="{{ $proc->id }}" {{ request('process_id') == $proc->id ? 'selected' : '' }}>
                                {{ $proc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Responsible Employee</label>
                    <select name="responsible_user_id" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Employees</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ request('responsible_user_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Escalated To</label>
                    <select name="recipient_id" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Recipients</option>
                        @foreach($recipients as $rec)
                            <option value="{{ $rec->id }}" {{ request('recipient_id') == $rec->id ? 'selected' : '' }}>
                                {{ $rec->name }} ({{ ucfirst($rec->role->value) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Escalation Level</label>
                    <select name="level" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Levels</option>
                        <option value="1" {{ request('level') === '1' ? 'selected' : '' }}>Level 1</option>
                        <option value="2" {{ request('level') === '2' ? 'selected' : '' }}>Level 2</option>
                        <option value="3" {{ request('level') === '3' ? 'selected' : '' }}>Level 3</option>
                        <option value="4" {{ request('level') === '4' ? 'selected' : '' }}>Level 4</option>
                        <option value="5" {{ request('level') === '5' ? 'selected' : '' }}>Level 5</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                    <select name="status" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Statuses</option>
                        <option value="TRIGGERED" {{ request('status') === 'TRIGGERED' ? 'selected' : '' }}>Triggered (Pending)</option>
                        <option value="ACKNOWLEDGED" {{ request('status') === 'ACKNOWLEDGED' ? 'selected' : '' }}>Acknowledged</option>
                        <option value="RESOLVED" {{ request('status') === 'RESOLVED' ? 'selected' : '' }}>Resolved</option>
                        <option value="CANCELLED" {{ request('status') === 'CANCELLED' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">From Date</label>
                    <input type="date" name="start_date" value="{{ request('start_date', $dateInfo['start']->format('Y-m-d')) }}" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">To Date</label>
                    <input type="date" name="end_date" value="{{ request('end_date', $dateInfo['end']->format('Y-m-d')) }}" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex items-end gap-2">
                    <input type="hidden" name="date_range" value="custom">
                    <button type="submit" class="w-full py-2 px-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                        Filter Audit
                    </button>
                    <a href="{{ route('reports.escalations.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-lg transition text-center">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Summary KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Escalations</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($report['summary']['total'] ?? 0) }}</div>
            <div class="mt-1 text-xs text-slate-400">Events in period</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-amber-200 bg-amber-50/20 shadow-sm">
            <div class="text-xs font-medium text-amber-700 uppercase tracking-wider">Triggered</div>
            <div class="mt-2 text-2xl font-bold text-amber-600">{{ number_format($report['summary']['triggered'] ?? 0) }}</div>
            <div class="mt-1 text-xs text-amber-600/70">Awaiting acknowledgement</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-blue-200 bg-blue-50/20 shadow-sm">
            <div class="text-xs font-medium text-blue-700 uppercase tracking-wider">Acknowledged</div>
            <div class="mt-2 text-2xl font-bold text-blue-600">{{ number_format($report['summary']['acknowledged'] ?? 0) }}</div>
            <div class="mt-1 text-xs text-blue-600/70">Management reviewed</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-emerald-200 bg-emerald-50/20 shadow-sm">
            <div class="text-xs font-medium text-emerald-700 uppercase tracking-wider">Resolved</div>
            <div class="mt-2 text-2xl font-bold text-emerald-600">{{ number_format($report['summary']['resolved'] ?? 0) }}</div>
            <div class="mt-1 text-xs text-emerald-600/70">Task completed</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">Cancelled</div>
            <div class="mt-2 text-2xl font-bold text-slate-500">{{ number_format($report['summary']['cancelled'] ?? 0) }}</div>
            <div class="mt-1 text-xs text-slate-400">Superseded / void</div>
        </div>
    </div>

    {{-- Daily Escalation Trend --}}
    @if(!empty($report['trend']) && count($report['trend']) > 1)
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Daily Escalation Occurrence Trend</h2>
                <span class="text-xs text-slate-500">Escalations fired per calendar day</span>
            </div>

            @php
                $maxEsc = max(array_column($report['trend'], 'count'));
                $maxEsc = max($maxEsc, 1);
            @endphp

            <div class="overflow-x-auto pb-2">
                <div class="min-w-[600px] flex items-end gap-1.5 h-36 pt-6">
                    @foreach($report['trend'] as $day)
                        @php
                            $heightPct = round(($day['count'] / $maxEsc) * 100);
                        @endphp
                        <div class="flex-1 flex flex-col items-center gap-1 group relative h-full justify-end">
                            {{-- Tooltip --}}
                            <div class="absolute bottom-full mb-1 hidden group-hover:flex flex-col items-center z-20 pointer-events-none">
                                <div class="bg-slate-900 text-white text-[10px] rounded px-2 py-1 shadow-lg whitespace-nowrap">
                                    <div class="font-bold">{{ $day['date'] }}</div>
                                    <div>Escalations: {{ $day['count'] }}</div>
                                </div>
                                <div class="w-1.5 h-1.5 bg-slate-900 rotate-45 -mt-0.5"></div>
                            </div>

                            {{-- Bar --}}
                            <div class="w-full max-w-[28px] rounded-t transition-all duration-300 {{ $day['count'] > 0 ? 'bg-amber-500 hover:bg-amber-600' : 'bg-slate-100' }}"
                                 style="height: {{ max($heightPct, 4) }}%;"></div>

                            {{-- Label --}}
                            <span class="text-[10px] text-slate-400 truncate w-full text-center font-medium">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Escalations Audit Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Escalation Audit Trail</h2>
            <span class="text-xs text-slate-500">{{ $report['events']->total() }} total events</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Triggered</th>
                        <th class="py-3 px-4">Process & Department</th>
                        <th class="py-3 px-4">Responsible Person</th>
                        <th class="py-3 px-4">Level & Rule</th>
                        <th class="py-3 px-4">Escalated To</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Acknowledgement</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($report['events'] as $event)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="font-medium text-slate-900">{{ $event->triggered_at->format('d M Y, H:i') }}</div>
                                <div class="text-[10px] text-slate-400">{{ $event->triggered_at->diffForHumans() }}</div>
                            </td>
                            <td class="py-3 px-4">
                                @if($event->execution && $event->execution->process)
                                    <a href="{{ route('reports.processes.show', $event->execution->process) }}" class="font-semibold text-slate-900 hover:text-blue-600 hover:underline">
                                        {{ $event->execution->process->name }}
                                    </a>
                                    <div class="text-[10px] text-slate-400">
                                        {{ $event->execution->process->department->name ?? 'General' }}
                                    </div>
                                @else
                                    <span class="text-slate-400">Process Unavailable</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if($event->execution && $event->execution->process && $event->execution->process->responsibleUser)
                                    <div class="font-medium text-slate-800">{{ $event->execution->process->responsibleUser->name }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $event->execution->process->responsibleUser->email }}</div>
                                @else
                                    <span class="text-slate-400">Unassigned</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800">
                                    Level {{ $event->level }}
                                </span>
                                @if($event->rule)
                                    <div class="text-[10px] text-slate-400 mt-0.5">+{{ $event->rule->trigger_after_minutes }} mins overdue</div>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if($event->rule && $event->rule->escalateToUser)
                                    <div class="font-medium text-slate-800">{{ $event->rule->escalateToUser->name }}</div>
                                    <div class="text-[10px] text-slate-400">{{ ucfirst($event->rule->escalateToUser->role->value) }}</div>
                                @else
                                    <span class="text-slate-400">System</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($event->status === \App\Enums\ProcessEscalationStatus::TRIGGERED)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800">
                                        Triggered
                                    </span>
                                @elseif($event->status === \App\Enums\ProcessEscalationStatus::ACKNOWLEDGED)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-100 text-blue-800">
                                        Acknowledged
                                    </span>
                                @elseif($event->status === \App\Enums\ProcessEscalationStatus::RESOLVED)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                        Resolved
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700">
                                        {{ ucfirst(strtolower($event->status->value)) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if($event->acknowledged_at)
                                    <div class="text-slate-800 font-medium">{{ $event->acknowledgedBy->name ?? 'User' }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $event->acknowledged_at->format('d M Y, H:i') }}</div>
                                    @if($event->resolution_notes)
                                        <div class="text-[11px] text-slate-600 mt-1 italic max-w-xs truncate" title="{{ $event->resolution_notes }}">
                                            "{{ $event->resolution_notes }}"
                                        </div>
                                    @endif
                                @else
                                    <span class="text-[11px] text-slate-400">Unacknowledged</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                @if($event->execution)
                                    <a href="{{ route('process-executions.show', $event->execution) }}" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                                        <span>Checklist</span>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400">
                                <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                No escalation events recorded for the selected period and criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($report['events']->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $report['events']->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
