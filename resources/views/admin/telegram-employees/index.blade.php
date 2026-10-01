@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Telegram-Only Employees</h1>
            <p class="text-sm text-slate-500">Manage field staff and operational employees who interact purely via Telegram bot. No web login seat consumed.</p>
        </div>
        <a href="{{ route('admin.telegram-employees.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
            + Add Telegram Employee
        </a>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-lg bg-emerald-50 text-emerald-800 text-sm font-medium border border-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filter bar --}}
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-wrap gap-4 items-center justify-between">
        <form method="GET" action="{{ route('admin.telegram-employees.index') }}" class="flex flex-wrap gap-3 items-center flex-1">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, phone, code..."
                   class="rounded-lg border-slate-300 text-sm focus:ring-blue-500 focus:border-blue-500 w-64">
            
            <select name="department_id" class="rounded-lg border-slate-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                @endforeach
            </select>

            <select name="status" class="rounded-lg border-slate-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>

            <button type="submit" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-lg transition">
                Filter
            </button>
            @if(request()->hasAny(['search', 'department_id', 'status']))
                <a href="{{ route('admin.telegram-employees.index') }}" class="text-xs text-slate-500 hover:text-slate-700 underline">Clear</a>
            @endif
        </form>
    </div>

    {{-- Table list --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 font-semibold text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3">Employee</th>
                        <th class="px-6 py-3">Department</th>
                        <th class="px-6 py-3">Telegram Account</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($employees as $emp)
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-4">
                            <div class="font-semibold text-slate-900">{{ $emp->name }}</div>
                            <div class="text-xs text-slate-500">{{ $emp->employee_code }} • {{ $emp->phone ?? 'No phone' }}</div>
                        </td>
                        <td class="px-6 py-4 text-slate-700">
                            {{ $emp->department ? $emp->department->name : '—' }}
                        </td>
                        <td class="px-6 py-4">
                            @if($emp->telegramAccount && $emp->telegramAccount->isVerified())
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Linked ({{ $emp->telegramAccount->username ? '@'.$emp->telegramAccount->username : ($emp->telegramAccount->masked_chat_id ?? 'Connected') }})
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                                    Not Linked
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $emp->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                {{ $emp->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="{{ route('admin.telegram-employees.show', $emp) }}" class="font-medium text-blue-600 hover:text-blue-800">View</a>
                            <a href="{{ route('admin.telegram-employees.edit', $emp) }}" class="font-medium text-slate-600 hover:text-slate-900">Edit</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500 text-sm">
                            No Telegram-only employees registered yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $employees->links() }}
</div>
@endsection
