@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('tasks.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to Tasks</a>
            <h1 class="text-2xl font-semibold text-gray-900 mt-1">
                {{ $task->title }}
                @if($task->isOverdue())
                    <span class="ml-2 text-sm text-red-500 font-semibold">(OVERDUE)</span>
                @endif
            </h1>
            <p class="text-sm text-gray-500">{{ $task->code }}</p>
        </div>
        <a href="{{ route('tasks.edit', $task) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm">Edit Task</a>
    </div>

    {{-- Task Details --}}
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase mb-4">Task Details</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
            <div>
                <dt class="text-gray-500">Project</dt>
                <dd class="font-medium text-gray-900">
                    <a href="{{ route('projects.show', $task->project) }}" class="text-blue-600 hover:underline">{{ $task->project->name }}</a>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Status</dt>
                @php
                    $statusColors = ['pending'=>'bg-yellow-100 text-yellow-800','in_progress'=>'bg-blue-100 text-blue-800','completed'=>'bg-green-100 text-green-800','cancelled'=>'bg-gray-100 text-gray-600'];
                @endphp
                <dd>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$task->status->value] ?? '' }}">
                        {{ ucfirst(str_replace('_', ' ', $task->status->value)) }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Priority</dt>
                @php
                    $priorityColors = ['urgent'=>'bg-red-100 text-red-800','high'=>'bg-orange-100 text-orange-800','medium'=>'bg-yellow-100 text-yellow-800','low'=>'bg-gray-100 text-gray-600'];
                @endphp
                <dd>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $priorityColors[$task->priority->value] ?? '' }}">
                        {{ ucfirst($task->priority->value) }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Assigned To</dt>
                <dd class="font-medium text-gray-900">{{ $task->assignee?->name ?? 'Unassigned' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Created By</dt>
                <dd class="font-medium text-gray-900">{{ $task->creator?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Due Date</dt>
                <dd class="font-medium {{ $task->isOverdue() ? 'text-red-600' : 'text-gray-900' }}">
                    {{ $task->due_date?->format('Y-m-d H:i') ?? '—' }}
                </dd>
            </div>
            @if($task->recurringTask)
            <div>
                <dt class="text-gray-500">Source</dt>
                <dd class="font-medium">
                    <a href="{{ route('recurring-tasks.show', $task->recurringTask) }}" class="text-blue-600 hover:underline">Recurring Task</a>
                </dd>
            </div>
            @endif
            @if($task->completed_at)
            <div>
                <dt class="text-gray-500">Completed At</dt>
                <dd class="font-medium text-green-600">{{ $task->completed_at->format('Y-m-d H:i') }}</dd>
            </div>
            @endif
            <div class="sm:col-span-2">
                <dt class="text-gray-500">Description</dt>
                <dd class="font-medium text-gray-900 whitespace-pre-wrap mt-1">{{ $task->description ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Reminders Section --}}
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 mb-6">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-800">Reminders</h2>
        </div>

        {{-- Add Reminder Form --}}
        <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-sm font-medium text-gray-700 mb-3">Add Reminder</h3>
            <form method="POST" action="{{ route('tasks.reminders.store', $task) }}" class="flex flex-wrap gap-3 items-end">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Recipient</label>
                    <select name="user_id" class="border rounded px-3 py-2 text-sm">
                        @foreach($users as $user)
                            <option value="{{ $user->id }}"
                                {{ (auth()->user()->isEmployee() && $user->id === auth()->id()) ? 'selected' : '' }}
                                {{ (!auth()->user()->isEmployee() && $task->assigned_to == $user->id) ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Remind At</label>
                    <input type="datetime-local" name="remind_at" class="border rounded px-3 py-2 text-sm"
                        min="{{ now()->addMinutes(1)->format('Y-m-d\TH:i') }}">
                </div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">
                    Set Reminder
                </button>
            </form>
            @error('remind_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            @error('user_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Reminder List --}}
        @if($task->reminders->isEmpty())
            <p class="text-sm text-gray-400 px-5 py-4">No reminders set for this task.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($task->reminders as $reminder)
                    @php
                        $rStatusColors = ['pending'=>'bg-yellow-100 text-yellow-700','triggered'=>'bg-green-100 text-green-700','cancelled'=>'bg-gray-100 text-gray-500'];
                        $rColor = $rStatusColors[$reminder->status->value] ?? 'bg-gray-100 text-gray-500';
                    @endphp
                    <li class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-800">
                                {{ $reminder->remind_at->format('Y-m-d H:i') }}
                            </p>
                            <p class="text-xs text-gray-500">
                                Recipient: {{ $reminder->user->name }}
                                @if($reminder->triggered_at)
                                    &bull; Triggered: {{ $reminder->triggered_at->format('Y-m-d H:i') }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $rColor }}">
                                {{ ucfirst($reminder->status->value) }}
                            </span>
                            @if($reminder->isPending() && (auth()->user()->isAdmin() || $reminder->user_id === auth()->id()))
                                <form method="POST" action="{{ route('tasks.reminders.destroy', [$task, $reminder]) }}" onsubmit="return confirm('Cancel this reminder?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-900">Cancel</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Actions --}}
    <div class="flex gap-3">
        <a href="{{ route('tasks.edit', $task) }}" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 text-sm">Edit</a>
        <form action="{{ route('tasks.destroy', $task) }}" method="POST" onsubmit="return confirm('Delete this task?');">
            @csrf @method('DELETE')
            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm">Delete</button>
        </form>
    </div>
@endsection