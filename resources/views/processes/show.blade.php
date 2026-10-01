@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    {{-- Top breadcrumbs & action bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('processes.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 flex items-center gap-1 mb-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Back to Processes
            </a>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $process->name }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                    {{ $process->department->name }}
                </span>
            </div>
            <p class="text-xs text-slate-400 font-mono mt-1">Code: {{ $process->code }}</p>
        </div>

        @if(! auth()->user()->isEmployee())
            <div class="flex items-center gap-2">
                <a href="{{ route('processes.edit', $process) }}" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 shadow-sm transition-colors">
                    Edit Process
                </a>
                @if($process->isActive())
                    <form method="POST" action="{{ route('processes.pause', $process) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="px-4 py-2 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg text-sm font-medium hover:bg-amber-100 transition-colors">
                            Pause
                        </button>
                    </form>
                @elseif($process->isPaused())
                    <form method="POST" action="{{ route('processes.resume', $process) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="px-4 py-2 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-sm font-medium hover:bg-emerald-100 transition-colors">
                            Resume
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    {{-- Overview Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Schedule Card --}}
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Schedule & Reminders</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Frequency:</span>
                    <span class="font-semibold text-slate-800 capitalize">{{ $process->frequency->value }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Reminder Time:</span>
                    <span class="font-semibold text-slate-800">{{ $process->reminder_time ?? '17:00' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Next Due:</span>
                    <span class="font-semibold text-blue-600">{{ $process->next_run_at ? $process->next_run_at->format('d M Y, H:i') : '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Status:</span>
                    <span class="font-semibold text-slate-800 capitalize">{{ $process->status->value }}</span>
                </div>
            </div>
        </div>

        {{-- Responsible Person Card --}}
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Responsible Person</h3>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center text-sm">
                    {{ strtoupper(substr($process->responsibleUser->name, 0, 1)) }}
                </div>
                <div>
                    <h4 class="font-semibold text-slate-800 text-sm">{{ $process->responsibleUser->name }}</h4>
                    <p class="text-xs text-slate-400">{{ $process->responsibleUser->email }}</p>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 mt-1">
                        {{ $process->responsibleUser->role->value }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Notifications & Escalation Card --}}
        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Accountability & Channels</h3>
            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">In-App Notification:</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $process->in_app_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                        {{ $process->in_app_enabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Telegram Bot:</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $process->telegram_enabled ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-500' }}">
                        {{ $process->telegram_enabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Escalation Policy:</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $process->activeEscalationRules->isNotEmpty() ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-500' }}">
                        {{ $process->activeEscalationRules->count() }} Level(s) Active
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Escalation Rules Configuration Card (if rules exist) --}}
    @if($process->escalationRules->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    Configured Escalation Rules
                </h2>
                <span class="text-xs text-slate-500">{{ $process->activeEscalationRules->count() }} active of {{ $process->escalationRules->count() }}</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-1">
                @foreach($process->escalationRules->sortBy('level') as $rule)
                    <div class="p-3 rounded-lg border {{ $rule->is_active ? 'border-amber-200 bg-amber-50/40' : 'border-slate-200 bg-slate-50 text-slate-400' }}">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase text-slate-800">Level {{ $rule->level }}</span>
                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded {{ $rule->is_active ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-600' }}">
                                {{ $rule->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </div>
                        <div class="text-xs text-slate-600 mt-2 space-y-1">
                            <div>Delay: <strong class="text-slate-800">{{ $rule->delay_minutes }} minutes</strong></div>
                            <div>Escalate To: <strong class="text-slate-800">{{ $rule->escalateToUser?->name ?? 'Unassigned' }}</strong></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Main 2 Columns: Questions & History --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        {{-- Checklist Questions --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="text-base font-bold text-slate-900">Checklist Questions ({{ $process->items->count() }})</h2>
                <span class="text-xs text-slate-500 font-medium">{{ $process->enabledItems->count() }} Active</span>
            </div>

            <div class="space-y-3 divide-y divide-slate-100">
                @forelse($process->items as $index => $item)
                    <div class="pt-3 first:pt-0 flex items-start gap-3">
                        <span class="text-xs font-mono font-bold text-slate-400 w-6 pt-0.5">{{ sprintf('%02d', $index + 1) }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium {{ $item->is_enabled ? 'text-slate-800' : 'text-slate-400 line-through' }}">
                                {{ $item->question }}
                            </p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[10px] font-mono uppercase bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">{{ $item->response_type->value }}</span>
                                @if($item->is_required)
                                    <span class="text-[10px] font-semibold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">Required</span>
                                @endif
                                @if(! $item->is_enabled)
                                    <span class="text-[10px] font-semibold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">Disabled</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 py-4 text-center">No questions configured.</p>
                @endforelse
            </div>
        </div>

        {{-- Recent Executions History --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="text-base font-bold text-slate-900">Recent Executions</h2>
                <a href="{{ route('process-executions.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">View All</a>
            </div>

            <div class="space-y-3">
                @forelse($process->executions as $exec)
                    <div class="p-3.5 rounded-lg border border-slate-100 hover:border-slate-300 hover:bg-slate-50/50 transition-colors flex items-center justify-between gap-4">
                        <div>
                            <div class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                                <span>{{ $exec->scheduled_for->format('d M Y') }}</span>
                                @if($exec->escalationEvents->isNotEmpty())
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                                        Escalated (L{{ $exec->escalationEvents->max('level') }})
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-400 mt-0.5">
                                @if($exec->isCompleted() && $exec->completedBy)
                                    Confirmed by {{ $exec->completedBy->name }} at {{ $exec->completed_at?->format('H:i') }}
                                @else
                                    Scheduled for {{ $exec->scheduled_for->format('H:i') }}
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="text-xs font-mono font-medium text-slate-500">
                                {{ $exec->answeredCount() }} / {{ $exec->totalCount() }}
                            </span>
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
                            <a href="{{ route('process-executions.show', $exec) }}" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">Open →</a>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 py-4 text-center">No executions generated yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Configuration & Governance Audit History --}}
        @if(isset($auditLogs) && (! auth()->user()->isEmployee() || $process->responsible_user_id === auth()->id()))
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Configuration and Governance History</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Chronological record of process configuration modifications and reassignments.</p>
                </div>
                <span class="text-xs text-slate-500 font-medium">{{ $auditLogs->total() }} audit entries</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3">Date / Time</th>
                            <th class="py-2.5 px-3">Actor</th>
                            <th class="py-2.5 px-3">Action</th>
                            <th class="py-2.5 px-3">Summary of Change</th>
                            @if(auth()->user()->isAdmin())
                                <th class="py-2.5 px-3 text-right">Inspect</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($auditLogs as $log)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <div class="font-medium text-slate-900">{{ $log->created_at->format('d M Y, H:i') }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $log->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <div class="font-medium text-slate-800">{{ $log->user->name ?? 'System' }}</div>
                                </td>
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $log->action->badgeClass() }}">
                                        {{ $log->action->value }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="text-slate-800">{{ $log->summary }}</div>
                                </td>
                                @if(auth()->user()->isAdmin())
                                    <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                        <a href="{{ route('admin.audit-logs.show', $log) }}" class="text-blue-600 hover:underline font-semibold text-[11px]">
                                            View Diff &rarr;
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-400 italic">
                                    No configuration modifications recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($auditLogs->hasPages())
                <div class="pt-3 border-t border-slate-100">
                    {{ $auditLogs->links() }}
                </div>
            @endif
        </div>
        @endif

    </div>
</div>
@endsection
