@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Breadcrumb & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('process-executions.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 flex items-center gap-1 mb-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Back to Checklists
            </a>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $execution->process->name }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                    {{ $execution->process->department->name }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Scheduled for: <span class="font-semibold text-slate-700">{{ $execution->scheduled_for->format('d M Y, H:i') }} UTC</span>
                &bull; Responsible: <span class="font-semibold text-slate-700">{{ $execution->process->responsibleUser->name }}</span>
            </p>
        </div>

        @if($execution->isCompleted())
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Completed
            </span>
        @endif
    </div>

    {{-- Confirmation Banner if Completed --}}
    @if($execution->isCompleted())
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 font-bold shrink-0">
                    ✓
                </div>
                <div>
                    <h3 class="text-sm font-bold">Checklist Confirmed & Completed</h3>
                    <p class="text-xs text-emerald-700 mt-0.5">
                        Confirmed by <span class="font-semibold">{{ $execution->completedBy?->name ?? 'User' }}</span>
                        at {{ $execution->completed_at?->format('d M Y H:i:s') }} UTC
                    </p>
                </div>
            </div>
            <span class="text-xs font-bold font-mono bg-white px-2.5 py-1 rounded border border-emerald-200">
                {{ $execution->answeredCount() }} / {{ $execution->totalCount() }} Answered
            </span>
        </div>
    @endif

    {{-- Escalation History Card (if any escalation triggered for this execution) --}}
    @if($execution->escalationEvents->isNotEmpty())
        <div class="p-5 rounded-xl border border-amber-200 bg-amber-50/50 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-amber-900 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Escalation History
                </h3>
                <span class="text-xs text-amber-800 font-medium">
                    Required human confirmation was not recorded within the configured escalation period.
                </span>
            </div>

            <div class="space-y-2 pt-1">
                @foreach($execution->escalationEvents->sortBy('level') as $escEvent)
                    <div class="p-3 bg-white rounded-lg border border-amber-200/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-900">
                                    Level {{ $escEvent->level }}
                                </span>
                                <span class="text-xs text-slate-600">
                                    Escalated to: <strong>{{ $escEvent->rule?->escalateToUser?->name ?? 'Manager' }}</strong>
                                </span>
                                <span class="text-xs text-slate-400">
                                    at {{ $escEvent->triggered_at?->format('d M Y, H:i') }}
                                </span>
                            </div>
                            @if($escEvent->acknowledged_at)
                                <div class="text-xs text-blue-700 mt-1">
                                    Acknowledged by {{ $escEvent->acknowledgedBy?->name ?? 'Manager' }} at {{ $escEvent->acknowledged_at->format('d M Y, H:i') }}
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold
                                @if($escEvent->isTriggered()) bg-amber-100 text-amber-800
                                @elseif($escEvent->isAcknowledged()) bg-blue-100 text-blue-800
                                @elseif($escEvent->isResolved()) bg-emerald-100 text-emerald-800
                                @else bg-slate-100 text-slate-700 @endif">
                                {{ ucfirst($escEvent->status->value) }}
                            </span>

                            @if($escEvent->isTriggered() && ! auth()->user()->isEmployee() && (auth()->user()->isAdmin() || auth()->id() === $escEvent->rule?->escalate_to_user_id))
                                <form method="POST" action="{{ route('accountability.escalations.acknowledge', $escEvent) }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded transition">
                                        Acknowledge
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Progress Card --}}
    @php
        $percentage = $execution->progressPercentage();
        $answered = $execution->answeredCount();
        $total = $execution->totalCount();
    @endphp
    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center justify-between text-sm font-semibold text-slate-800 mb-2">
            <span>Checklist Progress</span>
            <span>{{ $answered }} / {{ $total }} Completed ({{ $percentage }}%)</span>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
            <div class="h-2.5 rounded-full transition-all duration-300 {{ $execution->isCompleted() ? 'bg-emerald-500' : 'bg-blue-600' }}" style="width: {{ $percentage }}%"></div>
        </div>
    </div>

    {{-- Checklist Form --}}
    <form id="checklist-form" method="POST" action="{{ route('process-executions.confirm', $execution) }}" class="space-y-4">
        @csrf

        @foreach($execution->items as $index => $item)
            @php
                $resp = old("answers.{$item->id}.response", $item->response);
                $notes = old("answers.{$item->id}.notes", $item->notes);
                $isItemAnswered = $item->isAnswered();
            @endphp
            <div class="bg-white p-6 rounded-xl shadow-sm border {{ $isItemAnswered ? 'border-slate-200' : 'border-slate-300 ring-1 ring-slate-200' }} space-y-4 transition-all">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="w-7 h-7 rounded-full {{ $isItemAnswered ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }} flex items-center justify-center font-bold text-xs shrink-0">
                            {{ $isItemAnswered ? '✓' : sprintf('%02d', $index + 1) }}
                        </span>
                        <div>
                            <h3 class="text-base font-semibold text-slate-900 leading-snug">
                                {{ $item->question_snapshot }}
                            </h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[10px] font-mono uppercase bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">{{ $item->response_type->value }}</span>
                                @if($item->processItem && $item->processItem->is_required)
                                    <span class="text-[10px] font-semibold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">Required</span>
                                @endif
                                @if($item->answered_at)
                                    <span class="text-[10px] text-slate-400">Answered {{ $item->answered_at->format('H:i') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Response Options --}}
                <div class="pt-2">
                    @if($item->response_type === \App\Enums\ProcessResponseType::YES_NO)
                        <div class="flex items-center gap-4">
                            <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border {{ $resp === 'YES' ? 'bg-blue-50 border-blue-500 text-blue-700 font-bold' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }} cursor-pointer text-sm">
                                <input type="radio" name="answers[{{ $item->id }}][response]" value="YES" {{ $resp === 'YES' ? 'checked' : '' }} {{ $execution->isCompleted() ? 'disabled' : '' }} class="text-blue-600 focus:ring-blue-500">
                                <span>Yes</span>
                            </label>
                            <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border {{ $resp === 'NO' ? 'bg-red-50 border-red-500 text-red-700 font-bold' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }} cursor-pointer text-sm">
                                <input type="radio" name="answers[{{ $item->id }}][response]" value="NO" {{ $resp === 'NO' ? 'checked' : '' }} {{ $execution->isCompleted() ? 'disabled' : '' }} class="text-red-600 focus:ring-red-500">
                                <span>No</span>
                            </label>
                        </div>

                    @elseif($item->response_type === \App\Enums\ProcessResponseType::YES_NO_NA)
                        <div class="flex items-center gap-4">
                            <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border {{ $resp === 'YES' ? 'bg-blue-50 border-blue-500 text-blue-700 font-bold' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }} cursor-pointer text-sm">
                                <input type="radio" name="answers[{{ $item->id }}][response]" value="YES" {{ $resp === 'YES' ? 'checked' : '' }} {{ $execution->isCompleted() ? 'disabled' : '' }} class="text-blue-600 focus:ring-blue-500">
                                <span>Yes</span>
                            </label>
                            <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border {{ $resp === 'NO' ? 'bg-red-50 border-red-500 text-red-700 font-bold' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }} cursor-pointer text-sm">
                                <input type="radio" name="answers[{{ $item->id }}][response]" value="NO" {{ $resp === 'NO' ? 'checked' : '' }} {{ $execution->isCompleted() ? 'disabled' : '' }} class="text-red-600 focus:ring-red-500">
                                <span>No</span>
                            </label>
                            <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border {{ $resp === 'N/A' ? 'bg-slate-100 border-slate-400 text-slate-800 font-bold' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }} cursor-pointer text-sm">
                                <input type="radio" name="answers[{{ $item->id }}][response]" value="N/A" {{ $resp === 'N/A' ? 'checked' : '' }} {{ $execution->isCompleted() ? 'disabled' : '' }} class="text-slate-600 focus:ring-slate-500">
                                <span>N/A</span>
                            </label>
                        </div>

                    @else
                        <div>
                            <input type="text" name="answers[{{ $item->id }}][response]" value="{{ $resp }}" placeholder="Enter answer..." {{ $execution->isCompleted() ? 'readonly' : '' }} class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    @endif
                </div>

                {{-- Notes / Remarks --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Reason / Notes (Optional)</label>
                    <input type="text" name="answers[{{ $item->id }}][notes]" value="{{ $notes }}" placeholder="Add optional context or notes..." {{ $execution->isCompleted() ? 'readonly' : '' }} class="w-full text-xs border-slate-200 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        @endforeach

        {{-- Action Buttons --}}
        @if(! $execution->isCompleted())
            <div class="sticky bottom-4 z-20 bg-white/95 backdrop-blur border border-slate-200 p-4 rounded-xl shadow-lg flex items-center justify-between">
                <button type="submit"
                        formaction="{{ route('process-executions.save-progress', $execution) }}"
                        class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-lg transition-colors">
                    Save Progress
                </button>

                <button type="submit"
                        formaction="{{ route('process-executions.confirm', $execution) }}"
                        class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    Confirm Checklist
                </button>
            </div>
        @endif
    </form>
</div>
@endsection
