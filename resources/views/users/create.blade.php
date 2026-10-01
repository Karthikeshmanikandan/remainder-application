@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Create Web Portal User</h1>
            <p class="text-sm text-slate-500">Add an authenticated user to your organization (counts toward 5-seat limit).</p>
        </div>
        <a href="{{ route('users.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">
            &larr; Back to Users
        </a>
    </div>

    <form action="{{ route('users.store') }}" method="POST" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-6">
        @csrf
        @include('users._form')

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
            <a href="{{ route('users.index') }}" class="px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 border border-slate-300 rounded-lg transition">
                Cancel
            </a>
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-800 transition">
                Create User
            </button>
        </div>
    </form>
</div>
@endsection