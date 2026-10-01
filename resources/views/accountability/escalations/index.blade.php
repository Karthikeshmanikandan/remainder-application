@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Escalation Monitoring</h1>
            <p class="text-sm text-slate-500 mt-1">Audit log and status tracking for unconfirmed process executions escalated to management.</p>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 space-y-4">
        <form method="GET" action="{{ route('accountability.escalations.index') }}" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 mr-1">Period:</span>
                    @php
                        $presets = [
                            'all' => 'All Time',
                            'today' => 'Today',
                            'yesterday' => 'Yesterday',
                            'this_week' => 'This Week',
                            'last_7_days' => 'Last 7 Days',
                            'this_month' => 'This Month',
                        ];
                        $currentPreset = request('date_range', 'all');
                    @endphp

                    @foreach($presets as $key => $label)
                        <a href="{{ request()->fullUrlWithQuery(['date_range' => $key, 'page' => null]) }}"
                           class="px-3 py-1 text-xs font-semibold rounded-lg transition-colors {{ $currentPreset === $key ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('accountability.escalations.index') }}" class="text-xs text-slate-500 hover:text-slate-800 underline">Reset Filters</a>
                    <button type="submit" class="px-3 py-1.5 bg-slate-900 text-white text-xs font-semibold rounded-lg hover:bg-slate-800 transition">Apply</button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-2 border-t border-slate-100">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                    <select name="status" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="all">All Statuses</option>
                        <option value="triggered" {{ request('status') === 'triggered' ? 'selected' : '' }}>Triggered (Pending Ack)</option>
                        <option value="acknowledged" {{ request('status') === 'acknowledged' ? 'selected' : '' }}>Acknowledged</option>
                        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Escalation Level</label>
                    <select name="level" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="all">All Levels</option>
                        @for($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}" {{ request('level') == $i ? 'selected' : '' }}>Level {{ $i }}</option>
                        @endfor
                    </select>
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
                    <label class="block text-xs font-medium text-slate-500 mb-1">Responsible Person</label>
                    <select name="responsible_user_id" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Responsible</option>
                        @foreach($responsibleUsers as $user)
                            <option value="{{ $user->id }}" {{ request('responsible_user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Escalated Recipient</label>
                    <select name="recipient_id" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Recipients</option>
                        @foreach($recipientUsers as $mgr)
                            <option value="{{ $mgr->id }}" {{ request('recipient_id') == $mgr->id ? 'selected' : '' }}>{{ $mgr->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>
    </div>

    {{-- Escalations Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Escalation Events</h2>
            <span class="text-xs text-slate-500 font-medium">{{ $events->total() }} Total Events</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">Process & Scheduled Date</th>
                        <th class="px-5 py-3.5">Department</th>
                        <th class="px-5 py-3.5">Responsible</th>
                        <th class="px-5 py-3.5 text-center">Level</th>
                        <th class="px-5 py-3.5">Triggered At</th>
                        <th class="px-5 py-3.5">Escalated To</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5">Acknowledgement</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($events as $event)
                        @php
                            $execution = $event->execution;
                            $process = $execution?->process;
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-4">
                                <div class="font-bold text-slate-900">
                                    {{ $process?->name ?? 'Unknown Process' }}
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    Due: {{ $execution?->scheduled_date?->format('d M Y') ?? 'N/A' }} {{ $execution?->scheduled_time ?? '' }}
                                </div>
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-600">
                                {{ $process?->department?->name ?? '—' }}
                            </td>
                            <td class="px-5 py-4 text-xs font-semibold text-slate-800">
                                {{ $process?->responsibleUser?->name ?? '—' }}
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold
                                    @if($event->level === 1) bg-amber-100 text-amber-800 border border-amber-200
                                    @elseif($event->level === 2) bg-orange-100 text-orange-800 border border-orange-200
                                    @elseif($event->level >= 3) bg-rose-100 text-rose-800 border border-rose-200
                                    @endif">
                                    Level {{ $event->level }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-600 whitespace-nowrap">
                                <div>{{ $event->triggered_at?->format('d M Y, h:i A') ?? '—' }}</div>
                                <div class="text-[11px] text-slate-400">{{ $event->triggered_at?->diffForHumans() }}</div>
                            </td>
                            <td class="px-5 py-4 text-xs font-medium text-slate-800">
                                {{ $event->rule?->escalateToUser?->name ?? '—' }}
                            </td>
                            <td class="px-5 py-4 text-center">
                                @if($event->isTriggered())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        Triggered
                                    </span>
                                @elseif($event->isAcknowledged())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                        Acknowledged
                                    </span>
                                @elseif($event->isResolved())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Resolved
                                    </span>
                                @elseif($event->isCancelled())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                        Cancelled
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-600">
                                @if($event->acknowledged_at)
                                    <div class="font-medium text-slate-800">{{ $event->acknowledgedBy?->name ?? 'Admin' }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $event->acknowledged_at->format('d M, h:i A') }}</div>
                                @else
                                    <span class="text-slate-400 italic text-[11px]">Unacknowledged</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap text-xs space-x-2">
                                @if($execution)
                                    <a href="{{ route('process-executions.show', $execution) }}" class="text-blue-600 hover:text-blue-800 font-semibold hover:underline">
                                        View Checklist
                                    </a>
                                @endif

                                @if($event->isTriggered() && (auth()->user()->isAdmin() || auth()->id() === $event->rule?->escalate_to_user_id))
                                    <form method="POST" action="{{ route('accountability.escalations.acknowledge', $event) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 bg-blue-600 text-white text-[11px] font-bold rounded hover:bg-blue-700 transition">
                                            Acknowledge
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-500">
                                <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-base font-semibold text-slate-700">No Escalation Events Found</p>
                                <p class="text-xs text-slate-400 mt-1">There are no process execution escalations matching the selected criteria.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($events->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50">
                {{ $events->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
