<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-medium text-gray-700">Code</label>
        <input type="text" name="code" value="{{ old('code', $recurringTask->code ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">
        @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Title</label>
        <input type="text" name="title" value="{{ old('title', $recurringTask->title ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">
        @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700">Description</label>
        <textarea name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">{{ old('description', $recurringTask->description ?? '') }}</textarea>
        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Project</label>
        <select name="project_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">
            <option value="">Select Project</option>
            @foreach($projects as $project)
                <option value="{{ $project->id }}" {{ old('project_id', $recurringTask->project_id ?? '') == $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
            @endforeach
        </select>
        @error('project_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Assigned To</label>
        <select name="assigned_to" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">
            <option value="">Unassigned</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" {{ old('assigned_to', $recurringTask->assigned_to ?? '') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
            @endforeach
        </select>
        @error('assigned_to') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Priority</label>
        <select name="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">
            @foreach(App\Enums\TaskPriority::cases() as $priority)
                <option value="{{ $priority->value }}" {{ old('priority', $recurringTask->priority->value ?? 'medium') == $priority->value ? 'selected' : '' }}>{{ ucfirst($priority->value) }}</option>
            @endforeach
        </select>
        @error('priority') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    
    <div>
        <label class="block text-sm font-medium text-gray-700">Frequency</label>
        <select name="frequency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">
            @foreach(App\Enums\RecurrenceFrequency::cases() as $freq)
                <option value="{{ $freq->value }}" {{ old('frequency', $recurringTask->frequency->value ?? 'daily') == $freq->value ? 'selected' : '' }}>{{ ucfirst($freq->value) }}</option>
            @endforeach
        </select>
        @error('frequency') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Interval (e.g., every 1 day/week/month)</label>
        <input type="number" name="interval" min="1" value="{{ old('interval', $recurringTask->interval ?? 1) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">
        @error('interval') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    
    <div>
        <label class="block text-sm font-medium text-gray-700">Starts At</label>
        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', isset($recurringTask->starts_at) ? $recurringTask->starts_at->format('Y-m-d\TH:i') : '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">
        @error('starts_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    
    <div>
        <label class="block text-sm font-medium text-gray-700">Ends At (Optional)</label>
        <input type="datetime-local" name="ends_at" value="{{ old('ends_at', isset($recurringTask->ends_at) ? $recurringTask->ends_at->format('Y-m-d\TH:i') : '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">
        @error('ends_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <div class="flex items-start">
            <div class="flex h-5 items-center">
                <input type="hidden" name="auto_create_reminder" value="0">
                <input id="auto_create_reminder" name="auto_create_reminder" type="checkbox" value="1" {{ old('auto_create_reminder', $recurringTask->auto_create_reminder ?? false) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            </div>
            <div class="ml-3 text-sm">
                <label for="auto_create_reminder" class="font-medium text-gray-700">Auto-create Reminder</label>
                <p class="text-gray-500">Automatically create a reminder for generated tasks.</p>
            </div>
        </div>
        @error('auto_create_reminder') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    
    <div>
        <label class="block text-sm font-medium text-gray-700">Reminder Offset (Minutes before due)</label>
        <input type="number" name="reminder_offset_minutes" min="0" value="{{ old('reminder_offset_minutes', $recurringTask->reminder_offset_minutes ?? 60) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">
        @error('reminder_offset_minutes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded shadow-sm hover:bg-blue-700">Save</button>
    <a href="{{ route('recurring-tasks.index') }}" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded shadow-sm hover:bg-gray-50">Cancel</a>
</div>
