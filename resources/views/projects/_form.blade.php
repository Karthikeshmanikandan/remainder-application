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