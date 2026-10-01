@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Add Telegram-Only Employee</h1>
            <p class="text-sm text-slate-500">Create an operational employee profile. No web login seat is consumed.</p>
        </div>
        <a href="{{ route('admin.telegram-employees.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
            &larr; Back
        </a>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-lg bg-rose-50 text-rose-800 text-sm font-medium border border-rose-200">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form method="POST" action="{{ route('admin.telegram-employees.store') }}" class="space-y-5">
            @csrf

            <div>
                <label for="name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Full Name *</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    placeholder="e.g. Rahul Sharma">
            </div>

            <div>
                <label for="employee_code" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Employee Code / ID</label>
                <input type="text" name="employee_code" id="employee_code" value="{{ old('employee_code') }}"
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    placeholder="Leave blank to auto-generate">
                <p class="text-xs text-slate-400 mt-1">If left blank, an employee code (e.g. EMP-1001) will be generated automatically.</p>
            </div>

            <div>
                <label for="phone" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Phone Number</label>
                <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    placeholder="+91 98765 43210">
            </div>

            <div>
                <label for="department_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Department</label>
                <select name="department_id" id="department_id"
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="">-- Select Department (Optional) --</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="notes" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Notes / Role Info</label>
                <textarea name="notes" id="notes" rows="2"
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    placeholder="Field operator, delivery staff, site engineer, etc.">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}
                    class="rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <label for="is_active" class="text-sm font-medium text-slate-700">Active (can receive tasks and checklists)</label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.telegram-employees.index') }}" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white font-semibold text-sm rounded-lg hover:bg-blue-700 transition">
                    Create Employee
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
