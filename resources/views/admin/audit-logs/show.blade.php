@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    {{-- Breadcrumbs & Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                <a href="{{ route('admin.audit-logs.index') }}" class="hover:underline">Audit Trail</a>
                <span>/</span>
                <span>Audit Entry #{{ $auditLog->id }}</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Audit Log Inspection</h1>
        </div>

        <a href="{{ route('admin.audit-logs.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Back to Audit Trail</span>
        </a>
    </div>

    {{-- Overview Card --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-bold border {{ $auditLog->action->badgeClass() }}">
                    {{ $auditLog->action->value }}
                </span>
                <span class="text-sm font-semibold text-slate-900">{{ $auditLog->summary }}</span>
            </div>

            <div class="text-xs text-slate-500 font-medium">
                Recorded: <span class="font-bold text-slate-800">{{ $auditLog->created_at->format('d M Y, H:i:s') }} UTC</span> ({{ $auditLog->created_at->diffForHumans() }})
            </div>
        </div>

        {{-- Meta Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <div class="bg-slate-50 p-3.5 rounded-lg border border-slate-100">
                <div class="text-slate-400 font-medium uppercase tracking-wider text-[10px]">Actor (User)</div>
                <div class="font-bold text-slate-900 mt-1">{{ $auditLog->user->name ?? 'System / CLI' }}</div>
                <div class="text-[11px] text-slate-500">{{ $auditLog->user ? ucfirst($auditLog->user->role->value) : 'Automated Task' }}</div>
            </div>

            <div class="bg-slate-50 p-3.5 rounded-lg border border-slate-100">
                <div class="text-slate-400 font-medium uppercase tracking-wider text-[10px]">Target Entity</div>
                <div class="font-bold text-slate-900 mt-1">{{ $auditLog->getEntityTypeName() }}</div>
                <div class="text-[11px] text-slate-500 truncate" title="{{ $auditLog->getAuditableName() }}">{{ $auditLog->getAuditableName() }}</div>
            </div>

            <div class="bg-slate-50 p-3.5 rounded-lg border border-slate-100">
                <div class="text-slate-400 font-medium uppercase tracking-wider text-[10px]">IP Address</div>
                <div class="font-mono font-bold text-slate-900 mt-1">{{ $auditLog->ip_address ?? 'Not Recorded (CLI)' }}</div>
            </div>

            <div class="bg-slate-50 p-3.5 rounded-lg border border-slate-100">
                <div class="text-slate-400 font-medium uppercase tracking-wider text-[10px]">Audit Record ID</div>
                <div class="font-mono font-bold text-slate-900 mt-1">#{{ $auditLog->id }}</div>
                <div class="text-[10px] text-emerald-600 font-medium">Immutable &amp; Append-Only</div>
            </div>
        </div>

        @if($auditLog->user_agent)
            <div class="text-xs text-slate-500 bg-slate-50 p-3 rounded-lg border border-slate-100">
                <span class="font-semibold text-slate-700">User Agent:</span>
                <span class="font-mono text-[11px] text-slate-600 ml-1">{{ $auditLog->user_agent }}</span>
            </div>
        @endif
    </div>

    {{-- Value Diffs --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Before Values --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
            <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Before Values (Previous State)</h3>
                <span class="text-[10px] text-slate-400 font-mono">JSON</span>
            </div>
            <div class="p-4 flex-1 font-mono text-xs bg-slate-900 text-slate-100 overflow-x-auto rounded-b-xl">
                @if($auditLog->before_values)
                    <pre class="whitespace-pre-wrap">{{ json_encode($auditLog->before_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @else
                    <span class="text-slate-500 italic">null (New entity or baseline)</span>
                @endif
            </div>
        </div>

        {{-- After Values --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
            <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">After Values (Updated State)</h3>
                <span class="text-[10px] text-slate-400 font-mono">JSON</span>
            </div>
            <div class="p-4 flex-1 font-mono text-xs bg-slate-900 text-emerald-400 overflow-x-auto rounded-b-xl">
                @if($auditLog->after_values)
                    <pre class="whitespace-pre-wrap">{{ json_encode($auditLog->after_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @else
                    <span class="text-slate-500 italic">null (Deleted or non-state action)</span>
                @endif
            </div>
        </div>
    </div>

    @if($auditLog->metadata)
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 bg-slate-50 border-b border-slate-200">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Additional Metadata</h3>
            </div>
            <div class="p-4 font-mono text-xs bg-slate-900 text-slate-200 overflow-x-auto">
                <pre class="whitespace-pre-wrap">{{ json_encode($auditLog->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    @endif
</div>
@endsection
