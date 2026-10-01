@props([
    'route',
    'active' => false,
    'title',
    'badge' => null,
])

<div class="relative group/nav">
    <a href="{{ $route }}"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-150 {{ $active ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
       aria-label="{{ $title }}">
        <span class="shrink-0 w-5 h-5 flex items-center justify-center {{ $active ? 'text-white' : 'text-slate-500 group-hover/nav:text-slate-700' }}">
            {{ $slot }}
        </span>
        <span class="truncate sidebar-text tracking-normal whitespace-nowrap">{{ $title }}</span>

        @if($badge)
            <span class="ml-auto shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $active ? 'bg-blue-700 text-white' : 'bg-red-500 text-white' }} sidebar-badge">
                {{ $badge }}
            </span>
        @endif
    </a>

    {{-- Collapsed Hover Tooltip --}}
    <div class="sidebar-tooltip hidden absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1 bg-slate-900 text-white text-xs rounded shadow-lg whitespace-nowrap z-50 pointer-events-none">
        {{ $title }}
        @if($badge)
            <span class="ml-1.5 px-1.5 py-0.2 bg-red-500 text-white rounded-full font-bold text-[10px]">{{ $badge }}</span>
        @endif
    </div>
</div>
