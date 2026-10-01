@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Organization Settings</h1>
            <p class="text-sm text-slate-500">Root business boundary, web seats, and organization profile.</p>
        </div>
        <a href="{{ route('admin.organization.edit') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
            Edit Organization
        </a>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-lg bg-emerald-50 text-emerald-800 text-sm font-medium border border-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    {{-- Seat Allocation Overview --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-5 bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Web User Seats</div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-3xl font-bold text-slate-900">{{ $seatUsage['active_seats'] }}</span>
                <span class="text-sm text-slate-500">/ {{ $seatUsage['max_seats'] }} Max</span>
            </div>
            <p class="text-xs text-slate-500 mt-1">{{ $seatUsage['available_seats'] }} available seat(s)</p>
        </div>

        <div class="p-5 bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Telegram Employees</div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-3xl font-bold text-slate-900">{{ $telegramEmployeesCount }}</span>
                <span class="text-sm text-emerald-600 font-semibold">Unlimited</span>
            </div>
            <p class="text-xs text-slate-500 mt-1">No web login required</p>
        </div>

        <div class="p-5 bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Organization Status</div>
            <div class="mt-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $organization->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                    {{ $organization->is_active ? 'Active' : 'Suspended' }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-2">Code: <span class="font-mono font-medium">{{ $organization->code }}</span></p>
        </div>
    </div>

    {{-- Details Card --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden p-6 space-y-6">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">Organization Profile</h2>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
            <div>
                <dt class="text-xs font-medium text-slate-500 uppercase tracking-wider">Organization Name</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $organization->name }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-slate-500 uppercase tracking-wider">Unique Code / Slug</dt>
                <dd class="mt-1 text-sm font-mono font-semibold text-slate-900">{{ $organization->code }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-slate-500 uppercase tracking-wider">Contact Email</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $organization->contact_email ?? 'Not specified' }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-slate-500 uppercase tracking-wider">Contact Phone</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $organization->contact_phone ?? 'Not specified' }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-slate-500 uppercase tracking-wider">Timezone</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $organization->timezone ?? config('app.timezone') }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-slate-500 uppercase tracking-wider">Max Web Users Limit</dt>
                <dd class="mt-1 text-sm font-bold text-slate-900">{{ $organization->max_web_users }} Seats (Enforced)</dd>
            </div>
        </dl>
    </div>
</div>
@endsection
