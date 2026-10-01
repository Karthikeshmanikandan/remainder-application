<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'TaskApp') }} - Employee Task Reminder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Smooth layout transitions */
        #sidebar {
            will-change: width, transform;
        }
        #main-wrapper {
            will-change: margin-left;
        }

        /* Collapsed state styles applied via .sidebar-collapsed on body */
        body.sidebar-collapsed #sidebar {
            width: 4.5rem !important; /* 72px */
        }
        body.sidebar-collapsed #sidebar .sidebar-text,
        body.sidebar-collapsed #sidebar .sidebar-heading,
        body.sidebar-collapsed #sidebar .sidebar-badge {
            display: none !important;
        }
        body.sidebar-collapsed #sidebar #collapse-icon {
            transform: rotate(180deg);
        }
        body.sidebar-collapsed #sidebar .group\/nav:hover .sidebar-tooltip {
            display: block !important;
        }
        @media (min-width: 768px) {
            body:not(.sidebar-collapsed) #main-wrapper {
                margin-left: 16rem; /* 256px */
            }
            body.sidebar-collapsed #main-wrapper {
                margin-left: 4.5rem; /* 72px */
            }
        }
    </style>
    <script>
        // Apply persisted state immediately to prevent layout shift on load
        (function() {
            try {
                if (localStorage.getItem('taskapp_sidebar_collapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-collapsed-init');
                    window.addEventListener('DOMContentLoaded', function() {
                        document.body.classList.add('sidebar-collapsed');
                    });
                }
            } catch (e) {}
        })();
    </script>
</head>
<body class="h-full text-slate-900 antialiased font-sans flex flex-col">

    @auth
        {{-- Left Collapsible Sidebar --}}
        <x-sidebar />

        {{-- Mobile Overlay Backdrop --}}
        <div id="mobile-backdrop"
             class="fixed inset-0 bg-slate-900/40 z-30 hidden md:hidden transition-opacity duration-300 opacity-0"
             aria-hidden="true"></div>

        {{-- Main Page Structure --}}
        <div id="main-wrapper" class="flex-1 flex flex-col min-h-screen transition-all duration-300 ease-in-out">
            
            {{-- Top Header --}}
            <x-topbar />

            {{-- Main Scrollable Content Area --}}
            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
                {{-- Flash Notifications --}}
                @if(session('success'))
                    <div class="mb-6 flex items-center gap-3 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm shadow-sm" role="alert">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-6 flex items-center gap-3 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm shadow-sm" role="alert">
                        <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">{{ session('error') }}</span>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>

    @else
        {{-- Guest Layout (e.g. Login) --}}
        <div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8 bg-slate-50">
            @if(session('success'))
                <div class="max-w-md mx-auto mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm shadow-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="max-w-md mx-auto mb-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm shadow-sm">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </div>
    @endauth

    {{-- Vanilla JavaScript for Sidebar & Responsive Drawer --}}
    @auth
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const body = document.body;
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebar-toggle-btn');
            const hamburgerBtn = document.getElementById('mobile-hamburger');
            const mobileCloseBtn = document.getElementById('mobile-sidebar-close');
            const backdrop = document.getElementById('mobile-backdrop');

            // 1. Desktop Toggle Handler
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    body.classList.toggle('sidebar-collapsed');
                    const isCollapsed = body.classList.contains('sidebar-collapsed');
                    try {
                        localStorage.setItem('taskapp_sidebar_collapsed', isCollapsed ? 'true' : 'false');
                    } catch (e) {}
                });
            }

            // 2. Mobile Drawer Open
            function openMobileSidebar() {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
                setTimeout(() => backdrop.classList.remove('opacity-0'), 10);
            }

            // 3. Mobile Drawer Close
            function closeMobileSidebar() {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('opacity-0');
                setTimeout(() => backdrop.classList.add('hidden'), 300);
            }

            if (hamburgerBtn) hamburgerBtn.addEventListener('click', openMobileSidebar);
            if (mobileCloseBtn) mobileCloseBtn.addEventListener('click', closeMobileSidebar);
            if (backdrop) backdrop.addEventListener('click', closeMobileSidebar);

            // 4. Keyboard accessibility (Escape closes mobile drawer)
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !sidebar.classList.contains('-translate-x-full') && window.innerWidth < 768) {
                    closeMobileSidebar();
                }
            });
        });
    </script>
    @endauth

</body>
</html>