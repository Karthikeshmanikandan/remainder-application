@props(['dateInfo', 'actionUrl'])

<div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
    <form method="GET" action="{{ $actionUrl }}" class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Period:</span>
            
            <div class="flex flex-wrap items-center gap-1.5">
                @php
                    $presets = [
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'this_week' => 'This Week',
                        'last_7_days' => 'Last 7 Days',
                        'this_month' => 'This Month',
                        'last_month' => 'Last Month',
                    ];
                    $currentPreset = $dateInfo['preset'] ?? request('date_range', 'this_month');
                @endphp

                @foreach($presets as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['date_range' => $key, 'start_date' => null, 'end_date' => null]) }}"
                       class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors {{ $currentPreset === $key ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-2">
            <div class="text-xs font-semibold text-slate-700 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">
                <span>{{ $dateInfo['label'] }}</span>
            </div>
        </div>
    </form>
</div>
