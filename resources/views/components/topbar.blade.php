@php
    $unreadNotifications = auth()->user()->unreadNotifications()->count();
@endphp

<header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 lg:px-8 shrink-0 sticky top-0 z-30">
    <div class="flex items-center gap-3">
        {{-- Mobile Hamburger Button --}}
        <button id="mobile-hamburger"
                type="button"
                aria-label="Open sidebar"
                class="md:hidden p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        {{-- Dynamic Page Title / Breadcrumb Area --}}
        <div>
            <h1 class="text-lg font-bold text-slate-800 tracking-tight">
                @hasSection('title')
                    @yield('title')
                @else
                    @if(request()->routeIs('dashboard')) Dashboard
                    @elseif(request()->routeIs('projects.*')) Projects
                    @elseif(request()->routeIs('tasks.*')) Tasks
                    @elseif(request()->routeIs('recurring-tasks.*')) Recurring Tasks
                    @elseif(request()->routeIs('reminders.*')) Reminders
                    @elseif(request()->routeIs('notifications.*')) Notifications
                    @elseif(request()->routeIs('settings.telegram')) Telegram Settings
                    @elseif(request()->routeIs('admin.telegram')) Admin: Telegram Status
                    @elseif(request()->routeIs('users.*')) User Management
                    @else Workspace
                    @endif
                @endif
            </h1>
        </div>
    </div>

    {{-- Right Topbar Actions --}}
    <div class="flex items-center gap-3 sm:gap-4">
        {{-- Notification Bell --}}
        <a href="{{ route('notifications.index') }}"
           class="relative p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors"
           aria-label="View notifications">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            @if($unreadNotifications > 0)
                <span class="absolute top-1.5 right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white ring-2 ring-white">
                    {{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}
                </span>
            @endif
        </a>

        <div class="h-5 w-px bg-slate-200"></div>

        {{-- User Identity badge in Topbar --}}
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-xs">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div class="hidden sm:block text-left">
                <div class="text-xs font-semibold text-slate-800 leading-tight">{{ auth()->user()->name }}</div>
                <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider">{{ auth()->user()->role->value }}</div>
            </div>
        </div>
    </div>
</header>
