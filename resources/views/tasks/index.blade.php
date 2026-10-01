@extends('layouts.app')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-gray-900">Tasks</h1>
    <a href="{{ route('tasks.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Create Task</a>
</div>
<form method="GET" class="mb-4 flex gap-4">
    <input type="text" name="search" placeholder="Search..." value="{{ request('search') }}" class="border rounded px-3 py-2">
    <select name="status" class="border rounded px-3 py-2">
        <option value="">All Statuses</option>
        @foreach(\App\Enums\TaskStatus::cases() as $status)
            <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>{{ $status->name }}</option>
        @endforeach
    </select>
    <button type="submit" class="bg-gray-200 px-4 py-2 rounded">Filter</button>
</form>
<div class="bg-white shadow overflow-hidden sm:rounded-md">
    <ul class="divide-y divide-gray-200">
        @foreach($tasks as $task)
        <li>
            <div class="px-4 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-blue-600">
                        <a href="{{ route('tasks.show', $task) }}">{{ $task->code }} - {{ $task->title }}</a>
                        @if($task->isOverdue()) <span class="text-red-500 text-xs font-bold">(OVERDUE)</span> @endif
                    </h3>
                    <p class="text-sm text-gray-500">Project: {{ $task->project->name ?? 'N/A' }} | Assignee: {{ $task->assignee->name ?? 'Unassigned' }} | Status: {{ $task->status->name }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('tasks.edit', $task) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                    <form action="{{ route('tasks.destroy', $task) }}" method="POST" onsubmit="return confirm('Sure?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                    </form>
                </div>
            </div>
        </li>
        @endforeach
    </ul>
</div>
{{ $tasks->links() }}
@endsection