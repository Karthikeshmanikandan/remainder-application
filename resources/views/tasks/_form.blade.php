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
        <label class="block text-sm font-medium text-slate-700">Assignee (Web User)</label>
        <select name="assigned_to" class="w-full border rounded px-3 py-2 text-sm">
            <option value="">-- No Web User Assigned --</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" {{ old('assigned_to', $task->assigned_to ?? '') == $u->id ? 'selected' : '' }}>{{ $u->name }} (Web User)</option>
            @endforeach
        </select>
    </div>
    @if(isset($telegramEmployees) && $telegramEmployees->isNotEmpty())
    <div>
        <label class="block text-sm font-medium text-slate-700">Assignee (Telegram-Only Employee)</label>
        <select name="assigned_telegram_employee_id" class="w-full border rounded px-3 py-2 text-sm">
            <option value="">-- No Telegram Employee Assigned --</option>
            @foreach($telegramEmployees as $te)
                <option value="{{ $te->id }}" {{ old('assigned_telegram_employee_id', $task->assigned_telegram_employee_id ?? '') == $te->id ? 'selected' : '' }}>
                    {{ $te->name }} ({{ $te->employee_code }})
                </option>
            @endforeach
        </select>
        <p class="text-xs text-slate-500 mt-1">Assign to an operational employee who receives reminders and updates status purely via Telegram.</p>
    </div>
    @endif
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