@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <a href="{{ route('recurring-tasks.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to Recurring Tasks</a>
        <h1 class="text-2xl font-semibold text-gray-900 mt-1">
            {{ $recurringTask->title }}
            <span class="ml-2 text-sm text-gray-500 font-normal">({{ $recurringTask->code }})</span>
        </h1>
        <div class="mt-1 flex items-center space-x-2">
            @php $colors = ['active' => 'bg-green-100 text-green-800', 'paused' => 'bg-yellow-100 text-yellow-800', 'completed' => 'bg-blue-100 text-blue-800', 'cancelled' => 'bg-red-100 text-red-800']; @endphp
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colors[$recurringTask->status->value] ?? '' }}">
                {{ ucfirst($recurringTask->status->value) }}
            </span>
        </div>
    </div>
    
    @if(!auth()->user()->isEmployee())
    <div class="flex space-x-3">
        <a href="{{ route('recurring-tasks.edit', $recurringTask) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm">Edit</a>
        
        @if($recurringTask->isActive())
            <form method="POST" action="{{ route('recurring-tasks.pause', $recurringTask) }}">
                @csrf @method('PATCH')
                <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded-md hover:bg-yellow-600 text-sm">Pause</button>
            </form>
        @elseif($recurringTask->isPaused())
            <form method="POST" action="{{ route('recurring-tasks.resume', $recurringTask) }}">
                @csrf @method('PATCH')
                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 text-sm">Resume</button>
            </form>
        @endif

        @if(!$recurringTask->isCancelled())
            <form method="POST" action="{{ route('recurring-tasks.cancel', $recurringTask) }}" onsubmit="return confirm('Cancel this recurring task?');">
                @csrf @method('PATCH')
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 text-sm">Cancel</button>
            </form>
        @endif
    </div>
    @endif
</div>

<div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 mb-6">
    <h2 class="text-sm font-semibold text-gray-500 uppercase mb-4">Definition Details</h2>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
        <div><dt class="text-gray-500">Project</dt><dd class="font-medium">{{ $recurringTask->project->name }}</dd></div>
        <div><dt class="text-gray-500">Assignee</dt><dd class="font-medium">{{ $recurringTask->assignee->name ?? 'Unassigned' }}</dd></div>
        <div><dt class="text-gray-500">Priority</dt><dd class="font-medium">{{ ucfirst($recurringTask->priority->value) }}</dd></div>
        <div><dt class="text-gray-500">Recurrence</dt><dd class="font-medium">Every {{ $recurringTask->interval }} {{ $recurringTask->frequency->value }}</dd></div>
        <div><dt class="text-gray-500">Next Run</dt><dd class="font-medium">{{ $recurringTask->next_run_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
        <div><dt class="text-gray-500">Last Run</dt><dd class="font-medium">{{ $recurringTask->last_run_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
        <div><dt class="text-gray-500">Starts At</dt><dd class="font-medium">{{ $recurringTask->starts_at->format('Y-m-d H:i') }}</dd></div>
        <div><dt class="text-gray-500">Ends At</dt><dd class="font-medium">{{ $recurringTask->ends_at?->format('Y-m-d H:i') ?? 'Never' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-gray-500">Description</dt><dd class="font-medium whitespace-pre-wrap">{{ $recurringTask->description ?: '—' }}</dd></div>
        <div class="sm:col-span-2">
            <dt class="text-gray-500">Auto Reminder</dt>
            <dd class="font-medium">
                {{ $recurringTask->auto_create_reminder ? 'Yes (' . $recurringTask->reminder_offset_minutes . ' min before due)' : 'No' }}
            </dd>
        </div>
    </dl>
</div>

<div class="bg-white shadow-sm rounded-lg border border-gray-200">
    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-800">Recent Generated Tasks</h2>
    </div>
    @if($recentOccurrences->isEmpty())
        <p class="text-sm text-gray-500 px-5 py-4">No tasks generated yet.</p>
    @else
        <ul class="divide-y divide-gray-100">
            @foreach($recentOccurrences as $occ)
                <li class="px-5 py-3 flex items-center justify-between">
                    <div>
                        <a href="{{ route('tasks.show', $occ->task) }}" class="text-sm font-medium text-blue-600 hover:underline">{{ $occ->task->title }}</a>
                        <p class="text-xs text-gray-500">Scheduled for: {{ $occ->scheduled_for->format('Y-m-d H:i') }}</p>
                    </div>
                    <span class="text-xs text-gray-500">{{ ucfirst($occ->task->status->value) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
