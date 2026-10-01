@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <span>Administration</span>
                <span>/</span>
                <span>Process Templates</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Process Blueprint & Template Governance</h1>
            <p class="text-sm text-slate-500 mt-1">Manage standard checklist blueprints used for creating operational business processes.</p>
        </div>

        <a href="{{ route('admin.process-templates.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Create Blueprint Template</span>
        </a>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <form method="GET" action="{{ route('admin.process-templates.index') }}" class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or code..." class="text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500 w-64">
                </div>

                <div>
                    <select name="department_id" class="text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <select name="status" class="text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive Only</option>
                    </select>
                </div>

                <button type="submit" class="py-2 px-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                    Filter
                </button>
                <a href="{{ route('admin.process-templates.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-lg transition">
                    Reset
                </a>
            </div>

            <div class="text-xs text-slate-500 font-medium">
                Showing {{ $templates->total() }} Templates
            </div>
        </form>
    </div>

    {{-- Templates Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Template Name</th>
                        <th class="py-3 px-4">Department</th>
                        <th class="py-3 px-4">Code</th>
                        <th class="py-3 px-4 text-center">Questions</th>
                        <th class="py-3 px-4">Default Frequency</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-center">Processes</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($templates as $tpl)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3 px-4">
                                <a href="{{ route('admin.process-templates.show', $tpl) }}" class="font-bold text-slate-900 hover:text-blue-600">
                                    {{ $tpl->name }}
                                </a>
                                @if($tpl->description)
                                    <div class="text-[11px] text-slate-400 truncate max-w-xs">{{ $tpl->description }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-medium text-slate-700">{{ $tpl->department->name ?? 'General' }}</span>
                            </td>
                            <td class="py-3 px-4 font-mono font-semibold text-slate-700">
                                {{ $tpl->code }}
                            </td>
                            <td class="py-3 px-4 text-center font-bold text-slate-800">
                                {{ $tpl->items_count }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="capitalize font-medium text-slate-700">{{ $tpl->frequency_default->value }}</span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($tpl->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center font-bold text-slate-800">
                                {{ $tpl->processes_count }}
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.process-templates.show', $tpl) }}" class="px-2.5 py-1 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition">
                                        View
                                    </a>
                                    <a href="{{ route('admin.process-templates.edit', $tpl) }}" class="px-2.5 py-1 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('admin.process-templates.toggle-active', $tpl) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="px-2.5 py-1 text-xs font-medium {{ $tpl->is_active ? 'text-amber-700 bg-amber-50 hover:bg-amber-100' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100' }} rounded-lg transition">
                                            {{ $tpl->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400">
                                No process templates found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($templates->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $templates->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
