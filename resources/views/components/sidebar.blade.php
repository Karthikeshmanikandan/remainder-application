@php
    $unreadNotifications = auth()->user()->unreadNotifications()->count();
    $telegramAccount = auth()->user()->telegramAccount;
    $isTelegramConnected = $telegramAccount && $telegramAccount->isVerified() && $telegramAccount->is_active;
@endphp

<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-40 bg-white border-r border-slate-200 flex flex-col transition-all duration-300 ease-in-out -translate-x-full md:translate-x-0 w-64">
    
    {{-- Sidebar Header / Brand --}}
    <div class="h-16 flex items-center justify-between px-4 border-b border-slate-200 shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 overflow-hidden">
            <div class="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-lg shrink-0 shadow-sm">
                T
            </div>
            <span class="font-bold text-lg text-slate-800 tracking-tight whitespace-nowrap sidebar-text">
                TaskApp
            </span>
        </a>
        
        {{-- Desktop Toggle Button --}}
        <button id="sidebar-toggle-btn"
                type="button"
                aria-label="Toggle sidebar"
                class="hidden md:flex p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <svg id="collapse-icon" class="w-5 h-5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
        </button>

        {{-- Mobile Close Button --}}
        <button id="mobile-sidebar-close"
                type="button"
                aria-label="Close sidebar"
                class="md:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Navigation Links Container --}}
    <div class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
        
        {{-- Group 1: Workspace --}}
        <div>
            <div class="px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 sidebar-heading">
                Workspace
            </div>
            <nav class="space-y-1">
                {{-- Dashboard --}}
                <x-sidebar-item :route="route('dashboard')" :active="request()->routeIs('dashboard')" title="Dashboard">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </x-sidebar-item>

                {{-- Projects --}}
                <x-sidebar-item :route="route('projects.index')" :active="request()->routeIs('projects.*')" title="Projects">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                </x-sidebar-item>

                {{-- Tasks --}}
                <x-sidebar-item :route="route('tasks.index')" :active="request()->routeIs('tasks.*')" title="Tasks">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </x-sidebar-item>

                {{-- Recurring Tasks --}}
                <x-sidebar-item :route="route('recurring-tasks.index')" :active="request()->routeIs('recurring-tasks.*')" title="Recurring Tasks">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </x-sidebar-item>
            </nav>
        </div>

        {{-- Group: Processes & Checklists --}}
        <div>
            <div class="px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 sidebar-heading">
                Processes
            </div>
            <nav class="space-y-1">
                @if(! auth()->user()->isEmployee())
                    <x-sidebar-item :route="route('processes.index')" :active="request()->routeIs('processes.*')" title="Processes">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </x-sidebar-item>

                    <x-sidebar-item :route="route('admin.process-templates.index')" :active="request()->routeIs('admin.process-templates.*')" title="Templates">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                        </svg>
                    </x-sidebar-item>
                @endif

                <x-sidebar-item :route="route('my-checklists')" :active="request()->routeIs('my-checklists')" title="My Checklists">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </x-sidebar-item>
            </nav>
        </div>

        {{-- Group: Accountability (Management) --}}
        @if(! auth()->user()->isEmployee())
        <div>
            <div class="px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 sidebar-heading">
                Accountability
            </div>
            <nav class="space-y-1">
                <x-sidebar-item :route="route('process-executions.index')" :active="request()->routeIs('process-executions.index') && !request()->routeIs('my-checklists')" title="Monitoring">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </x-sidebar-item>

                <x-sidebar-item :route="route('accountability.departments.index')" :active="request()->routeIs('accountability.departments.*')" title="Departments">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </x-sidebar-item>

                <x-sidebar-item :route="route('accountability.users.index')" :active="request()->routeIs('accountability.users.*')" title="People">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </x-sidebar-item>

                <x-sidebar-item :route="route('accountability.processes.index')" :active="request()->routeIs('accountability.processes.*')" title="Process Stats">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </x-sidebar-item>

                <x-sidebar-item :route="route('accountability.escalations.index')" :active="request()->routeIs('accountability.escalations.*')" title="Escalations">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </x-sidebar-item>
            </nav>
        </div>

        {{-- Group: Business Intelligence & Reports (Management) --}}
        <div>
            <div class="px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 sidebar-heading">
                Reports
            </div>
            <nav class="space-y-1">
                <x-sidebar-item :route="route('reports.index')" :active="request()->routeIs('reports.index')" title="Overview">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </x-sidebar-item>

                <x-sidebar-item :route="route('reports.departments.index')" :active="request()->routeIs('reports.departments.*')" title="Departments">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </x-sidebar-item>

                <x-sidebar-item :route="route('reports.processes.index')" :active="request()->routeIs('reports.processes.*')" title="Processes">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </x-sidebar-item>

                <x-sidebar-item :route="route('reports.escalations.index')" :active="request()->routeIs('reports.escalations.*')" title="Escalations">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </x-sidebar-item>
            </nav>
        </div>
        @endif

        {{-- Group 2: Activity --}}
        <div>
            <div class="px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 sidebar-heading">
                Activity
            </div>
            <nav class="space-y-1">
                {{-- Reminders --}}
                <x-sidebar-item :route="route('reminders.index')" :active="request()->routeIs('reminders.*')" title="Reminders">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-sidebar-item>

                {{-- Notifications --}}
                <x-sidebar-item :route="route('notifications.index')" :active="request()->routeIs('notifications.*')" title="Notifications" :badge="$unreadNotifications > 0 ? ($unreadNotifications > 99 ? '99+' : $unreadNotifications) : null">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </x-sidebar-item>
            </nav>
        </div>

        {{-- Group 3: Integrations --}}
        <div>
            <div class="px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 sidebar-heading">
                Integrations
            </div>
            <nav class="space-y-1">
                <x-sidebar-item :route="route('settings.telegram')" :active="request()->routeIs('settings.telegram')" title="Telegram Bot">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </x-sidebar-item>
            </nav>
        </div>

        {{-- Group 4: Administration (Admin Only) --}}
        @if(auth()->user()->isAdmin())
        <div>
            <div class="px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 sidebar-heading">
                Administration
            </div>
            <nav class="space-y-1">
                {{-- Users --}}
                <x-sidebar-item :route="route('users.index')" :active="request()->routeIs('users.*')" title="User Management">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </x-sidebar-item>

                {{-- Organization Settings --}}
                <x-sidebar-item :route="route('admin.organization.show')" :active="request()->routeIs('admin.organization.*')" title="Organization">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </x-sidebar-item>

                {{-- Telegram-Only Employees --}}
                <x-sidebar-item :route="route('admin.telegram-employees.index')" :active="request()->routeIs('admin.telegram-employees.*')" title="Telegram Employees">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </x-sidebar-item>

                {{-- Departments --}}
                <x-sidebar-item :route="route('admin.departments.index')" :active="request()->routeIs('admin.departments.*')" title="Departments">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </x-sidebar-item>

                {{-- Audit Logs --}}
                <x-sidebar-item :route="route('admin.audit-logs.index')" :active="request()->routeIs('admin.audit-logs.*')" title="Audit Trail">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </x-sidebar-item>

                {{-- Admin Telegram Status --}}
                <x-sidebar-item :route="route('admin.telegram')" :active="request()->routeIs('admin.telegram')" title="Telegram Status">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </x-sidebar-item>
            </nav>
        </div>
        @endif

    </div>

    {{-- Sidebar Footer / User Identity & Logout --}}
    <div class="p-3 border-t border-slate-200 shrink-0 bg-slate-50/50">
        <div class="flex items-center gap-3 px-2 py-2 rounded-lg">
            {{-- User Initial Avatar --}}
            <div class="w-9 h-9 rounded-full bg-slate-200 text-slate-700 font-semibold flex items-center justify-center shrink-0 text-sm">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            
            <div class="flex-1 min-w-0 sidebar-text">
                <p class="text-sm font-semibold text-slate-800 truncate">{{ auth()->user()->name }}</p>
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full {{ $isTelegramConnected ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                    <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wide truncate">
                        {{ auth()->user()->role->value }}
                    </p>
                </div>
            </div>

            {{-- Logout Button Form --}}
            <form method="POST" action="{{ route('logout') }}" class="sidebar-text">
                @csrf
                <button type="submit"
                        aria-label="Log out"
                        title="Log out"
                        class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
            </form>
        </div>
    </div>

</aside>
