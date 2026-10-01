@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('admin.departments.index') }}" class="hover:underline">Departments</a>
                <span>/</span>
                <span>{{ $department->name }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $department->name }}</h1>
                <span class="font-mono text-xs px-2 py-0.5 bg-slate-100 text-slate-700 rounded font-bold">{{ $department->code }}</span>
                @if($department->is_active)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Active
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                        Inactive
                    </span>
                @endif
            </div>
            @if($department->description)
                <p class="text-sm text-slate-500 mt-1">{{ $department->description }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.departments.edit', $department) }}" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                Edit Department
            </a>
            <form method="POST" action="{{ route('admin.departments.toggle-active', $department) }}" class="inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="px-3 py-1.5 text-xs font-semibold {{ $department->is_active ? 'bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200' }} rounded-lg transition">
                    {{ $department->is_active ? 'Deactivate' : 'Activate' }}
                </button>
            </form>
        </div>
    </div>

    {{-- Metrics Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Active Processes</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ $department->processes_count }}</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Process Templates</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ $department->process_templates_count }}</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Created Date</div>
            <div class="mt-2 text-base font-bold text-slate-900">{{ $department->created_at->format('d M Y, H:i') }} UTC</div>
        </div>
    </div>

    {{-- Processes & Templates Panels --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Associated Processes --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Processes in this Department</h2>
                <span class="text-xs text-slate-400">{{ count($department->processes) }} recent</span>
            </div>

            <div class="divide-y divide-slate-100 text-xs">
                @forelse($department->processes as $proc)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <a href="{{ route('processes.show', $proc) }}" class="font-semibold text-slate-900 hover:text-blue-600">
                                {{ $proc->name }}
                            </a>
                            <div class="text-[11px] text-slate-400">Responsible: {{ $proc->responsibleUser->name ?? 'Unassigned' }}</div>
                        </div>
                        <span class="font-mono text-[11px] text-slate-500 bg-slate-50 px-2 py-0.5 rounded">{{ $proc->frequency->value }}</span>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 italic py-4 text-center">No processes defined for this department yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Associated Templates --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Process Templates</h2>
                <a href="{{ route('admin.process-templates.create') }}" class="text-xs font-semibold text-blue-600 hover:underline">+ New Template</a>
            </div>

            <div class="divide-y divide-slate-100 text-xs">
                @forelse($department->processTemplates as $tpl)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <a href="{{ route('admin.process-templates.show', $tpl) }}" class="font-semibold text-slate-900 hover:text-blue-600">
                                {{ $tpl->name }}
                            </a>
                            <div class="text-[11px] text-slate-400">{{ $tpl->items_count }} questions &bull; {{ $tpl->frequency_default->value }}</div>
                        </div>
                        @if($tpl->is_active)
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Active</span>
                        @else
                            <span class="text-[10px] font-bold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">Inactive</span>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-slate-400 italic py-4 text-center">No blueprints registered for this department.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Audit & Governance History --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Department Governance Change History</h2>
            <span class="text-xs text-slate-500">{{ $auditLogs->total() }} audit entries</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Date / Time (UTC)</th>
                        <th class="py-3 px-4">Actor</th>
                        <th class="py-3 px-4">Action</th>
                        <th class="py-3 px-4">Summary</th>
                        <th class="py-3 px-4 text-right">Inspect</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($auditLogs as $log)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="font-medium text-slate-900">{{ $log->created_at->format('d M Y, H:i') }}</div>
                                <div class="text-[10px] text-slate-400">{{ $log->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="font-medium text-slate-800">{{ $log->user->name ?? 'System' }}</div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold border {{ $log->action->badgeClass() }}">
                                    {{ $log->action->value }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-slate-800">{{ $log->summary }}</div>
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <a href="{{ route('admin.audit-logs.show', $log) }}" class="text-blue-600 hover:underline font-semibold">
                                    View Diff &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-slate-400 italic">
                                No configuration changes recorded for this department yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($auditLogs->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $auditLogs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
