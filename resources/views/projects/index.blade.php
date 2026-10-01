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