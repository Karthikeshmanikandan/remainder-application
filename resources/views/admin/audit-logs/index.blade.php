@extends('layouts.app')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <span>Administration</span>
                <span>/</span>
                <span>Audit Trail</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">System Audit & Governance Logs</h1>
            <p class="text-sm text-slate-500 mt-1">Immutable, append-only chronological log of all administrative actions and configuration changes.</p>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Summary or IP..." class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Actor (User)</label>
                    <select name="user_id" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Action</label>
                    <select name="action" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Actions</option>
                        @foreach($actions as $action)
                            <option value="{{ $action->value }}" {{ request('action') === $action->value ? 'selected' : '' }}>
                                {{ $action->label() }} ({{ $action->value }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Entity Type</label>
                    <select name="auditable_type" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Entities</option>
                        @foreach($entityTypes as $key => $label)
                            <option value="{{ $key }}" {{ request('auditable_type') === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">From Date</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">To Date</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <a href="{{ route('admin.audit-logs.index') }}" class="py-1.5 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-lg transition">
                    Reset
                </a>
                <button type="submit" class="py-1.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                    Filter Logs
                </button>
            </div>
        </form>
    </div>

    {{-- Audit Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Audit Log Records</h2>
            <span class="text-xs text-slate-500">{{ $auditLogs->total() }} total entries</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Timestamp (UTC)</th>
                        <th class="py-3 px-4">Actor</th>
                        <th class="py-3 px-4">Action</th>
                        <th class="py-3 px-4">Target Entity</th>
                        <th class="py-3 px-4">Summary</th>
                        <th class="py-3 px-4">IP Address</th>
                        <th class="py-3 px-4 text-right">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($auditLogs as $log)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="font-medium text-slate-900">{{ $log->created_at->format('d M Y, H:i:s') }}</div>
                                <div class="text-[10px] text-slate-400">{{ $log->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($log->user)
                                    <div class="font-medium text-slate-800">{{ $log->user->name }}</div>
                                    <div class="text-[10px] text-slate-400">{{ ucfirst($log->user->role->value) }}</div>
                                @else
                                    <span class="text-slate-400 italic">System / CLI</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold border {{ $log->action->badgeClass() }}">
                                    {{ $log->action->value }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-semibold text-slate-800">{{ $log->getEntityTypeName() }}</span>
                                <div class="text-[11px] text-slate-500 truncate max-w-xs" title="{{ $log->getAuditableName() }}">
                                    {{ $log->getAuditableName() }}
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-slate-800 max-w-md truncate" title="{{ $log->summary }}">
                                    {{ $log->summary }}
                                </div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap font-mono text-[11px] text-slate-500">
                                {{ $log->ip_address ?? '—' }}
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <a href="{{ route('admin.audit-logs.show', $log) }}" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                                    <span>Inspect</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                No audit records matched your filter criteria.
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
