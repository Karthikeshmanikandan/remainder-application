<?php

function create_file($path, $content)
{
    $dir = dirname(__DIR__.'/'.$path);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(__DIR__.'/'.$path, $content);
    echo "Created: $path\n";
}

// LAYOUT
create_file('resources/views/layouts/app.blade.php', <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Task Reminder</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow-sm mb-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="shrink-0 flex items-center font-bold text-xl text-blue-600">
                        TaskApp
                    </div>
                    <div class="hidden sm:-my-px sm:ml-6 sm:flex sm:space-x-8">
                        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Dashboard</a>
                        <a href="{{ route('projects.index') }}" class="{{ request()->routeIs('projects.*') ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Projects</a>
                        <a href="{{ route('tasks.index') }}" class="{{ request()->routeIs('tasks.*') ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Tasks</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
HTML);

// DASHBOARD VIEW
create_file('resources/views/dashboard.blade.php', <<<'HTML'
@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold text-gray-900 mb-6">Dashboard</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h3 class="text-gray-500 text-sm font-medium uppercase">Total Projects</h3>
            <p class="text-3xl font-bold text-gray-900">{{ $totalProjects }}</p>
            <p class="text-sm text-green-600 mt-1">{{ $activeProjects }} active</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h3 class="text-gray-500 text-sm font-medium uppercase">Total Tasks</h3>
            <p class="text-3xl font-bold text-gray-900">{{ $totalTasks }}</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h3 class="text-gray-500 text-sm font-medium uppercase">Task Status</h3>
            <p class="text-sm text-gray-600 mt-2">{{ $pendingTasks }} Pending</p>
            <p class="text-sm text-gray-600">{{ $inProgressTasks }} In Progress</p>
            <p class="text-sm text-gray-600">{{ $completedTasks }} Completed</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-red-200">
            <h3 class="text-red-500 text-sm font-medium uppercase">Overdue Tasks</h3>
            <p class="text-3xl font-bold text-red-600">{{ $overdueTasks }}</p>
        </div>
    </div>
@endsection
HTML);

