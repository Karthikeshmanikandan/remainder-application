@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Business Processes</h1>
            <p class="text-sm text-slate-500 mt-1">Manage departmental process templates, scheduled checklists, and accountability workflows.</p>
        </div>
        @if(! auth()->user()->isEmployee())
            <a href="{{ route('processes.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-blue-700 shadow-sm transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                + Add Process
            </a>
        @endif
    </div>

    {{-- Filters --}}
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <form method="GET" action="{{ route('processes.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Process name or code..." class="w-full text-sm border-slate-200 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 border">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Department</label>
                <select name="department_id" class="w-full text-sm border-slate-200 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 border">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Status</label>
                <select name="status" class="w-full text-sm border-slate-200 rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 border">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="paused" {{ request('status') == 'paused' ? 'selected' : '' }}>Paused</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium py-2 px-4 rounded-lg transition-colors">
                    Filter
                </button>
                <a href="{{ route('processes.index') }}" class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Process Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5">Process / Department</th>
                        <th class="px-6 py-3.5">Responsible</th>
                        <th class="px-6 py-3.5">Schedule</th>
                        <th class="px-6 py-3.5">Next Run</th>
                        <th class="px-6 py-3.5">Channels</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($processes as $process)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-6 py-4">
                                <a href="{{ route('processes.show', $process) }}" class="font-semibold text-slate-900 hover:text-blue-600">
                                    {{ $process->name }}
                                </a>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-blue-50 text-blue-700">
                                        {{ $process->department->name }}
                                    </span>
                                    <span class="text-xs text-slate-400 font-mono">{{ $process->code }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-800">{{ $process->responsibleUser->name }}</div>
                                <div class="text-xs text-slate-400">{{ $process->responsibleUser->role->value }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="capitalize font-medium">{{ $process->frequency->value }}</span>
                                @if($process->interval > 1) (every {{ $process->interval }}) @endif
                                <div class="text-xs text-slate-400 mt-0.5">Time: {{ $process->reminder_time ?? '17:00' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @if($process->next_run_at)
                                    <div class="text-slate-800 font-medium">{{ $process->next_run_at->format('d M Y') }}</div>
                                    <div class="text-xs text-slate-400">{{ $process->next_run_at->format('H:i') }}</div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-1.5">
                                    @if($process->in_app_enabled)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700" title="In-App Notification">App</span>
                                    @endif
                                    @if($process->telegram_enabled)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-sky-100 text-sky-700" title="Telegram Notification">Telegram</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $statusClasses = [
                                        'active' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'paused' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'completed' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusClasses[$process->status->value] ?? 'bg-slate-100 text-slate-600' }}">
                                    {{ ucfirst($process->status->value) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('processes.show', $process) }}" class="text-blue-600 hover:text-blue-800 font-medium text-xs">View</a>
                                @if(! auth()->user()->isEmployee())
                                    <a href="{{ route('processes.edit', $process) }}" class="text-slate-600 hover:text-slate-800 font-medium text-xs">Edit</a>
                                    @if($process->isActive())
                                        <form method="POST" action="{{ route('processes.pause', $process) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-amber-600 hover:text-amber-800 font-medium text-xs">Pause</button>
                                        </form>
                                    @elseif($process->isPaused())
                                        <form method="POST" action="{{ route('processes.resume', $process) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-emerald-600 hover:text-emerald-800 font-medium text-xs">Resume</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <p class="text-base font-semibold text-slate-700">No processes configured yet</p>
                                <p class="text-sm text-slate-500 mt-1">Get started by selecting a departmental template.</p>
                                @if(! auth()->user()->isEmployee())
                                    <a href="{{ route('processes.create') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                                        + Add Your First Process
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($processes->hasPages())
            <div class="px-6 py-4 border-t border-slate-100">
                {{ $processes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
