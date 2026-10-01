@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-2xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('admin.departments.index') }}" class="hover:underline">Departments</a>
                <span>/</span>
                <span>Create</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Create Department</h1>
        </div>

        <a href="{{ route('admin.departments.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
            Cancel
        </a>
    </div>

    {{-- Form --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form method="POST" action="{{ route('admin.departments.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Department Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Finance & Accounts" required class="w-full text-sm border-slate-200 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Department Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code') }}" placeholder="e.g. FIN" required class="w-full text-sm border-slate-200 rounded-lg p-2.5 border uppercase font-mono focus:ring-blue-500 focus:border-blue-500">
                @error('code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="3" placeholder="Brief explanation of department scope..." class="w-full text-sm border-slate-200 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">{{ old('description') }}</textarea>
                @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4">
                <label for="is_active" class="text-xs font-medium text-slate-700">Active (Department is enabled for processes and templates)</label>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="{{ route('admin.departments.index') }}" class="py-2 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                    Cancel
                </a>
                <button type="submit" class="py-2 px-5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                    Create Department
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