// PROJECTS VIEWS
create_file('resources/views/projects/index.blade.php', <<<'HTML'
@extends('layouts.app')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-gray-900">Projects</h1>
    <a href="{{ route('projects.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Create Project</a>
</div>
<form method="GET" class="mb-4 flex gap-4">
    <input type="text" name="search" placeholder="Search..." value="{{ request('search') }}" class="border rounded px-3 py-2">
    <select name="status" class="border rounded px-3 py-2">
        <option value="">All Statuses</option>
        @foreach(\App\Enums\ProjectStatus::cases() as $status)
            <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>{{ $status->name }}</option>
        @endforeach
    </select>
    <button type="submit" class="bg-gray-200 px-4 py-2 rounded">Filter</button>
</form>
<div class="bg-white shadow overflow-hidden sm:rounded-md">
    <ul class="divide-y divide-gray-200">
        @foreach($projects as $project)
        <li>
            <div class="px-4 py-4 flex items-center justify-between sm:px-6">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-blue-600"><a href="{{ route('projects.show', $project) }}">{{ $project->code }} - {{ $project->name }}</a></h3>
                    <p class="text-sm text-gray-500">Status: {{ $project->status->name }} | Tasks: {{ $project->tasks_count }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('projects.edit', $project) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                    <form action="{{ route('projects.destroy', $project) }}" method="POST" onsubmit="return confirm('Sure?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                    </form>
                </div>
            </div>
        </li>
        @endforeach
    </ul>
</div>
{{ $projects->links() }}
@endsection
HTML);

create_file('resources/views/projects/create.blade.php', <<<'HTML'
@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Create Project</h1>
<form action="{{ route('projects.store') }}" method="POST" class="bg-white shadow-sm rounded p-6">
    @csrf
    @include('projects._form')
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded mt-4">Save Project</button>
</form>
@endsection
HTML);

create_file('resources/views/projects/edit.blade.php', <<<'HTML'
@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Edit Project</h1>
<form action="{{ route('projects.update', $project) }}" method="POST" class="bg-white shadow-sm rounded p-6">
    @csrf @method('PUT')
    @include('projects._form', ['project' => $project])
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded mt-4">Update Project</button>
</form>
@endsection
HTML);

create_file('resources/views/projects/_form.blade.php', <<<'HTML'
@if($errors->any())
    <div class="text-red-600 mb-4"><ul>@foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul></div>
@endif
<div class="grid grid-cols-1 gap-4">
    <div>
        <label>Code</label>
        <input type="text" name="code" value="{{ old('code', $project->code ?? '') }}" class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label>Name</label>
        <input type="text" name="name" value="{{ old('name', $project->name ?? '') }}" class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label>Description</label>
        <textarea name="description" class="w-full border rounded px-3 py-2">{{ old('description', $project->description ?? '') }}</textarea>
    </div>
    <div>
        <label>Status</label>
        <select name="status" class="w-full border rounded px-3 py-2">
            @foreach(\App\Enums\ProjectStatus::cases() as $status)
                <option value="{{ $status->value }}" {{ old('status', $project->status->value ?? '') === $status->value ? 'selected' : '' }}>{{ $status->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Start Date</label>
        <input type="date" name="start_date" value="{{ old('start_date', isset($project) && $project->start_date ? $project->start_date->format('Y-m-d') : '') }}" class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label>End Date</label>
        <input type="date" name="end_date" value="{{ old('end_date', isset($project) && $project->end_date ? $project->end_date->format('Y-m-d') : '') }}" class="w-full border rounded px-3 py-2">
    </div>
</div>
HTML);

create_file('resources/views/projects/show.blade.php', <<<'HTML'
@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Project: {{ $project->name }}</h1>
<div class="bg-white p-6 shadow-sm rounded mb-6">
    <p><strong>Code:</strong> {{ $project->code }}</p>
    <p><strong>Status:</strong> {{ $project->status->name }}</p>
    <p><strong>Progress:</strong> {{ $project->progress()['percentage'] }}%</p>
    <p><strong>Description:</strong> {{ $project->description }}</p>
</div>
@endsection
HTML);

// TASKS VIEWS
create_file('resources/views/tasks/index.blade.php', <<<'HTML'
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
HTML);

create_file('resources/views/tasks/create.blade.php', <<<'HTML'
@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Create Task</h1>
<form action="{{ route('tasks.store') }}" method="POST" class="bg-white shadow-sm rounded p-6">
    @csrf
    @include('tasks._form')
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded mt-4">Save Task</button>
</form>
@endsection
HTML);

create_file('resources/views/tasks/edit.blade.php', <<<'HTML'
@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Edit Task</h1>
<form action="{{ route('tasks.update', $task) }}" method="POST" class="bg-white shadow-sm rounded p-6">
    @csrf @method('PUT')
    @include('tasks._form', ['task' => $task])
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded mt-4">Update Task</button>
</form>
@endsection
HTML);

create_file('resources/views/tasks/_form.blade.php', <<<'HTML'
@if($errors->any())
    <div class="text-red-600 mb-4"><ul>@foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul></div>
@endif
<div class="grid grid-cols-1 gap-4">
    <div>
        <label>Code</label>
        <input type="text" name="code" value="{{ old('code', $task->code ?? '') }}" class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label>Title</label>
        <input type="text" name="title" value="{{ old('title', $task->title ?? '') }}" class="w-full border rounded px-3 py-2">
    </div>
    <div>
        <label>Description</label>
        <textarea name="description" class="w-full border rounded px-3 py-2">{{ old('description', $task->description ?? '') }}</textarea>
    </div>
    <div>
        <label>Project</label>
        <select name="project_id" class="w-full border rounded px-3 py-2">
            @foreach($projects as $p)
                <option value="{{ $p->id }}" {{ old('project_id', $task->project_id ?? '') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Assignee</label>
        <select name="assigned_to" class="w-full border rounded px-3 py-2">
            <option value="">Unassigned</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" {{ old('assigned_to', $task->assigned_to ?? '') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Status</label>
        <select name="status" class="w-full border rounded px-3 py-2">
            @foreach(\App\Enums\TaskStatus::cases() as $status)
                <option value="{{ $status->value }}" {{ old('status', $task->status->value ?? '') === $status->value ? 'selected' : '' }}>{{ $status->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Priority</label>
        <select name="priority" class="w-full border rounded px-3 py-2">
            @foreach(\App\Enums\TaskPriority::cases() as $prio)
                <option value="{{ $prio->value }}" {{ old('priority', $task->priority->value ?? '') === $prio->value ? 'selected' : '' }}>{{ $prio->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Due Date</label>
        <input type="date" name="due_date" value="{{ old('due_date', isset($task) && $task->due_date ? $task->due_date->format('Y-m-d') : '') }}" class="w-full border rounded px-3 py-2">
    </div>
</div>
HTML);

create_file('resources/views/tasks/show.blade.php', <<<'HTML'
@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Task: {{ $task->title }}</h1>
<div class="bg-white p-6 shadow-sm rounded mb-6">
    <p><strong>Code:</strong> {{ $task->code }}</p>
    <p><strong>Project:</strong> {{ $task->project->name }}</p>
    <p><strong>Status:</strong> {{ $task->status->name }}</p>
    <p><strong>Priority:</strong> {{ $task->priority->name }}</p>
    <p><strong>Assignee:</strong> {{ $task->assignee->name ?? 'None' }}</p>
    <p><strong>Due Date:</strong> {{ $task->due_date ? $task->due_date->format('Y-m-d') : 'None' }}</p>
    <p><strong>Description:</strong> {{ $task->description }}</p>
</div>
@endsection
HTML);
