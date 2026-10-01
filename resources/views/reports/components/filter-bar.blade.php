<div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 space-y-3">
    <form method="GET" action="{{ $action ?? url()->current() }}" class="space-y-3">
        {{-- Preset Buttons --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 mr-1">Period:</span>
                @php
                    $presets = [
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'this_week' => 'This Week',
                        'last_week' => 'Last Week',
                        'last_7_days' => 'Last 7 Days',
                        'this_month' => 'This Month',
                        'last_month' => 'Last Month',
                        'last_30_days' => 'Last 30 Days',
                    ];
                    $currentPreset = $dateInfo['preset'] ?? request('date_range', 'this_month');
                @endphp

                @foreach($presets as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['date_range' => $key, 'start_date' => null, 'end_date' => null, 'page' => null]) }}"
                       class="px-2.5 py-1 text-xs font-semibold rounded-lg transition-colors {{ $currentPreset === $key ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-2.5 py-1 rounded-md">
                    {{ $dateInfo['label'] ?? 'Selected Period' }}
                </span>
            </div>
        </div>

        {{-- Custom Date Range & Filters Row --}}
        <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="date_range" value="custom">
                <div class="flex items-center gap-1">
                    <label class="text-[11px] font-medium text-slate-500">From:</label>
                    <input type="date" name="start_date" value="{{ request('start_date', $dateInfo['start']->format('Y-m-d')) }}" class="text-xs border-slate-200 rounded-lg px-2 py-1 border focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex items-center gap-1">
                    <label class="text-[11px] font-medium text-slate-500">To:</label>
                    <input type="date" name="end_date" value="{{ request('end_date', $dateInfo['end']->format('Y-m-d')) }}" class="text-xs border-slate-200 rounded-lg px-2 py-1 border focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="submit" class="px-3 py-1 bg-slate-800 text-white text-xs font-semibold rounded-lg hover:bg-slate-700 transition">
                    Filter Date
                </button>
            </div>

            @if(isset($extraFilters) && $extraFilters)
                <div class="flex items-center gap-2">
                    {{ $extraFilters }}
                </div>
            @endif
        </div>
    </form>
</div>
