@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold text-gray-900 mb-6">My Reminders</h1>

    {{-- Upcoming --}}
    <div class="mb-8">
        <h2 class="text-base font-semibold text-gray-700 mb-3">Upcoming ({{ $upcomingReminders->count() }})</h2>
        @if($upcomingReminders->isEmpty())
            <p class="text-sm text-gray-400">No upcoming reminders.</p>
        @else
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm divide-y divide-gray-100">
                @foreach($upcomingReminders as $reminder)
                    <div class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <a href="{{ route('tasks.show', $reminder->task) }}" class="text-sm font-medium text-blue-600 hover:underline">
                                {{ $reminder->task->title }}
                            </a>
                            <p class="text-xs text-gray-500">
                                Project: {{ $reminder->task->project->name ?? '—' }}
                                &bull; Remind at: <strong>{{ $reminder->remind_at->format('Y-m-d H:i') }}</strong>
                            </p>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700">Pending</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Triggered --}}
    <div class="mb-8">
        <h2 class="text-base font-semibold text-gray-700 mb-3">Triggered ({{ $triggeredReminders->count() }})</h2>
        @if($triggeredReminders->isEmpty())
            <p class="text-sm text-gray-400">No triggered reminders.</p>
        @else
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm divide-y divide-gray-100">
                @foreach($triggeredReminders as $reminder)
                    <div class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <a href="{{ route('tasks.show', $reminder->task) }}" class="text-sm font-medium text-blue-600 hover:underline">
                                {{ $reminder->task->title }}
                            </a>
                            <p class="text-xs text-gray-500">
                                Project: {{ $reminder->task->project->name ?? '—' }}
                                &bull; Triggered: {{ $reminder->triggered_at?->format('Y-m-d H:i') ?? '—' }}
                            </p>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Triggered</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Cancelled --}}
    <div>
        <h2 class="text-base font-semibold text-gray-700 mb-3">Cancelled ({{ $cancelledReminders->count() }})</h2>
        @if($cancelledReminders->isEmpty())
            <p class="text-sm text-gray-400">No cancelled reminders.</p>
        @else
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm divide-y divide-gray-100">
                @foreach($cancelledReminders as $reminder)
                    <div class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <a href="{{ route('tasks.show', $reminder->task) }}" class="text-sm font-medium text-blue-600 hover:underline">
                                {{ $reminder->task->title }}
                            </a>
                            <p class="text-xs text-gray-500">Project: {{ $reminder->task->project->name ?? '—' }}</p>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Cancelled</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
