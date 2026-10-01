@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('admin.process-templates.index') }}" class="hover:underline">Process Templates</a>
                <span>/</span>
                <span>{{ $processTemplate->name }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $processTemplate->name }}</h1>
                <span class="font-mono text-xs px-2 py-0.5 bg-slate-100 text-slate-700 rounded font-bold">{{ $processTemplate->code }}</span>
                @if($processTemplate->is_active)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Active
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                        Inactive
                    </span>
                @endif
            </div>
            @if($processTemplate->description)
                <p class="text-sm text-slate-500 mt-1">{{ $processTemplate->description }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.process-templates.edit', $processTemplate) }}" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                Edit Blueprint
            </a>
            <form method="POST" action="{{ route('admin.process-templates.toggle-active', $processTemplate) }}" class="inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="px-3 py-1.5 text-xs font-semibold {{ $processTemplate->is_active ? 'bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200' }} rounded-lg transition">
                    {{ $processTemplate->is_active ? 'Deactivate' : 'Activate' }}
                </button>
            </form>
        </div>
    </div>

    {{-- Metrics Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Department</div>
            <div class="mt-2 text-base font-bold text-slate-900">{{ $processTemplate->department->name ?? 'General' }}</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Default Frequency</div>
            <div class="mt-2 text-base font-bold capitalize text-slate-900">{{ $processTemplate->frequency_default->value }}</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Standard Questions</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ count($processTemplate->items) }}</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Active Processes</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ $processTemplate->processes_count }}</div>
        </div>
    </div>

    {{-- Standard Questions Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Standard Verification Checklist Questions</h2>
                <p class="text-xs text-slate-400 mt-0.5">Blueprint questions copied when initializing new operational processes.</p>
            </div>
            <span class="text-xs text-slate-500 font-medium">{{ count($processTemplate->items) }} Questions</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4 w-12 text-center">#</th>
                        <th class="py-3 px-4">Question Text</th>
                        <th class="py-3 px-4">Response Type</th>
                        <th class="py-3 px-4 text-center">Required</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($processTemplate->items as $item)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3 px-4 text-center font-bold text-slate-400 font-mono">
                                {{ $item->sort_order }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-slate-900">{{ $item->question }}</div>
                                @if($item->description)
                                    <div class="text-[11px] text-slate-400 mt-0.5">{{ $item->description }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-mono text-[11px] uppercase px-2 py-0.5 bg-slate-100 text-slate-700 rounded font-medium">
                                    {{ $item->response_type->value }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($item->is_required)
                                    <span class="text-emerald-700 font-bold">Yes</span>
                                @else
                                    <span class="text-slate-400">Optional</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($item->is_active)
                                    <span class="text-emerald-700 font-bold">Active</span>
                                @else
                                    <span class="text-slate-400">Inactive</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-slate-400 italic">
                                No questions configured in this blueprint template.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Audit & Governance History --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Blueprint Governance Change History</h2>
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
                                No blueprint modifications recorded yet.
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
