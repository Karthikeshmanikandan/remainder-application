@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('admin.process-templates.index') }}" class="hover:underline">Process Templates</a>
                <span>/</span>
                <a href="{{ route('admin.process-templates.show', $processTemplate) }}" class="hover:underline">{{ $processTemplate->name }}</a>
                <span>/</span>
                <span>Edit</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Edit Blueprint Template</h1>
        </div>

        <a href="{{ route('admin.process-templates.show', $processTemplate) }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
            Cancel
        </a>
    </div>

    {{-- Form --}}
    <form method="POST" action="{{ route('admin.process-templates.update', $processTemplate) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">1. Blueprint Metadata</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Template Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $processTemplate->name) }}" required class="w-full text-sm border-slate-200 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Blueprint Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" value="{{ old('code', $processTemplate->code) }}" required class="w-full text-sm border-slate-200 rounded-lg p-2.5 border uppercase font-mono focus:ring-blue-500 focus:border-blue-500">
                    @error('code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Department <span class="text-red-500">*</span></label>
                    <select name="department_id" required class="w-full text-sm border-slate-200 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id', $processTemplate->department_id) == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }} ({{ $dept->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('department_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Default Frequency <span class="text-red-500">*</span></label>
                    <select name="frequency_default" required class="w-full text-sm border-slate-200 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                        @foreach($frequencies as $freq)
                            <option value="{{ $freq->value }}" {{ old('frequency_default', $processTemplate->frequency_default->value) === $freq->value ? 'selected' : '' }}>
                                {{ ucfirst($freq->value) }}
                            </option>
                        @endforeach
                    </select>
                    @error('frequency_default') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full text-sm border-slate-200 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">{{ old('description', $processTemplate->description) }}</textarea>
                @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $processTemplate->is_active) ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4">
                <label for="is_active" class="text-xs font-medium text-slate-700">Active (Available for creating new operational processes)</label>
            </div>
        </div>

        {{-- Blueprint Questions Builder --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">2. Standard Checklist Questions</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Edit existing blueprint questions or add new ones. Changes apply to future process definitions.</p>
                </div>
                <button type="button" id="add-question-btn" class="py-1.5 px-3 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-semibold rounded-lg transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Add Question</span>
                </button>
            </div>

            <div id="questions-container" class="space-y-3">
                @foreach($processTemplate->items as $index => $item)
                    <div class="question-row p-3.5 rounded-lg border border-slate-200 bg-slate-50/50 space-y-3">
                        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                            <div class="md:col-span-7">
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Question Text <span class="text-red-500">*</span></label>
                                <input type="text" name="items[{{ $index }}][question]" value="{{ old("items.{$index}.question", $item->question) }}" required class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500 bg-white">
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Response Type</label>
                                <select name="items[{{ $index }}][response_type]" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500 bg-white">
                                    @foreach($responseTypes as $rt)
                                        <option value="{{ $rt->value }}" {{ old("items.{$index}.response_type", $item->response_type->value) === $rt->value ? 'selected' : '' }}>
                                            {{ $rt->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2 flex items-center justify-between pt-6">
                                <label class="inline-flex items-center gap-1.5 text-xs text-slate-700">
                                    <input type="checkbox" name="items[{{ $index }}][is_required]" value="1" {{ old("items.{$index}.is_required", $item->is_required) ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    <span>Required</span>
                                </label>
                                <button type="button" class="remove-question-btn text-red-500 hover:text-red-700 text-xs font-bold p-1">&times;</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Submit Actions --}}
        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.process-templates.show', $processTemplate) }}" class="py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                Cancel
            </a>
            <button type="submit" class="py-2.5 px-6 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                Save Changes
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let questionIndex = {{ count($processTemplate->items) + 1 }};
    const container = document.getElementById('questions-container');
    const addBtn = document.getElementById('add-question-btn');

    if (addBtn && container) {
        addBtn.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'question-row p-3.5 rounded-lg border border-slate-200 bg-slate-50/50 space-y-3';
            row.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                    <div class="md:col-span-7">
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Question Text <span class="text-red-500">*</span></label>
                        <input type="text" name="items[${questionIndex}][question]" placeholder="e.g. Next verification question..." required class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500 bg-white">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Response Type</label>
                        <select name="items[${questionIndex}][response_type]" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500 bg-white">
                            @foreach($responseTypes as $rt)
                                <option value="{{ $rt->value }}">{{ $rt->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2 flex items-center justify-between pt-6">
                        <label class="inline-flex items-center gap-1.5 text-xs text-slate-700">
                            <input type="checkbox" name="items[${questionIndex}][is_required]" value="1" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span>Required</span>
                        </label>
                        <button type="button" class="remove-question-btn text-red-500 hover:text-red-700 text-xs font-bold p-1">&times;</button>
                    </div>
                </div>
            `;
            container.appendChild(row);
            questionIndex++;
        });

        container.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-question-btn')) {
                const row = e.target.closest('.question-row');
                if (row) {
                    row.remove();
                }
            }
        });
    }
});
</script>
@endsection
