@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Edit Organization</h1>
            <p class="text-sm text-slate-500">Update organization business profile.</p>
        </div>
        <a href="{{ route('admin.organization.show') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
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
        <form method="POST" action="{{ route('admin.organization.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Organization Name *</label>
                <input type="text" name="name" id="name" value="{{ old('name', $organization->name) }}" required
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>

            <div>
                <label for="code" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Organization Code / Slug</label>
                <input type="text" name="code" id="code" value="{{ old('code', $organization->code) }}" readonly
                    class="mt-1 block w-full rounded-lg border-slate-200 bg-slate-50 text-slate-500 shadow-sm sm:text-sm cursor-not-allowed">
                <p class="text-xs text-slate-400 mt-1">Unique organization code cannot be modified directly.</p>
            </div>

            <div>
                <label for="contact_email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Contact Email</label>
                <input type="email" name="contact_email" id="contact_email" value="{{ old('contact_email', $organization->contact_email) }}"
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>

            <div>
                <label for="contact_phone" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Contact Phone</label>
                <input type="text" name="contact_phone" id="contact_phone" value="{{ old('contact_phone', $organization->contact_phone) }}"
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>

            <div>
                <label for="timezone" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Timezone</label>
                <input type="text" name="timezone" id="timezone" value="{{ old('timezone', $organization->timezone ?? 'UTC') }}"
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.organization.show') }}" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white font-semibold text-sm rounded-lg hover:bg-blue-700 transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
