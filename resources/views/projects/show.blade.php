@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('projects.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to Projects</a>
            <h1 class="text-2xl font-semibold text-gray-900 mt-1">{{ $project->name }}</h1>
            <p class="text-sm text-gray-500">{{ $project->code }}</p>
        </div>
        <a href="{{ route('projects.edit', $project) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm">Edit Project</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-white shadow-sm rounded-lg border border-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase mb-3">Project Details</h2>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-500">Status</dt>
                    <dd class="font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $project->status->value)) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Start Date</dt>
                    <dd class="font-medium text-gray-900">{{ $project->start_date?->format('Y-m-d') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">End Date</dt>
                    <dd class="font-medium text-gray-900">{{ $project->end_date?->format('Y-m-d') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Created By</dt>
                    <dd class="font-medium text-gray-900">{{ $project->creator?->name ?? '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-gray-500">Description</dt>
                    <dd class="font-medium text-gray-900 whitespace-pre-wrap">{{ $project->description ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase mb-3">Progress</h2>
            @php $progress = $project->progress(); @endphp
            <p class="text-4xl font-bold text-blue-600">{{ $progress['percentage'] }}%</p>
            <p class="text-sm text-gray-500 mt-1">{{ $progress['completed'] }} of {{ $progress['total'] }} tasks complete</p>
            <div class="mt-3 w-full bg-gray-200 rounded-full h-2">
                <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $progress['percentage'] }}%"></div>
            </div>
        </div>
    </div>

    <div class="bg-white shadow-sm rounded-lg border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-800">Tasks ({{ $project->tasks->count() }})</h2>
            <a href="{{ route('tasks.create') }}" class="text-sm bg-blue-600 text-white px-3 py-1.5 rounded hover:bg-blue-700">Add Task</a>
        </div>
        @if($project->tasks->isEmpty())
            <p class="text-sm text-gray-500 px-5 py-4">No tasks yet.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($project->tasks as $task)
                    <li class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <a href="{{ route('tasks.show', $task) }}" class="text-sm font-medium text-blue-600 hover:underline">
                                {{ $task->code }} — {{ $task->title }}
                                @if($task->isOverdue()) <span class="text-xs text-red-500 font-semibold ml-1">(OVERDUE)</span> @endif
                            </a>
                            <p class="text-xs text-gray-400">
                                Assignee: {{ $task->assignee?->name ?? 'Unassigned' }}
                                &bull; Due: {{ $task->due_date?->format('Y-m-d') ?? '—' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @php
                                $priorityColors = ['urgent'=>'bg-red-100 text-red-700','high'=>'bg-orange-100 text-orange-700','medium'=>'bg-yellow-100 text-yellow-700','low'=>'bg-gray-100 text-gray-600'];
                                $statusColors = ['pending'=>'bg-yellow-100 text-yellow-700','in_progress'=>'bg-blue-100 text-blue-700','completed'=>'bg-green-100 text-green-700','cancelled'=>'bg-gray-100 text-gray-600'];
                            @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $priorityColors[$task->priority->value] ?? '' }}">{{ ucfirst($task->priority->value) }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $statusColors[$task->status->value] ?? '' }}">{{ ucfirst(str_replace('_',' ',$task->status->value)) }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection