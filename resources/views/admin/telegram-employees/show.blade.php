@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900">{{ $telegramEmployee->name }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $telegramEmployee->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                    {{ $telegramEmployee->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">
                Telegram-Only Employee • <span class="font-mono">{{ $telegramEmployee->employee_code }}</span> • {{ $telegramEmployee->department ? $telegramEmployee->department->name : 'No Department' }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.telegram-employees.edit', $telegramEmployee) }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold uppercase tracking-wider rounded-lg transition">
                Edit
            </a>
            <a href="{{ route('admin.telegram-employees.index') }}" class="text-sm text-slate-600 hover:text-slate-900">
                &larr; All Employees
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-lg bg-emerald-50 text-emerald-800 text-sm font-medium border border-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    {{-- Telegram Account Linking Card --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-base font-bold text-slate-900">Telegram Bot Connection</h2>
            @if($telegramAccount && $telegramAccount->isVerified())
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> 🟢 Linked
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-700 bg-rose-50 px-3 py-1 rounded-full border border-rose-200">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span> 🔴 Not Linked
                </span>
            @endif
        </div>

        @if($telegramAccount && $telegramAccount->isVerified())
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 bg-slate-50 p-4 rounded-xl text-sm border border-slate-100">
                <div>
                    <span class="text-xs text-slate-500 font-medium block">Username</span>
                    <span class="font-semibold text-slate-900">{{ $telegramAccount->username ? '@'.$telegramAccount->username : '—' }}</span>
                </div>
                <div>
                    <span class="text-xs text-slate-500 font-medium block">Employee Name</span>
                    <span class="font-semibold text-slate-900">{{ $telegramEmployee->name }}</span>
                </div>
                <div>
                    <span class="text-xs text-slate-500 font-medium block">Connected</span>
                    <span class="text-slate-700">{{ $telegramAccount->verified_at ? $telegramAccount->verified_at->format('d M Y') : '—' }}</span>
                </div>
                <div>
                    <span class="text-xs text-slate-500 font-medium block">Chat ID</span>
                    <span class="font-mono text-slate-600" title="Masked for security">{{ $telegramAccount->masked_chat_id ?? '••••••' }}</span>
                </div>
            </div>

            <div class="pt-2 flex items-center justify-between">
                <p class="text-xs text-slate-500">
                    This employee can receive task assignments and complete process checklists directly in Telegram.
                </p>
                <form method="POST" action="{{ route('admin.telegram-employees.unlink', $telegramEmployee) }}" onsubmit="return confirm('Are you sure you want to unlink this Telegram account? The employee will no longer receive Telegram notifications until re-linked.');">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 text-xs text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 font-semibold rounded-lg transition">
                        Unlink Telegram
                    </button>
                </form>
            </div>
        @else
            <div class="space-y-4">
                <p class="text-sm text-slate-600">
                    Connect this employee's Telegram account so they can receive tasks and respond to process checklists without requiring web login credentials.
                </p>

                @if($telegramAccount && $telegramAccount->hasValidVerificationCode())
                    @php
                        $code = $telegramAccount->verification_code;
                        $link = $deepLink ?? ("https://t.me/" . ($botUsername ?? 'MyCompanyBot') . "?start=" . $code);
                    @endphp
                    <div class="p-5 bg-blue-50/80 border border-blue-200 rounded-xl space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-blue-100 pb-3">
                            <div>
                                <span class="text-xs uppercase font-bold tracking-wider text-blue-700">Telegram Linking Link</span>
                                <h3 class="text-sm font-semibold text-slate-900">Employee: {{ $telegramEmployee->name }}</h3>
                            </div>
                            <span class="text-xs font-medium text-blue-700 bg-blue-100/70 px-2.5 py-1 rounded-full">
                                Expires in 15 minutes ({{ $telegramAccount->verification_expires_at->diffForHumans() }})
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider block mb-1">One-Time Code</label>
                                <div class="font-mono text-xl font-bold text-slate-900 bg-white px-3 py-2 rounded-lg border border-blue-200 inline-block">
                                    {{ $code }}
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider block mb-1">Telegram Deep Link</label>
                                <div class="flex items-center gap-2">
                                    <input type="text" id="telegram-link-input" readonly value="{{ $link }}"
                                           class="w-full text-xs font-mono bg-white border-blue-200 rounded-lg text-slate-700 select-all">
                                    <button type="button" onclick="copyTelegramLink()" id="copy-btn"
                                            class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm whitespace-nowrap transition">
                                        Copy Link
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-blue-800">
                            <p class="flex items-center gap-1.5 font-medium">
                                <span>👉</span> Ask the employee to open this Telegram link and press <strong>Start</strong>.
                            </p>
                            <form method="POST" action="{{ route('admin.telegram-employees.generate-code', $telegramEmployee) }}">
                                @csrf
                                <button type="submit" class="text-xs text-blue-700 hover:text-blue-900 font-semibold underline">
                                    Refresh Code
                                </button>
                            </form>
                        </div>
                    </div>

                    <script>
                        function copyTelegramLink() {
                            const input = document.getElementById('telegram-link-input');
                            input.select();
                            navigator.clipboard.writeText(input.value).then(() => {
                                const btn = document.getElementById('copy-btn');
                                const originalText = btn.innerText;
                                btn.innerText = 'Copied!';
                                btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                                btn.classList.add('bg-emerald-600');
                                setTimeout(() => {
                                    btn.innerText = originalText;
                                    btn.classList.remove('bg-emerald-600');
                                    btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                                }, 2500);
                            });
                        }
                    </script>
                @else
                    <div class="p-6 bg-slate-50 border border-dashed border-slate-300 rounded-xl text-center space-y-3">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 mx-auto flex items-center justify-center font-bold text-lg">
                            ✈
                        </div>
                        <h3 class="text-sm font-semibold text-slate-800">No Active Linking Link</h3>
                        <p class="text-xs text-slate-500 max-w-md mx-auto">
                            Generate a secure, one-time 15-minute link for this employee. They can simply click the link on Telegram to connect their chat.
                        </p>
                        <form method="POST" action="{{ route('admin.telegram-employees.generate-code', $telegramEmployee) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold uppercase tracking-wider rounded-lg shadow-sm transition">
                                Generate Telegram Link
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Details & Info --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Employee Details</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-xs text-slate-500 font-medium">Full Name</dt>
                <dd class="font-semibold text-slate-800">{{ $telegramEmployee->name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500 font-medium">Employee Code</dt>
                <dd class="font-mono text-slate-800">{{ $telegramEmployee->employee_code }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500 font-medium">Phone</dt>
                <dd class="text-slate-800">{{ $telegramEmployee->phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500 font-medium">Department</dt>
                <dd class="text-slate-800">{{ $telegramEmployee->department ? $telegramEmployee->department->name : '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs text-slate-500 font-medium">Notes</dt>
                <dd class="text-slate-800 whitespace-pre-line">{{ $telegramEmployee->notes ?? 'No notes recorded.' }}</dd>
            </div>
        </dl>
    </div>
</div>
@endsection
