@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('processes.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700 flex items-center gap-1 mb-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Back to Processes
            </a>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Create Business Process</h1>
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

    <form method="POST" action="{{ route('processes.store') }}" id="process-form" class="space-y-6">
        @csrf

        {{-- Step 1 & 2: Department and Template --}}
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 space-y-6">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">1</span>
                Department & Process Template
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Department <span class="text-red-500">*</span></label>
                    <select id="department_select" name="department_id" required class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select a department...</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Process Template</label>
                    <select id="template_select" name="process_template_id" class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select template or create custom...</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Process Name <span class="text-red-500">*</span></label>
                <input type="text" id="process_name" name="name" value="{{ old('name') }}" required placeholder="e.g. Finance — Daily Checklist" class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <textarea id="process_desc" name="description" rows="2" placeholder="Brief description of the process..." class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">{{ old('description') }}</textarea>
            </div>
        </div>

        {{-- Step 3: Checklist Items Customization --}}
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">2</span>
                    Standard Questions & Checklist Items
                </h2>
                <span id="items-count-badge" class="text-xs font-medium text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">0 questions</span>
            </div>
            <p class="text-xs text-slate-500">Enable or disable standard questions according to your organization's workflow.</p>

            <div id="checklist-container" class="space-y-2 divide-y divide-slate-100 pt-2">
                <div class="p-4 text-center text-sm text-slate-400 bg-slate-50 rounded-lg">
                    Select a department template above to load standard questions.
                </div>
            </div>
        </div>

        {{-- Step 4: Assignment & Schedule --}}
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 space-y-6">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">3</span>
                Assignment & Schedule
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Responsible Person (Web User)</label>
                    <select name="responsible_user_id" class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select responsible web user...</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ old('responsible_user_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} ({{ $u->role->value }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Or Responsible (Telegram Employee)</label>
                    <select name="responsible_telegram_employee_id" class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select responsible telegram employee...</option>
                        @foreach($telegramEmployees as $te)
                            <option value="{{ $te->id }}" {{ old('responsible_telegram_employee_id') == $te->id ? 'selected' : '' }}>
                                {{ $te->name }} ({{ $te->employee_code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Frequency <span class="text-red-500">*</span></label>
                    <select name="frequency" id="frequency_select" required class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                        <option value="daily" {{ old('frequency') == 'daily' ? 'selected' : '' }}>Daily</option>
                        <option value="weekly" {{ old('frequency') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                        <option value="monthly" {{ old('frequency') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                    </select>
                </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reminder Time</label>
                    <input type="time" name="reminder_time" value="{{ old('reminder_time', '17:00') }}" class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-400 mt-1">Default 5:00 PM</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Starts At</label>
                    <input type="date" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d')) }}" class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ends At (Optional)</label>
                    <input type="date" name="ends_at" value="{{ old('ends_at') }}" class="w-full text-sm border-slate-300 rounded-lg p-2.5 border focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <div class="border-t border-slate-100 pt-4 space-y-3">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Notification Channels</label>
                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="in_app_enabled" value="1" {{ old('in_app_enabled', true) ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        <span class="text-sm font-medium text-slate-700">In-App Notification Center</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="telegram_enabled" value="1" {{ old('telegram_enabled', false) ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        <span class="text-sm font-medium text-slate-700">Telegram Bot Reminder</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Step 5: Multi-Level Escalation Rules --}}
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-700 text-xs font-bold flex items-center justify-center">4</span>
                    Accountability Escalation Rules (Optional)
                </h2>
                <span class="text-xs font-medium text-slate-500">Up to 5 Levels</span>
            </div>
            <p class="text-xs text-slate-500">Configure multi-level escalation to notify managers or admins if the human confirmation is not recorded within a specified delay.</p>

            <div class="space-y-3 pt-2" id="escalation-rules-container">
                @for($lvl = 1; $lvl <= 3; $lvl++)
                    <div class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/60 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-slate-200 text-slate-700 text-[11px] font-bold flex items-center justify-center">{{ $lvl }}</span>
                                Level {{ $lvl }} Escalation
                            </span>
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-slate-600">
                                <input type="hidden" name="escalation_rules[{{ $lvl - 1 }}][level]" value="{{ $lvl }}">
                                <input type="hidden" name="escalation_rules[{{ $lvl - 1 }}][is_active]" value="0">
                                <input type="checkbox" name="escalation_rules[{{ $lvl - 1 }}][is_active]" value="1" {{ old("escalation_rules.".($lvl-1).".is_active", $lvl === 1 ? '1' : '0') == '1' ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                                <span>Enable Level {{ $lvl }}</span>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Delay After Due (Minutes)</label>
                                <input type="number" name="escalation_rules[{{ $lvl - 1 }}][delay_minutes]" value="{{ old("escalation_rules.".($lvl-1).".delay_minutes", $lvl * 30) }}" min="1" max="10080" class="w-full text-sm border-slate-300 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-[11px] text-slate-400 mt-0.5">e.g. 30 min, 60 min, 120 min</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Escalate To (Manager/Admin)</label>
                                <select name="escalation_rules[{{ $lvl - 1 }}][escalate_to_user_id]" class="w-full text-sm border-slate-300 rounded-lg p-2 border focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Manager / Admin...</option>
                                    @foreach($managers as $m)
                                        <option value="{{ $m->id }}" {{ old("escalation_rules.".($lvl-1).".escalate_to_user_id") == $m->id ? 'selected' : '' }}>
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
            <a href="{{ route('processes.index') }}" class="px-5 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors">
                Create Process
            </button>
        </div>
    </form>
</div>

{{-- Dynamic Department / Template Data Script --}}
<script>
    const departmentsData = @json($departments);

    document.addEventListener('DOMContentLoaded', function() {
        const deptSelect = document.getElementById('department_select');
        const templateSelect = document.getElementById('template_select');
        const checklistContainer = document.getElementById('checklist-container');
        const countBadge = document.getElementById('items-count-badge');
        const nameInput = document.getElementById('process_name');
        const descInput = document.getElementById('process_desc');

        deptSelect.addEventListener('change', function() {
            const deptId = parseInt(this.value);
            templateSelect.innerHTML = '<option value="">Select a template...</option>';
            checklistContainer.innerHTML = '<div class="p-4 text-center text-sm text-slate-400 bg-slate-50 rounded-lg">Select a template above to load standard questions.</div>';
            countBadge.innerText = '0 questions';

            const dept = departmentsData.find(d => d.id === deptId);
            if (dept && dept.process_templates) {
                dept.process_templates.forEach(tpl => {
                    const opt = document.createElement('option');
                    opt.value = tpl.id;
                    opt.textContent = tpl.name;
                    templateSelect.appendChild(opt);
                });

                // Auto-select first template if only one exists
                if (dept.process_templates.length === 1) {
                    templateSelect.value = dept.process_templates[0].id;
                    templateSelect.dispatchEvent(new Event('change'));
                }
            }
        });

        templateSelect.addEventListener('change', function() {
            const deptId = parseInt(deptSelect.value);
            const tplId = parseInt(this.value);

            const dept = departmentsData.find(d => d.id === deptId);
            if (!dept) return;

            const tpl = dept.process_templates ? dept.process_templates.find(t => t.id === tplId) : null;
            if (!tpl) {
                checklistContainer.innerHTML = '<div class="p-4 text-center text-sm text-slate-400 bg-slate-50 rounded-lg">Select a template above to load standard questions.</div>';
                countBadge.innerText = '0 questions';
                return;
            }

            if (!nameInput.value || nameInput.value === '') {
                nameInput.value = tpl.name;
            }
            if (!descInput.value || descInput.value === '') {
                descInput.value = tpl.description || '';
            }

            if (tpl.items && tpl.items.length > 0) {
                countBadge.innerText = `${tpl.items.length} questions`;
                checklistContainer.innerHTML = '';

                tpl.items.forEach((item, index) => {
                    const row = document.createElement('div');
                    row.className = 'flex items-start gap-3 py-3 px-2 rounded-lg hover:bg-slate-50 transition-colors';
                    row.innerHTML = `
                        <input type="hidden" name="items[${index}][template_item_id]" value="${item.id}">
                        <input type="hidden" name="items[${index}][question]" value="${item.question.replace(/"/g, '&quot;')}">
                        <input type="hidden" name="items[${index}][response_type]" value="${item.response_type}">
                        <input type="hidden" name="items[${index}][sort_order]" value="${index + 1}">
                        <input type="hidden" name="items[${index}][is_required]" value="${item.is_required ? 1 : 0}">
                        <input type="hidden" name="items[${index}][is_enabled]" value="0">
                        <div class="pt-0.5">
                            <input type="checkbox" name="items[${index}][is_enabled]" value="1" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-slate-800">${item.question}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[11px] font-mono uppercase bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">${item.response_type}</span>
                                ${item.is_required ? '<span class="text-[11px] font-medium text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">Required</span>' : ''}
                            </div>
                        </div>
                    `;
                    checklistContainer.appendChild(row);
                });
            } else {
                checklistContainer.innerHTML = '<div class="p-4 text-center text-sm text-slate-400 bg-slate-50 rounded-lg">No questions defined in this template.</div>';
                countBadge.innerText = '0 questions';
            }
        });
    });
</script>
@endsection
