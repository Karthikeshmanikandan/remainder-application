@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-gray-900">Recurring Tasks</h1>
    @if(!auth()->user()->isEmployee())
        <a href="{{ route('recurring-tasks.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Create Recurring Task</a>
    @endif
</div>
<div class="bg-white shadow overflow-hidden sm:rounded-md">
    <ul class="divide-y divide-gray-200">
        @forelse($recurringTasks as $task)
        <li>
            <div class="px-4 py-4 flex items-center justify-between sm:px-6">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-blue-600">
                        <a href="{{ route('recurring-tasks.show', $task) }}" class="hover:underline">{{ $task->title }} ({{ $task->code }})</a>
                    </h3>
                    <p class="text-sm text-gray-500">
                        Project: {{ $task->project->name ?? '—' }} | 
                        Assignee: {{ $task->assignee->name ?? 'Unassigned' }} | 
                        Next Run: {{ $task->next_run_at?->format('Y-m-d H:i') ?? '—' }}
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                        Frequency: every {{ $task->interval }} {{ $task->frequency->value }}
                        | Status: 
                        @php
                            $colors = ['active' => 'text-green-600', 'paused' => 'text-yellow-600', 'completed' => 'text-blue-600', 'cancelled' => 'text-red-600'];
                        @endphp
                        <span class="font-medium {{ $colors[$task->status->value] ?? 'text-gray-500' }}">{{ ucfirst($task->status->value) }}</span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('recurring-tasks.show', $task) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">View</a>
                </div>
            </div>
        </li>
        @empty
            <li class="px-4 py-4 text-gray-500">No recurring tasks found.</li>
        @endforelse
    </ul>
</div>
<div class="mt-4">
    {{ $recurringTasks->links() }}
</div>
@endsection
