@extends('layouts.app')
@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">User Management</h1>
            <p class="text-sm text-slate-500">Manage authenticated web users for your organization (max 5 active seats).</p>
        </div>
        @if(!($seatUsage['is_limit_reached'] ?? false))
            <a href="{{ route('users.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-800 transition">
                + Create User
            </a>
        @else
            <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-100 text-amber-800" title="Maximum 5 active web user seats reached for this organization">
                Seat Limit Reached (5/5)
            </span>
        @endif
    </div>

    {{-- Seat Allocation Banner --}}
    @if(isset($seatUsage))
    <div class="p-4 rounded-xl border {{ $seatUsage['is_limit_reached'] ? 'bg-amber-50 border-amber-200' : 'bg-blue-50/70 border-blue-200' }}">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg {{ $seatUsage['is_limit_reached'] ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }} flex items-center justify-center font-bold text-sm">
                    {{ $seatUsage['active_seats'] }}/{{ $seatUsage['max_seats'] }}
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">
                        Organization Web User Seats: <span class="font-bold">{{ $seatUsage['active_seats'] }} of {{ $seatUsage['max_seats'] }} Active Seats Used</span>
                    </h3>
                    <p class="text-xs text-slate-500">
                        {{ $seatUsage['available_seats'] }} seats remaining for web portal access. Unlimited Telegram-only employees are managed separately.
                    </p>
                </div>
            </div>
            <a href="{{ route('admin.telegram-employees.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 hover:underline">
                Manage Telegram Employees &rarr;
            </a>
        </div>
    </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('users.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or email..." class="w-full text-xs rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <select name="role" class="w-full text-xs rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Roles</option>
                    @foreach(\App\Enums\UserRole::cases() as $role)
                        <option value="{{ $role->value }}" {{ request('role') === $role->value ? 'selected' : '' }}>
                            {{ ucfirst($role->value) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="department_id" class="w-full text-xs rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">All Departments</option>
                    @if(isset($departments))
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ (string) request('department_id') === (string) $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="px-3 py-2 text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition w-full sm:w-auto">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'role', 'department_id', 'status']))
                    <a href="{{ route('users.index') }}" class="px-3 py-2 text-xs text-slate-500 hover:text-slate-700">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <ul class="divide-y divide-slate-200">
            @forelse($users as $user)
            <li class="p-4 sm:px-6 hover:bg-slate-50/50 flex items-center justify-between transition">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h3 class="text-sm font-semibold text-slate-900">{{ $user->name }}</h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $user->status->value === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                            {{ ucfirst($user->status->value) }}
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                            {{ ucfirst($user->role->value) }}
                        </span>
                        @if($user->department)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                {{ $user->department->name }}
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ $user->email }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('users.edit', $user) }}" class="text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline">Edit</a>
                </div>
            </li>
            @empty
            <li class="p-6 text-center text-sm text-slate-500">No users found.</li>
            @endforelse
        </ul>
    </div>
    {{ $users->links() }}
</div>
@endsection