@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('processes.show', $process) }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 flex items-center gap-1 mb-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Back to Process
            </a>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Edit Process: {{ $process->name }}</h1>
        </div>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
            <p class="font-semibold mb-1">Please correct the following errors:</p>
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('processes.update', $process) }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- General Details --}}
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 space-y-6">
            <h2 class="text-base font-bold text-slate-900">Process Configuration</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Process Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $process->name) }}" required class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Process Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" value="{{ old('code', $process->code) }}" required class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500 font-mono">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">{{ old('description', $process->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Responsible Person (Web User)</label>
                    <select name="responsible_user_id" class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- No Web User --</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ old('responsible_user_id', $process->responsible_user_id) == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} ({{ $u->role->value }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Or Responsible (Telegram Employee)</label>
                    <select name="responsible_telegram_employee_id" class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- No Telegram Employee --</option>
                        @foreach($telegramEmployees as $te)
                            <option value="{{ $te->id }}" {{ old('responsible_telegram_employee_id', $process->responsible_telegram_employee_id) == $te->id ? 'selected' : '' }}>
                                {{ $te->name }} ({{ $te->employee_code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Frequency <span class="text-red-500">*</span></label>
                    <select name="frequency" required class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="daily" {{ old('frequency', $process->frequency->value) == 'daily' ? 'selected' : '' }}>Daily</option>
                        <option value="weekly" {{ old('frequency', $process->frequency->value) == 'weekly' ? 'selected' : '' }}>Weekly</option>
                        <option value="monthly" {{ old('frequency', $process->frequency->value) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                    </select>
                </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reminder Time</label>
                    <input type="time" name="reminder_time" value="{{ old('reminder_time', $process->reminder_time ?? '17:00') }}" class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ends At (Optional)</label>
                    <input type="date" name="ends_at" value="{{ old('ends_at', $process->ends_at?->format('Y-m-d')) }}" class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <div class="border-t border-slate-100 pt-4 space-y-3">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Notification Channels</label>
                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="in_app_enabled" value="1" {{ old('in_app_enabled', $process->in_app_enabled) ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        <span class="text-sm font-medium text-slate-700">In-App Notification Center</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="telegram_enabled" value="1" {{ old('telegram_enabled', $process->telegram_enabled) ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        <span class="text-sm font-medium text-slate-700">Telegram Bot Reminder</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Checklist Questions Toggles --}}
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 space-y-4">
            <h2 class="text-base font-bold text-slate-900">Checklist Questions ({{ $process->items->count() }})</h2>
            <p class="text-xs text-slate-500">Enable or disable specific questions. Changes apply to future checklist executions.</p>

            <div class="space-y-2 divide-y divide-slate-100 pt-2">
                @foreach($process->items as $item)
                    <div class="flex items-start gap-3 py-3 px-2 rounded-lg hover:bg-slate-50 transition-colors">
                        <input type="hidden" name="items[{{ $item->id }}][is_enabled]" value="0">
                        <div class="pt-0.5">
                            <input type="checkbox" name="items[{{ $item->id }}][is_enabled]" value="1" {{ $item->is_enabled ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-slate-800">{{ $item->question }}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[11px] font-mono uppercase bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">{{ $item->response_type->value }}</span>
                                <label class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-600 ml-2">
                                    <input type="checkbox" name="items[{{ $item->id }}][is_required]" value="1" {{ $item->is_required ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-3 h-3">
                                    Required
                                </label>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Escalation Rules --}}
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-700 text-xs font-bold flex items-center justify-center">⚙</span>
                    Accountability Escalation Rules
                </h2>
                <span class="text-xs font-medium text-slate-500">Up to 5 Levels</span>
            </div>
            <p class="text-xs text-slate-500">Escalate unconfirmed process executions to designated Managers or Admins if human confirmation is not recorded within the delay period.</p>

            <div class="space-y-3 pt-2">
                @php
                    $existingRulesByLevel = $process->escalationRules->keyBy('level');
                @endphp
                @for($lvl = 1; $lvl <= 3; $lvl++)
                    @php
                        $rule = $existingRulesByLevel->get($lvl);
                        $isActive = old("escalation_rules.".($lvl-1).".is_active", $rule ? ($rule->is_active ? '1' : '0') : '0');
                        $delay = old("escalation_rules.".($lvl-1).".delay_minutes", $rule ? $rule->delay_minutes : ($lvl * 30));
                        $recipient = old("escalation_rules.".($lvl-1).".escalate_to_user_id", $rule ? $rule->escalate_to_user_id : null);
                    @endphp
                    <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/60 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-slate-200 text-slate-700 text-[11px] font-bold flex items-center justify-center">{{ $lvl }}</span>
                                Level {{ $lvl }} Escalation
                            </span>
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-slate-600">
                                <input type="hidden" name="escalation_rules[{{ $lvl - 1 }}][level]" value="{{ $lvl }}">
                                <input type="hidden" name="escalation_rules[{{ $lvl - 1 }}][is_active]" value="0">
                                <input type="checkbox" name="escalation_rules[{{ $lvl - 1 }}][is_active]" value="1" {{ $isActive == '1' ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                                <span>Enable Level {{ $lvl }}</span>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Delay After Due (Minutes)</label>
                                <input type="number" name="escalation_rules[{{ $lvl - 1 }}][delay_minutes]" value="{{ $delay }}" min="1" max="10080" class="w-full text-sm border-slate-300 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Escalate To (Manager/Admin)</label>
                                <select name="escalation_rules[{{ $lvl - 1 }}][escalate_to_user_id]" class="w-full text-sm border-slate-300 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Manager / Admin...</option>
                                    @foreach($managers as $m)
                                        <option value="{{ $m->id }}" {{ $recipient == $m->id ? 'selected' : '' }}>
                                            {{ $m->name }} ({{ $m->role->value }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        {{-- Submit Actions --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('processes.show', $process) }}" class="px-5 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors">
                Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
