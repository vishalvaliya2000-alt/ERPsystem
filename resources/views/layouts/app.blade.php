<!DOCTYPE html>
<html lang="en" class="h-full bg-[#F5F6F8]">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') — {{ $currentTenant?->name ?? config('app.name', 'ERPSaaS') }}@else{{
        $title ?? ($currentTenant?->name ?? config('app.name', 'ERPSaaS')) }} — AI Operations & ERP @endif </title>

    <!-- Favicon & PWA Manifest -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#091315">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ERPSaaS">

    <!-- Google Fonts: Outfit & Plus Jakarta Sans & IBM Plex Mono (ERPSaaS Design System) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap"
        rel="stylesheet">

    @php
    $tenantTheme = $currentTenant?->settings['theme'] ?? null;
    $primaryColor = $tenantTheme['primary_color'] ?? '#091315';
    $accentColor = $tenantTheme['accent_color'] ?? '#D7FF53';
    @endphp

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    screens: {
                        'xs': '420px',
                    },
                    fontFamily: {
                        sans: ['"Outfit"', '"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        outfit: ['"Outfit"', 'sans-serif'],
                        mono: ['"IBM Plex Mono"', 'ui-monospace', 'monospace'],
                    },
                    colors: {
                        erpsaa: {
                            dark: '#091315',
                            'dark-surface': '#112225',
                            lime: '#D7FF53',
                            'lime-hover': '#C8F043',
                            'lime-faded': '#F3FED4',
                            canvas: '#F5F6F8',
                            border: '#E2E6E8',
                        },
                        brand: {
                            DEFAULT: '#091315',
                            accent: '#D7FF53',
                            50: '#F3FED4',
                            500: '#D7FF53',
                            600: '#C8F043',
                            900: '#091315',
                        },
                        neutral: {
                            850: '#112225',
                            950: '#091315',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Livewire Styles (TALL Stack) -->
    @livewireStyles

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    <!-- Global ERPSaaS Date Formatting Helper (Timezone-Safe for HTML Date Inputs) -->
    <script>
        window.formatInputDate = function(val) {
            if (!val) return '';
            if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val)) return val;
            const d = new Date(val);
            if (isNaN(d.getTime())) return typeof val === 'string' ? val.substring(0, 10) : '';
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        window.addDaysToDateString = function(dateStr, days) {
            if (!dateStr) return '';
            const clean = window.formatInputDate(dateStr);
            const parts = clean.split('-');
            if (parts.length !== 3) return dateStr;
            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1;
            const day = parseInt(parts[2], 10);
            const dt = new Date(year, month, day);
            dt.setDate(dt.getDate() + (parseInt(days, 10) || 0));
            const y = dt.getFullYear();
            const m = String(dt.getMonth() + 1).padStart(2, '0');
            const d = String(dt.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };
    </script>

    <style>
        :root {
            --brand-primary: #091315;
            --brand-accent: #D7FF53;
        }

        [x-cloak] {
            display: none !important;
        }

        /* Safe area insets for iPhones, flip phones, and foldables */
        .pb-safe {
            padding-bottom: max(0.5rem, env(safe-area-inset-bottom, 0px));
        }
        .pt-safe {
            padding-top: max(0.5rem, env(safe-area-inset-top, 0px));
        }
        .touch-scroll {
            -webkit-overflow-scrolling: touch;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        ::selection {
            background-color: #D7FF53;
            color: #091315;
        }

        /* Livewire Navigate Electric Lime Top Progress Bar */
        [wire\:loading-bar], .livewire-progress-bar {
            height: 3px !important;
            background: #D7FF53 !important;
        }

        @keyframes shimmer {
            0% {
                background-position: -200% 0;
            }

            100% {
                background-position: 200% 0;
            }
        }

        .skeleton-shimmer {
            background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
    </style>
</head>

<body
    class="h-full bg-[#F5F6F8] flex overflow-hidden font-sans antialiased text-neutral-900 selection:bg-[#D7FF53] selection:text-[#091315]"
    x-data="globalApp()">



    @php
    $navItems = [
        [
            'name' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'bento'
        ],
        [
            'name' => 'Clients',
            'route' => 'customers.index',
            'icon' => 'clients'
        ],
        [
            'name' => 'New Lead /Prospect',
            'route' => 'pipeline.index',
            'icon' => 'lead'
        ],
        [
            'name' => 'Sales Orders',
            'route' => 'orders.index',
            'icon' => 'orders'
        ],
        [
            'name' => 'Shipments',
            'route' => 'shipments.index',
            'icon' => 'shipments'
        ],
        [
            'name' => 'Invoices',
            'route' => 'invoices.index',
            'icon' => 'invoices'
        ],
        [
            'name' => 'Products',
            'route' => 'products.index',
            'icon' => 'products'
        ],
        [
            'name' => 'Transports',
            'route' => 'transporters.index',
            'icon' => 'transports'
        ],
        [
            'name' => 'Accounting',
            'route' => 'accounting.chart',
            'icon' => 'accounting'
        ],
        [
            'name' => 'Expenses & ITC',
            'route' => 'expenses.index',
            'icon' => 'expenses'
        ],
        [
            'name' => 'Fixed Assets',
            'route' => 'fixed-assets.index',
            'icon' => 'assets'
        ],
        [
            'name' => 'Service Reminders',
            'route' => 'service-reminders.index',
            'icon' => 'reminders'
        ],
        [
            'name' => 'Reports & Analytics',
            'route' => 'reports.index',
            'icon' => 'reports'
        ],
        [
            'name' => 'Settings',
            'route' => 'organization.settings',
            'icon' => 'settings'
        ],
    ];
    @endphp

    <!-- Desktop Sidebar Navigation (Large Screens, Laptops, Desktops) -->
    <aside :class="isSidebarCollapsed ? 'w-20' : 'w-64'"
        class="hidden lg:flex bg-white text-neutral-700 flex-col shrink-0 min-h-screen border-r border-neutral-200/80 transition-all duration-300 ease-in-out select-none relative z-40">
        <!-- Brand Header (ERPSaaS Black Squircle + Lime Bolt + Collapse Toggle) -->
        <div class="h-20 flex items-center px-4 gap-3 bg-white border-b border-neutral-100/80 justify-between">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 overflow-hidden min-w-0 group">
                <div
                    class="w-9 h-9 rounded-2xl bg-[#091315] flex items-center justify-center text-[#D7FF53] shrink-0 shadow-xs group-hover:scale-105 transition-transform border border-neutral-800">
                    <svg class="w-4.5 h-4.5 fill-current" viewBox="0 0 24 24">
                        <path d="M13 2L4 14h6v8l9-12h-6z" />
                    </svg>
                </div>
                <div x-show="!isSidebarCollapsed" x-transition.opacity class="min-w-0 flex-1 truncate">
                    <h1 class="font-black text-neutral-900 tracking-tight text-base font-display">ERPSaaS</h1>
                    <span
                        class="text-[10px] font-bold tracking-wider text-neutral-400 uppercase block font-mono">Platform</span>
                </div>
            </a>

            <!-- Collapse / Expand Toggle Button -->
            <button type="button" @click="toggleSidebar()"
                class="w-7 h-7 rounded-xl bg-neutral-100 hover:bg-[#091315] text-neutral-500 hover:text-[#D7FF53] flex items-center justify-center transition-all cursor-pointer shrink-0"
                :title="isSidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <svg class="w-3.5 h-3.5 transition-transform duration-300"
                    :class="isSidebarCollapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
        </div>

        <!-- Navigation Links -->
        <div class="flex-1 py-4 px-3 space-y-1.5 overflow-y-auto overflow-x-hidden">
            @foreach ($navItems as $item)
            @php
            $baseRoute = str_replace('.index', '', $item['route']);
            $isActive = request()->routeIs($item['route']) || request()->routeIs($baseRoute . '.*');
            @endphp
            <div class="relative group">
                <a href="{{ route($item['route']) }}" wire:navigate
                    class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-xs font-bold transition-all {{ $isActive ? 'bg-[#091315] text-[#D7FF53] shadow-sm' : 'text-neutral-600 hover:text-neutral-900 hover:bg-[#F5F6F8]' }} {{ $isActive && 'is-active' }}"
                    :class="isSidebarCollapsed ? 'justify-center px-2' : ''">
                    <span
                        class="shrink-0 {{ $isActive ? 'text-[#D7FF53]' : 'text-neutral-500 group-hover:text-neutral-900' }}">
                        @if($item['icon'] === 'bento')
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="3" y="3" width="7" height="7" rx="2" />
                            <rect x="14" y="3" width="7" height="7" rx="2" />
                            <rect x="3" y="14" width="7" height="7" rx="2" />
                            <rect x="14" y="14" width="7" height="7" rx="2" />
                        </svg>
                        @elseif($item['icon'] === 'clients')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        @elseif($item['icon'] === 'lead')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        @elseif($item['icon'] === 'orders')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        @elseif($item['icon'] === 'shipments')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                        </svg>
                        @elseif($item['icon'] === 'invoices')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                        </svg>

                        @elseif($item['icon'] === 'products')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        @elseif($item['icon'] === 'transports')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        @elseif($item['icon'] === 'accounting')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        @elseif($item['icon'] === 'expenses')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                        </svg>
                        @elseif($item['icon'] === 'assets')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        @elseif($item['icon'] === 'reminders')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        @elseif($item['icon'] === 'reports')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        @elseif($item['icon'] === 'settings')
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        @endif
                    </span>
                    <span x-show="!isSidebarCollapsed" x-transition.opacity class="truncate">{{ $item['name'] }}</span>
                </a>

                <!-- Collapsed Tooltip -->
                <div x-show="isSidebarCollapsed"
                    class="absolute left-full ml-3 top-1/2 -translate-y-1/2 px-3 py-1.5 bg-[#091315] text-[#D7FF53] text-[11px] font-bold rounded-xl shadow-xl whitespace-nowrap z-50 pointer-events-none group-hover:block hidden font-display border border-neutral-800">
                    {{ $item['name'] }}
                </div>
            </div>
            @endforeach
        </div>

        <!-- Footer Subtle Status -->
        <div class="p-3 border-t border-neutral-100 bg-white">
            <div class="flex items-center gap-2.5" :class="isSidebarCollapsed ? 'justify-center' : 'px-2'">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
                <div x-show="!isSidebarCollapsed" x-transition.opacity class="min-w-0 truncate">
                    <p class="text-xs font-bold text-neutral-900 truncate">{{ $currentTenant?->name ?? config('app.name', 'ERPSaaS') }}</p>
                    <p class="text-[10px] text-neutral-400 font-mono">Workspace Online</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Mobile Navigation Drawer (For Smartphones, Flip Phones, Foldables & Tablets) -->
    <div x-show="isMobileMenuOpen" x-cloak class="relative z-50 lg:hidden" aria-modal="true" role="dialog">
        <!-- Backdrop Overlay -->
        <div x-show="isMobileMenuOpen"
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="isMobileMenuOpen = false"
            class="fixed inset-0 bg-black/60 backdrop-blur-xs"></div>

        <div class="fixed inset-0 flex">
            <!-- Off-canvas panel -->
            <div x-show="isMobileMenuOpen"
                x-transition:enter="transition ease-in-out duration-300 transform"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in-out duration-300 transform"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                class="relative mr-14 flex w-full max-w-xs flex-1 flex-col bg-white pt-4 pb-4 shadow-2xl">

                <!-- Close Button (Top Right of Drawer) -->
                <div class="absolute top-4 right-4">
                    <button type="button" @click="isMobileMenuOpen = false"
                        class="w-8 h-8 rounded-full bg-neutral-100 hover:bg-neutral-200 text-neutral-600 flex items-center justify-center cursor-pointer transition-colors"
                        aria-label="Close menu">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Brand Header -->
                <div class="flex items-center gap-3 px-5 pb-4 border-b border-neutral-100">
                    <div class="w-10 h-10 rounded-2xl bg-[#091315] flex items-center justify-center text-[#D7FF53] shrink-0 border border-neutral-800 shadow-xs">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                            <path d="M13 2L4 14h6v8l9-12h-6z" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1 truncate">
                        <h2 class="font-black text-neutral-900 tracking-tight text-base font-display">ERPSaaS</h2>
                        <span class="text-[10px] font-bold tracking-wider text-neutral-400 uppercase block font-mono">Mobile Operations</span>
                    </div>
                </div>

                <!-- Navigation Links -->
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-1.5 touch-scroll">
                    @foreach ($navItems as $item)
                    @php
                    $baseRoute = str_replace('.index', '', $item['route']);
                    $isActive = request()->routeIs($item['route']) || request()->routeIs($baseRoute . '.*');
                    @endphp
                    <a href="{{ route($item['route']) }}" wire:navigate @click="isMobileMenuOpen = false"
                        class="flex items-center gap-3 px-3.5 py-3 rounded-2xl text-xs font-bold transition-all {{ $isActive ? 'bg-[#091315] text-[#D7FF53] shadow-sm' : 'text-neutral-700 hover:text-neutral-900 hover:bg-[#F5F6F8]' }}">
                        <span class="shrink-0 {{ $isActive ? 'text-[#D7FF53]' : 'text-neutral-500' }}">
                            @if($item['icon'] === 'bento')
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="2" /><rect x="14" y="3" width="7" height="7" rx="2" /><rect x="3" y="14" width="7" height="7" rx="2" /><rect x="14" y="14" width="7" height="7" rx="2" /></svg>
                            @elseif($item['icon'] === 'clients')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            @elseif($item['icon'] === 'lead')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                            @elseif($item['icon'] === 'orders')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            @elseif($item['icon'] === 'shipments')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" /></svg>
                            @elseif($item['icon'] === 'invoices')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" /></svg>
                            @elseif($item['icon'] === 'products')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            @elseif($item['icon'] === 'transports')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                            @elseif($item['icon'] === 'accounting')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                            @elseif($item['icon'] === 'expenses')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" /></svg>
                            @elseif($item['icon'] === 'assets')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            @elseif($item['icon'] === 'reminders')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                            @elseif($item['icon'] === 'reports')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                            @elseif($item['icon'] === 'settings')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            @endif
                        </span>
                        <span>{{ $item['name'] }}</span>
                    </a>
                    @endforeach
                </div>

                <!-- Footer: User Info & Logout -->
                <div class="p-4 border-t border-neutral-100 bg-neutral-50/50 space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-[#091315] text-[#D7FF53] flex items-center justify-center font-bold text-xs shrink-0">
                            {{ $currentUser?->initials ?? 'VV' }}
                        </div>
                        <div class="min-w-0 flex-1 truncate">
                            <p class="text-xs font-bold text-neutral-900 truncate">{{ $currentUser?->name ?? 'Vishal Valiya' }}</p>
                            <p class="text-[10px] text-neutral-400 font-mono truncate">{{ $currentTenant?->name ?? 'Real Dehydrates ERP' }}</p>
                        </div>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit"
                            class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl text-rose-600 bg-rose-50 hover:bg-rose-100 text-xs font-bold transition-colors cursor-pointer">
                            <span>🚪</span> Sign Out Securely
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 bg-[#F5F6F8] overflow-hidden">
        <!-- PWA Offline & Draft Sync Banner -->
        <div id="pwa-connection-banner" class="hidden"></div>

        <!-- Top Navbar (Breadcrumb, Search Ctrl+K, + Create Dropdown, Notifications, AI Copilot, User Profile) -->
        <header
            class="h-16 bg-white/90 backdrop-blur-md border-b border-neutral-200/80 px-3 sm:px-6 flex items-center justify-between sticky top-0 z-30 shadow-[0_1px_3px_rgba(0,0,0,0.02)]">
            <!-- Left: Mobile Hamburger & Breadcrumbs -->
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <!-- Mobile Hamburger Button -->
                <button type="button" @click="isMobileMenuOpen = true"
                    class="lg:hidden p-2 -ml-1 text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 rounded-xl transition-colors cursor-pointer shrink-0"
                    aria-label="Open Navigation Menu">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <!-- Mobile Brand Icon -->
                <a href="{{ route('dashboard') }}" class="flex lg:hidden items-center gap-2 shrink-0">
                    <div class="w-8 h-8 rounded-xl bg-[#091315] flex items-center justify-center text-[#D7FF53] shrink-0 border border-neutral-800 shadow-2xs">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                            <path d="M13 2L4 14h6v8l9-12h-6z" />
                        </svg>
                    </div>
                </a>

                <!-- Breadcrumb -->
                <div class="flex items-center gap-1.5 sm:gap-2 text-xs font-semibold text-neutral-500 min-w-0">
                    <a href="{{ route('dashboard') }}" wire:navigate
                        class="hidden sm:flex hover:text-neutral-900 transition-colors items-center gap-1.5 shrink-0">
                        <span class="w-2 h-2 rounded-full bg-[#D7FF53]"></span>
                        <span>ERPSaaS</span>
                        <span class="text-neutral-300">/</span>
                    </a>
                    <span class="text-neutral-900 font-bold font-display truncate">
                        @hasSection('title')
                        @yield('title')
                        @else
                        {{ ucfirst(str_replace(['.', 'index'], [' ', ''], request()->route()->getName())) }}
                        @endif
                    </span>
                </div>
            </div>

            <!-- Right Actions: Search (Ctrl+K), + Create Menu, Notifications, AI Copilot, User Profile -->
            <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
                <!-- Command Palette Search Button: Full on Desktop/Tablet, Icon on Mobile -->
                <button type="button" @click="$dispatch('open-command-palette')"
                    class="hidden md:flex items-center gap-2.5 px-3.5 py-2 rounded-full bg-[#F5F6F8] hover:bg-neutral-100 text-neutral-400 hover:text-neutral-700 text-xs font-medium border border-neutral-200/80 transition-all shadow-2xs group cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-neutral-400 group-hover:text-neutral-600" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <span class="text-neutral-500 font-medium">Search ERP...</span>
                    <kbd
                        class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-mono font-bold text-neutral-400 bg-white border border-neutral-200 rounded">
                        Ctrl+K
                    </kbd>
                </button>

                <!-- Mobile Search Icon Button -->
                <button type="button" @click="$dispatch('open-command-palette')"
                    class="flex md:hidden w-8 h-8 rounded-full bg-neutral-100/80 hover:bg-neutral-200 text-neutral-700 hover:text-neutral-900 items-center justify-center transition-all cursor-pointer shadow-2xs"
                    title="Search ERP (Ctrl+K)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>

                <!-- + Create Quick Action Dropdown -->
                <div class="relative" x-data="{ isQuickCreateOpen: false }">
                    <button type="button" @click="isQuickCreateOpen = !isQuickCreateOpen"
                        class="inline-flex items-center gap-1 sm:gap-1.5 px-2.5 sm:px-4 py-1.5 sm:py-2 rounded-full bg-[#091315] hover:bg-black text-[#D7FF53] font-bold text-xs shadow-xs transition-all cursor-pointer border border-neutral-800">
                        <span class="text-sm font-black leading-none">+</span>
                        <span class="hidden xs:inline sm:inline">Create</span>
                        <span class="text-[9px] sm:text-[10px] opacity-70">▾</span>
                    </button>

                    <div x-show="isQuickCreateOpen" @click.outside="isQuickCreateOpen = false" x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        class="absolute right-0 mt-2 w-56 max-w-[calc(100vw-1.5rem)] bg-white rounded-3xl shadow-xl border border-neutral-200/80 p-2 z-50 text-xs space-y-0.5">
                        <a href="{{ route('customers.index') }}"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-2xl hover:bg-[#F5F6F8] text-neutral-800 font-bold transition-colors">
                            <span
                                class="w-6 h-6 rounded-lg bg-neutral-100 flex items-center justify-center text-xs">👥</span>
                            <span>Customer Account</span>
                        </a>
                        <a href="{{ route('pipeline.index') }}"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-2xl hover:bg-[#F5F6F8] text-neutral-800 font-bold transition-colors">
                            <span
                                class="w-6 h-6 rounded-lg bg-neutral-100 flex items-center justify-center text-xs">🎯</span>
                            <span>Lead / Prospect</span>
                        </a>
                        <a href="{{ route('orders.index') }}"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-2xl hover:bg-[#F5F6F8] text-neutral-800 font-bold transition-colors">
                            <span
                                class="w-6 h-6 rounded-lg bg-neutral-100 flex items-center justify-center text-xs">📦</span>
                            <span>Sales Order</span>
                        </a>
                        <a href="{{ route('invoices.index') }}"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-2xl hover:bg-[#F5F6F8] text-neutral-800 font-bold transition-colors">
                            <span
                                class="w-6 h-6 rounded-lg bg-neutral-100 flex items-center justify-center text-xs">📑</span>
                            <span>Tax Invoice</span>
                        </a>
                        <a href="{{ route('shipments.index') }}"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-2xl hover:bg-[#F5F6F8] text-neutral-800 font-bold transition-colors">
                            <span
                                class="w-6 h-6 rounded-lg bg-neutral-100 flex items-center justify-center text-xs">🚚</span>
                            <span>Consignment Shipment</span>
                        </a>
                        <a href="{{ route('products.index') }}"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-2xl hover:bg-[#F5F6F8] text-neutral-800 font-bold transition-colors">
                            <span
                                class="w-6 h-6 rounded-lg bg-neutral-100 flex items-center justify-center text-xs">🏷️</span>
                            <span>Product Master</span>
                        </a>
                    </div>
                </div>

                <!-- Notifications Dropdown -->
                <div class="relative" x-data="{ isNotificationsOpen: false }">
                    <button type="button" @click="isNotificationsOpen = !isNotificationsOpen"
                        title="Operational Notifications"
                        class="relative w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-neutral-100/80 hover:bg-neutral-200 text-neutral-700 hover:text-neutral-900 flex items-center justify-center transition-all cursor-pointer shadow-2xs">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span
                            class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-[#D7FF53] ring-2 ring-white"></span>
                    </button>

                    <div x-show="isNotificationsOpen" @click.outside="isNotificationsOpen = false" x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-1.5rem)] bg-white rounded-3xl shadow-xl border border-neutral-200/80 p-4 z-50 text-xs space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-neutral-100">
                            <span class="font-bold text-neutral-900 font-display">Notifications</span>
                            <span
                                class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#F3FED4] text-[#091315] border border-[#D7FF53]">3
                                In Queue</span>
                        </div>
                        <div class="space-y-2 max-h-72 overflow-y-auto touch-scroll">
                            <a href="{{ route('invoices.index') }}"
                                class="block p-2.5 rounded-2xl bg-[#F5F6F8] hover:bg-neutral-100 transition-colors">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-neutral-900">Overdue AR Invoices</span>
                                    <span class="text-[10px] font-bold text-rose-600">Urgent</span>
                                </div>
                                <p class="text-[11px] text-neutral-500 mt-0.5">Review outstanding receivables requiring
                                    collection follow-ups.</p>
                            </a>
                            <a href="{{ route('orders.index') }}"
                                class="block p-2.5 rounded-2xl bg-[#F5F6F8] hover:bg-neutral-100 transition-colors">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-neutral-900">Pending Sales Dispatches</span>
                                    <span class="text-[10px] font-bold text-amber-600">Open PO</span>
                                </div>
                                <p class="text-[11px] text-neutral-500 mt-0.5">Check pending factory dispatch line
                                    items.</p>
                            </a>
                            <a href="{{ route('shipments.index') }}"
                                class="block p-2.5 rounded-2xl bg-[#F5F6F8] hover:bg-neutral-100 transition-colors">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-neutral-900">Consignments In-Transit</span>
                                    <span class="text-[10px] font-bold text-blue-600">Logistics</span>
                                </div>
                                <p class="text-[11px] text-neutral-500 mt-0.5">Real-time GPS and transporter tracking
                                    updates.</p>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- AI Copilot Sparkle Button (ERPSaaS Header) -->
                <a href="{{ route('assistant.index') }}" title="ERPSaaS AI Assistant Copilot"
                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-neutral-100/80 hover:bg-[#D7FF53] text-neutral-800 hover:text-[#091315] flex items-center justify-center transition-all cursor-pointer shadow-2xs hover:shadow-xs group">
                    <svg class="w-4 h-4 transition-transform group-hover:scale-110" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path
                            d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z" />
                        <path d="M5 3v4" />
                        <path d="M19 17v4" />
                        <path d="M3 5h4" />
                        <path d="M17 19h4" />
                    </svg>
                </a>

                <!-- Company & Firm Switcher (Multi-Company/Multi-Firm Context) -->
                @if(isset($availableCompanies) && $availableCompanies->isNotEmpty())
                    <div class="relative" x-data="{ isContextOpen: false }">
                        <button @click="isContextOpen = !isContextOpen"
                            title="Active Operating Company & Firm Unit"
                            class="flex items-center gap-2 px-2.5 sm:px-3 py-1.5 rounded-full hover:bg-neutral-100 transition-all cursor-pointer text-left border border-neutral-200/90 bg-white shadow-2xs group">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                            <div class="max-w-[110px] sm:max-w-[180px] truncate text-left">
                                <p class="text-xs font-black text-neutral-900 leading-none truncate">
                                    {{ $currentFirm?->firm_name ?? $currentCompany?->legal_name ?? 'Select Unit' }}
                                </p>
                                @if($currentFirm?->gstin)
                                    <p class="text-[9px] font-mono text-neutral-400 leading-none truncate mt-0.5">
                                        GST: {{ $currentFirm->gstin }}
                                    </p>
                                @endif
                            </div>
                            <span class="text-neutral-400 text-[10px] group-hover:text-neutral-700 transition-colors">▾</span>
                        </button>

                        <!-- Switcher Dropdown -->
                        <div x-show="isContextOpen" @click.outside="isContextOpen = false" x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            class="absolute right-0 mt-2 w-72 max-w-[calc(100vw-1.5rem)] bg-white rounded-3xl shadow-xl border border-neutral-200/80 p-3 z-50 text-xs space-y-2">
                            <div class="px-2 py-1 border-b border-neutral-100 flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-wider text-neutral-400 font-mono">Operating Units & Firms</span>
                                <a href="{{ route('organization.companies.index') }}" class="text-[10px] font-bold text-indigo-600 hover:underline">Manage</a>
                            </div>

                            <div class="max-h-60 overflow-y-auto space-y-2 divide-y divide-neutral-100 touch-scroll">
                                @foreach($availableCompanies as $comp)
                                    <div class="pt-1.5 first:pt-0 space-y-1">
                                        <p class="text-[10px] font-black text-neutral-500 uppercase tracking-wider font-mono px-2">{{ $comp->legal_name }}</p>
                                        @foreach($comp->firms as $f)
                                            @php
                                                $isSelected = $currentFirm && (int)$currentFirm->id === (int)$f->id;
                                            @endphp
                                            <form action="{{ route('organization.switch-context') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="firm_id" value="{{ $f->id }}">
                                                <button type="submit"
                                                    class="w-full text-left p-2 rounded-xl flex items-center justify-between transition-colors cursor-pointer {{ $isSelected ? 'bg-emerald-50 text-emerald-950 font-bold border border-emerald-200' : 'hover:bg-neutral-50 text-neutral-700' }}">
                                                    <div class="min-w-0 pr-2">
                                                        <p class="text-xs truncate font-bold">{{ $f->firm_name }}</p>
                                                        <p class="text-[10px] text-neutral-400 font-mono truncate">
                                                            {{ $f->gstin ?: 'No GSTIN' }} · {{ $f->state }}
                                                        </p>
                                                    </div>
                                                    @if($isSelected)
                                                        <span class="text-emerald-600 font-black text-xs shrink-0">✓</span>
                                                    @endif
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <!-- User Profile & Dropdown -->
                <div class="relative pl-1 sm:border-l sm:border-neutral-200" x-data="{ isProfileOpen: false }">
                    <button @click="isProfileOpen = !isProfileOpen"
                        class="flex items-center gap-1.5 sm:gap-2.5 p-1 sm:p-1.5 rounded-full hover:bg-neutral-100 transition-all cursor-pointer text-left border border-neutral-200/80 bg-white sm:pr-3 shadow-2xs">
                        <div
                            class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-[#091315] text-[#D7FF53] flex items-center justify-center font-bold text-xs shrink-0">
                            {{ $currentUser?->initials ?? 'VV' }}
                        </div>
                        <div class="hidden sm:block text-left">
                            <div class="flex items-center gap-1.5">
                                <p class="text-xs font-bold text-neutral-900 leading-tight">{{ $currentUser?->name ??
                                    'Vishal Valiya' }}</p>
                                <span
                                    class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-[#F5F6F8] text-neutral-700 border border-neutral-200">
                                    {{ $currentUser?->role ?? 'ADMIN' }}
                                </span>
                            </div>
                            <p class="text-[10px] font-medium text-neutral-400">{{ $currentUser?->designation ?? 'Administrator' }}</p>
                        </div>
                        <span class="text-neutral-400 text-xs ml-0.5 hidden sm:inline">▾</span>
                    </button>

                    <!-- Dropdown Menu -->
                    <div x-show="isProfileOpen" @click.outside="isProfileOpen = false" x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        class="absolute right-0 mt-2 w-64 max-w-[calc(100vw-1.5rem)] bg-white rounded-3xl shadow-xl border border-neutral-200/80 p-3 z-50 text-xs space-y-1">
                        <div class="px-3 py-2 border-b border-neutral-100">
                            <p class="font-extrabold text-neutral-900">{{ $currentUser?->name ?? 'Administrator' }}</p>
                            <p class="text-[11px] text-neutral-400 font-mono truncate">{{ $currentUser?->email ?? 'admin@example.com' }}</p>
                        </div>

                        <a href="{{ route('organization.settings') }}"
                            class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-full text-neutral-700 hover:bg-neutral-100 font-bold transition-colors">
                            <span>🏢</span> Company Profile & Settings
                        </a>

                        <a href="{{ route('organization.companies.index') }}"
                            class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-full text-neutral-700 hover:bg-neutral-100 font-bold transition-colors">
                            <span>🏭</span> Companies & Operating Firms
                        </a>

                        <a href="{{ route('organization.team') }}"
                            class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-full text-neutral-700 hover:bg-neutral-100 font-bold transition-colors">
                            <span>👥</span> Team & Staff Access
                        </a>

                        <a href="{{ route('organization.departments') }}"
                            class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-full text-neutral-700 hover:bg-neutral-100 font-bold transition-colors">
                            <span>🏛️</span> 4 Fixed Departments
                        </a>

                        <a href="{{ route('organization.audit-logs') }}"
                            class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-full text-neutral-700 hover:bg-neutral-100 font-bold transition-colors">
                            <span>🛡️</span> Security Audit Logs
                        </a>

                        <a href="{{ route('recycle-bin.index') }}"
                            class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-full text-neutral-700 hover:bg-neutral-100 font-bold transition-colors">
                            <span>🗑️</span> Recycle Bin & Recovery
                        </a>

                        <a href="{{ route('profile') }}"
                            class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-full text-neutral-700 hover:bg-neutral-100 font-bold transition-colors">
                            <span>⚙️</span> Password & Profile
                        </a>

                        <form action="{{ route('logout') }}" method="POST"
                            class="pt-1 border-t border-neutral-100">
                            @csrf
                            <button type="submit"
                                class="w-full flex items-center gap-2.5 px-3.5 py-2.5 rounded-full text-rose-600 hover:bg-rose-50 font-bold transition-colors text-left cursor-pointer">
                                <span>🚪</span> Sign Out Securely
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Page View -->
        <main class="flex-1 overflow-y-auto p-3.5 sm:p-5 md:p-6 lg:p-8 pb-24 lg:pb-8 touch-scroll">
            <div class="max-w-7xl mx-auto space-y-4 sm:space-y-6">
                {{ $slot ?? '' }}
                @yield('content')
            </div>
        </main>
    </div>

    <!-- Mobile Bottom Navigation Dock (Phones, Flip phones, Foldables) -->
    <nav class="lg:hidden fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur-md border-t border-neutral-200/80 z-40 pb-safe transition-transform duration-300 shadow-[0_-4px_16px_rgba(0,0,0,0.03)]">
        <div class="flex items-center justify-around h-16 px-1 max-w-lg mx-auto">
            <!-- 1. Home / Dashboard -->
            <a href="{{ route('dashboard') }}" wire:navigate
                class="flex flex-col items-center justify-center w-14 py-1 rounded-2xl transition-colors {{ request()->routeIs('dashboard') ? 'text-[#091315] font-black' : 'text-neutral-400 hover:text-neutral-700 font-semibold' }}">
                <div class="relative flex items-center justify-center p-1 rounded-xl {{ request()->routeIs('dashboard') ? 'bg-[#F3FED4]' : '' }}">
                    <svg class="w-5 h-5 {{ request()->routeIs('dashboard') ? 'text-[#091315]' : 'currentColor' }}" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="3" y="3" width="7" height="7" rx="2" />
                        <rect x="14" y="3" width="7" height="7" rx="2" />
                        <rect x="3" y="14" width="7" height="7" rx="2" />
                        <rect x="14" y="14" width="7" height="7" rx="2" />
                    </svg>
                </div>
                <span class="text-[10px] mt-0.5 tracking-tight">Home</span>
            </a>

            <!-- 2. Orders -->
            <a href="{{ route('orders.index') }}" wire:navigate
                class="flex flex-col items-center justify-center w-14 py-1 rounded-2xl transition-colors {{ request()->routeIs('orders.*') ? 'text-[#091315] font-black' : 'text-neutral-400 hover:text-neutral-700 font-semibold' }}">
                <div class="relative flex items-center justify-center p-1 rounded-xl {{ request()->routeIs('orders.*') ? 'bg-[#F3FED4]' : '' }}">
                    <svg class="w-5 h-5 {{ request()->routeIs('orders.*') ? 'text-[#091315]' : 'currentColor' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <span class="text-[10px] mt-0.5 tracking-tight">Orders</span>
            </a>

            <!-- 3. Central Quick Action -->
            <button type="button" @click="isQuickAddOpen = true"
                class="flex flex-col items-center justify-center -mt-5 group cursor-pointer focus:outline-hidden"
                title="Quick Add Action">
                <div class="w-12 h-12 rounded-full bg-[#091315] text-[#D7FF53] flex items-center justify-center shadow-lg shadow-black/25 group-hover:scale-105 group-active:scale-95 transition-all border-2 border-white">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </div>
                <span class="text-[9px] font-bold text-neutral-600 mt-0.5">Quick Add</span>
            </button>

            <!-- 4. Invoices -->
            <a href="{{ route('invoices.index') }}" wire:navigate
                class="flex flex-col items-center justify-center w-14 py-1 rounded-2xl transition-colors {{ request()->routeIs('invoices.*') ? 'text-[#091315] font-black' : 'text-neutral-400 hover:text-neutral-700 font-semibold' }}">
                <div class="relative flex items-center justify-center p-1 rounded-xl {{ request()->routeIs('invoices.*') ? 'bg-[#F3FED4]' : '' }}">
                    <svg class="w-5 h-5 {{ request()->routeIs('invoices.*') ? 'text-[#091315]' : 'currentColor' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                    </svg>
                </div>
                <span class="text-[10px] mt-0.5 tracking-tight">Invoices</span>
            </a>

            <!-- 5. More (Opens Full Drawer) -->
            <button type="button" @click="isMobileMenuOpen = true"
                class="flex flex-col items-center justify-center w-14 py-1 rounded-2xl transition-colors text-neutral-400 hover:text-neutral-900 cursor-pointer">
                <div class="relative flex items-center justify-center p-1 rounded-xl">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </div>
                <span class="text-[10px] mt-0.5 tracking-tight font-semibold">More</span>
            </button>
        </div>
    </nav>

    <!-- Global Livewire Command Palette Component -->
    <livewire:common.command-palette />

    <!-- Universal Quick Add Modal -->
    <template x-teleport="body">
        <div x-show="isQuickAddOpen" x-cloak
            class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div @click.outside="isQuickAddOpen = false"
                class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 w-full max-w-lg overflow-hidden max-h-[92vh] flex flex-col text-xs"
                x-data="{ tab: 'task' }">
                <div class="p-4 sm:p-5 bg-[#091315] text-white flex items-center justify-between shrink-0">
                    <div>
                        <h3 class="font-bold text-sm tracking-wide font-display">Quick Add Record</h3>
                        <p class="text-[11px] text-[#D7FF53]/90 font-mono">Add once — system connects, tracks, and reminds you.</p>
                    </div>
                    <button @click="isQuickAddOpen = false"
                        class="text-neutral-400 hover:text-white cursor-pointer text-base">✕</button>
                </div>

                <div class="flex flex-wrap border-b border-neutral-200/80 bg-neutral-50 p-2 gap-1.5 shrink-0">
                    <button @click="tab = 'task'"
                        :class="tab === 'task' ? 'bg-[#091315] text-[#D7FF53] shadow-xs' : 'bg-transparent text-neutral-600 hover:text-neutral-900'"
                        class="px-3.5 py-1.5 text-xs font-bold rounded-full cursor-pointer transition-colors">
                        Follow-up Task
                    </button>
                    <button @click="tab = 'lead'"
                        :class="tab === 'lead' ? 'bg-[#091315] text-[#D7FF53] shadow-xs' : 'bg-transparent text-neutral-600 hover:text-neutral-900'"
                        class="px-3.5 py-1.5 text-xs font-bold rounded-full cursor-pointer transition-colors">
                        Prospect / Lead
                    </button>
                    <button @click="tab = 'customer'"
                        :class="tab === 'customer' ? 'bg-[#091315] text-[#D7FF53] shadow-xs' : 'bg-transparent text-neutral-600 hover:text-neutral-900'"
                        class="px-3.5 py-1.5 text-xs font-bold rounded-full cursor-pointer transition-colors">
                        Customer Account
                    </button>
                </div>

                <!-- Forms Container -->
                <div class="p-4 sm:p-5 overflow-y-auto flex-1">
                    <!-- 1. Task Form -->
                    <form x-show="tab === 'task'" action="{{ route('tasks.store') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Company / Customer Name *</label>
                            <input type="text" name="customer_name" placeholder="e.g. Monk Foods or Gimi Michi"
                                class="w-full px-3.5 py-2 text-xs border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden"
                                required>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-neutral-700 mb-1">Priority</label>
                                <select name="priority"
                                    class="w-full px-3.5 py-2 text-xs border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden bg-white">
                                    <option value="URGENT">🔴 Urgent</option>
                                    <option value="HIGH" selected>🟠 High</option>
                                    <option value="MEDIUM">🟡 Medium</option>
                                    <option value="LOW">⚪ Low</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-neutral-700 mb-1">Due Date</label>
                                <input type="date" name="due_date" value="{{ date('Y-m-d') }}"
                                    class="w-full px-3.5 py-2 text-xs border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden"
                                    required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Context / Reason *</label>
                            <input type="text" name="reason" placeholder="e.g. Invoice payment pending over 30 days"
                                class="w-full px-3.5 py-2 text-xs border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden"
                                required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Scheduled Next Action *</label>
                            <input type="text" name="next_action" placeholder="e.g. Call purchase head at 11 AM"
                                class="w-full px-3.5 py-2 text-xs border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden"
                                required>
                        </div>
                        <div class="pt-3 border-t border-neutral-100 flex justify-end gap-2">
                            <button type="button" @click="isQuickAddOpen = false"
                                class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-full cursor-pointer">Cancel</button>
                            <button type="submit"
                                class="px-5 py-2 text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] rounded-full shadow-2xs cursor-pointer transition-colors border border-[#c8f043]">Save Task</button>
                        </div>
                    </form>

                    <!-- 2. Quick Lead Form -->
                    <form x-show="tab === 'lead'" action="{{ route('pipeline.store') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-neutral-700 mb-1">Company / Buyer Name *</label>
                            <input type="text" name="company_name" placeholder="e.g. Everest Spices Pvt Ltd"
                                class="w-full px-3.5 py-2 text-xs border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden"
                                required>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-neutral-700 mb-1">Contact Person</label>
                                <input type="text" name="contact_person" placeholder="e.g. Rajesh Shah"
                                    class="w-full px-3.5 py-2 text-xs border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-neutral-700 mb-1">Phone / WhatsApp</label>
                                <input type="text" name="phone" placeholder="e.g. 9820155443"
                                    class="w-full px-3.5 py-2 text-xs border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-neutral-700 mb-1">City</label>
                                <input type="text" name="city" placeholder="e.g. Mumbai"
                                    class="w-full px-3.5 py-2 text-xs border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-neutral-700 mb-1">Est. Value (₹)</label>
                                <input type="number" name="estimated_value" placeholder="e.g. 500000"
                                    class="w-full px-3.5 py-2 text-xs border border-neutral-300 rounded-xl focus:ring-2 focus:ring-[#091315] focus:outline-hidden">
                            </div>
                        </div>
                        <div class="pt-3 border-t border-neutral-100 flex justify-end gap-2">
                            <button type="button" @click="isQuickAddOpen = false"
                                class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-full cursor-pointer">Cancel</button>
                            <button type="submit"
                                class="px-5 py-2 text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] rounded-full shadow-2xs cursor-pointer transition-colors border border-[#c8f043]">Add Lead</button>
                        </div>
                    </form>

                    <!-- 3. Quick Customer Link -->
                    <div x-show="tab === 'customer'" class="space-y-4 py-2 text-center">
                        <div class="w-12 h-12 rounded-2xl bg-[#091315] text-[#D7FF53] font-black text-xl flex items-center justify-center mx-auto shadow-xs">
                            🏢
                        </div>
                        <div>
                            <h4 class="font-black text-neutral-900 text-sm">Add Full Customer Account</h4>
                            <p class="text-neutral-500 text-xs mt-1 max-w-sm mx-auto">Create a 360 buyer ledger with GSTIN verification, delivery branches, and custom payment credit limits.</p>
                        </div>
                        <div class="pt-2 flex justify-center gap-2">
                            <button type="button" @click="isQuickAddOpen = false"
                                class="px-4 py-2 text-xs font-bold text-neutral-600 hover:bg-neutral-100 rounded-full cursor-pointer">Close</button>
                            <a href="{{ route('customers.index') }}"
                                class="px-5 py-2 text-xs font-extrabold text-[#091315] bg-[#D7FF53] hover:bg-[#c8f043] rounded-full shadow-2xs cursor-pointer inline-flex items-center gap-1.5 border border-[#c8f043]">
                                <span>Go to Clients Directory</span>
                                <span>→</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Global Search Modal (⌘K) -->
    <template x-teleport="body">
        <div x-show="isSearchOpen" x-cloak
            class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-md flex items-start justify-center pt-4 sm:pt-16 p-2 sm:p-4">
            <div @click.outside="isSearchOpen = false"
                class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-neutral-200/80 w-full max-w-xl overflow-hidden max-h-[85vh] flex flex-col"
                x-data="searchModal()">
                <div class="p-3.5 sm:p-4 border-b border-neutral-200/80 flex items-center gap-2.5 sm:gap-3 shrink-0">
                    <svg class="w-5 h-5 text-neutral-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text"
                        placeholder="Search Customers, Orders, LRs, Invoices..."
                        x-model="query" @input.debounce.200ms="doSearch()"
                        class="flex-1 text-xs sm:text-sm outline-hidden font-medium text-neutral-900 placeholder:text-neutral-400 bg-transparent"
                        x-ref="searchInput">
                    <button @click="isSearchOpen = false"
                        class="text-neutral-400 hover:text-neutral-700 text-xs cursor-pointer p-1">✕</button>
                </div>

                <div class="overflow-y-auto p-2 divide-y divide-neutral-100 flex-1">
                    <template x-if="results.length > 0">
                        <div>
                            <template x-for="item in results" :key="item.title">
                                <a :href="item.url"
                                    class="flex items-center justify-between p-3 rounded-2xl hover:bg-[#F5F6F8] transition-colors">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-xs text-neutral-900" x-text="item.title"></span>
                                            <span
                                                class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-600 uppercase"
                                                x-text="item.tag"></span>
                                        </div>
                                        <p class="text-[11px] text-neutral-500" x-text="item.subtitle"></p>
                                    </div>
                                    <span class="text-neutral-400 text-xs">→</span>
                                </a>
                            </template>
                        </div>
                    </template>
                    <template x-if="results.length === 0 && query.length >= 2">
                        <p class="p-6 text-center text-xs text-neutral-400">No matching records found.</p>
                    </template>
                    <template x-if="query.length < 2">
                        <p class="p-6 text-center text-xs text-neutral-400">Type at least 2 characters to search across
                            customers, orders, LRs, and invoices.</p>
                    </template>
                </div>
            </div>
        </div>
    </template>



    <!-- Global App State -->
    <script>
        function globalApp() {
            return {
                isSidebarCollapsed: localStorage.getItem('erpsaa_sidebar_collapsed') === 'true',
                isMobileMenuOpen: false,
                toggleSidebar() {
                    this.isSidebarCollapsed = !this.isSidebarCollapsed;
                    localStorage.setItem('erpsaa_sidebar_collapsed', this.isSidebarCollapsed);
                },
                toggleMobileMenu() {
                    this.isMobileMenuOpen = !this.isMobileMenuOpen;
                },
                closeMobileMenu() {
                    this.isMobileMenuOpen = false;
                },
                isQuickAddOpen: false,
                isSearchOpen: false,
                isCommandPaletteOpen: false,
                commandQuery: '',
                searchResults: [],
                isSearching: false,
                async executeSearch() {
                    if (this.commandQuery.length < 2) {
                        this.searchResults = [];
                        return;
                    }
                    this.isSearching = true;
                    try {
                        const res = await fetch(`{{ route('search') }}?q=${encodeURIComponent(this.commandQuery)}`);
                        const data = await res.json();
                        this.searchResults = data.results || [];
                    } catch (err) {
                        console.error('Search error:', err);
                    } finally {
                        this.isSearching = false;
                    }
                },
                init() {
                    window.addEventListener('keydown', (e) => {
                        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                            e.preventDefault();
                            this.isCommandPaletteOpen = true;
                        }
                    });

                    window.addEventListener('resize', () => {
                        if (window.innerWidth >= 1024) {
                            this.isMobileMenuOpen = false;
                        }
                    });
                }
            }
        }

        function searchModal() {
            return {
                query: '',
                results: [],
                async doSearch() {
                    if (this.query.length < 2) {
                        this.results = [];
                        return;
                    }
                    const res = await fetch(`{{ route('search') }}?q=${encodeURIComponent(this.query)}`);
                    const data = await res.json();
                    this.results = data.results || [];
                }
            }
        }


        function toastManager() {
            return {
                toasts: [],
                init() {
                    @if(session('success'))
                        this.addToast({ type: 'success', message: @js(session('success')) });
                    @endif
                    @if(session('error'))
                        this.addToast({ type: 'error', message: @js(session('error')) });
                    @endif
                    @if(session('warning'))
                        this.addToast({ type: 'warning', message: @js(session('warning')) });
                    @endif
                    @if(session('info'))
                        this.addToast({ type: 'info', message: @js(session('info')) });
                    @endif

                    // High-precision smooth countdown loop with hover pause support
                    setInterval(() => {
                        const step = 25;
                        this.toasts.forEach(t => {
                            if (t.paused || !t.visible) return;
                            t.remaining = Math.max(0, t.remaining - step);
                            t.progressPct = Math.max(0, Math.min(100, (t.remaining / t.duration) * 100));
                            if (t.remaining <= 0) {
                                this.removeToast(t.id);
                            }
                        });
                    }, 25);
                },
                addToast(payload) {
                    let toast = typeof payload === 'string' ? { message: payload } : (payload || {});
                    let rawMsg = toast.message || '';
                    let type = (toast.type || 'success').toLowerCase();
                    let title = toast.title || '';

                    // Clean leading icons/emojis from server flash strings
                    rawMsg = rawMsg.replace(/^[✓⚠️ℹ️🎉⚡🚨\s]+/, '').trim();

                    if (!title) {
                        if (type === 'success') title = 'Success';
                        else if (type === 'error') title = 'Action Failed';
                        else if (type === 'warning') title = 'Attention Required';
                        else if (type === 'info') title = 'Information';
                        else title = 'Notification';
                    }

                    const duration = toast.duration || (type === 'error' ? 6000 : 4200);
                    const id = 'toast_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7);

                    const newToast = {
                        id: id,
                        type: type,
                        title: title,
                        message: rawMsg,
                        visible: true,
                        duration: duration,
                        remaining: duration,
                        progressPct: 100,
                        paused: false,
                        timestamp: 'Just now'
                    };

                    // Maximum 4 toasts visible at once to prevent viewport clutter
                    const activeToasts = this.toasts.filter(t => t.visible);
                    if (activeToasts.length >= 4) {
                        this.removeToast(activeToasts[0].id);
                    }

                    this.toasts.push(newToast);
                },
                pause(toast) {
                    toast.paused = true;
                },
                resume(toast) {
                    toast.paused = false;
                },
                removeToast(id) {
                    const index = this.toasts.findIndex(t => t.id === id);
                    if (index !== -1) {
                        this.toasts[index].visible = false;
                        setTimeout(() => {
                            this.toasts = this.toasts.filter(t => t.id !== id);
                        }, 250);
                    }
                }
            };
        }

        function liveTrackingModalApp() {
            return {
                isOpen: false,
                isLoading: false,
                isSyncing: false,
                copied: false,
                data: {
                    success: false,
                    mode: 'PORTAL',
                    transporter: '',
                    lr_number: '',
                    origin: '',
                    destination: '',
                    current_location: '',
                    booking_date: '',
                    expected_date: '',
                    delivered_date: '',
                    vehicle_no: '',
                    packages: '',
                    weight: '',
                    current_status: '',
                    stage: 'BOOKED',
                    scans: [],
                    external_url: '',
                    can_sync: false,
                    shipment_id: null
                },
                isStagePassed(stageName) {
                    const order = ['BOOKED', 'IN_TRANSIT', 'ARRIVED', 'OUT_FOR_DELIVERY', 'DELIVERED'];
                    const currentIdx = order.indexOf(this.data.stage);
                    const checkIdx = order.indexOf(stageName);
                    return checkIdx <= currentIdx;
                },
                copyLr() {
                    if (this.data.lr_number && navigator.clipboard) {
                        navigator.clipboard.writeText(this.data.lr_number).then(() => {
                            this.copied = true;
                            if (window.showToast) window.showToast(`📋 LR ${this.data.lr_number} copied!`, 'info');
                            setTimeout(() => this.copied = false, 2000);
                        }).catch(() => {});
                    }
                },
                refresh() {
                    this.open(this.data.shipment_id, this.data.lr_number, this.data.transporter, true);
                },
                async open(shipmentId, lrNumber, transporter, force = false) {
                    this.isOpen = true;
                    this.isLoading = true;
                    this.copied = false;
                    this.data.transporter = transporter || 'Transporter';
                    this.data.lr_number = lrNumber || '';
                    this.data.scans = [];
                    this.data.shipment_id = shipmentId || null;
                    this.data.current_status = 'Connecting to carrier network...';
                    this.data.stage = 'BOOKED';

                    if (lrNumber && navigator.clipboard) {
                        navigator.clipboard.writeText(lrNumber).catch(() => {});
                    }

                    try {
                        const forceParam = force ? (shipmentId ? '?force=1' : '&force=1') : '';
                        const url = shipmentId
                            ? `/shipments/${shipmentId}/live-tracking${forceParam}`
                            : `/api/transporter/tracking?transporter=${encodeURIComponent(transporter || '')}&lr=${encodeURIComponent(lrNumber || '')}${forceParam}`;

                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (res.ok) {
                            this.data = await res.json();
                        } else {
                            this.data.current_status = 'Could not fetch live scan. Carrier server may be busy.';
                        }
                    } catch (e) {
                        this.data.current_status = 'Unable to reach carrier network.';
                    } finally {
                        this.isLoading = false;
                    }
                },
                async syncDelivery() {
                    if (!this.data.shipment_id) return;
                    this.isSyncing = true;
                    try {
                        const res = await fetch(`/shipments/${this.data.shipment_id}/status`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                status: 'DELIVERED',
                                actual_delivery_date: this.data.delivered_date || null
                            })
                        });
                        const json = await res.json();
                        if (json.success) {
                            this.data.stage = 'DELIVERED';
                            this.data.can_sync = false;
                            if (window.showToast) window.showToast(json.message, 'success');
                            setTimeout(() => window.location.reload(), 900);
                        }
                    } catch (e) {
                        alert('Could not update status. Please try again.');
                    } finally {
                        this.isSyncing = false;
                    }
                }
            };
        }

        // Global helper for JS / Livewire calls
        window.showToast = function(messageOrOptions, type = 'success', title = null) {
            if (typeof messageOrOptions === 'object' && messageOrOptions !== null) {
                window.dispatchEvent(new CustomEvent('toast', { detail: messageOrOptions }));
            } else {
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: messageOrOptions, type, title } }));
            }
        };

        window.openLiveTracking = function(shipmentId, lrNumber, transporter) {
            window.dispatchEvent(new CustomEvent('open-live-tracking', {
                detail: { shipmentId, lrNumber, transporter }
            }));
        };

        window.trackConsignment = function(url, lr, transporter) {
            window.openLiveTracking(null, lr, transporter);
        };
    </script>

    <!-- Global Floating Top-Right Toast Notifications (Luxury ERPSaaS Design) -->
    <div x-data="toastManager()"
        @toast.window="addToast($event.detail)"
        @show-toast.window="addToast($event.detail)"
        @notify.window="addToast($event.detail)"
        class="fixed top-5 right-5 z-[99999] flex flex-col gap-3 max-w-sm sm:max-w-md w-full pointer-events-none px-4 sm:px-0">
        <template x-for="t in toasts" :key="t.id">
            <div x-show="t.visible"
                x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="opacity-0 translate-y-[-10px] translate-x-8 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 translate-x-0 scale-100"
                x-transition:leave="transition ease-in duration-200 transform"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-x-8 scale-95"
                @mouseenter="pause(t)"
                @mouseleave="resume(t)"
                class="pointer-events-auto bg-white text-neutral-900 border border-neutral-200/90 rounded-2xl shadow-[0_20px_45px_-12px_rgba(9,19,21,0.18),0_4px_16px_rgba(9,19,21,0.06)] relative overflow-hidden transition-all select-none">

                <!-- Left Accent Border Strip -->
                <div class="absolute left-0 top-0 bottom-0 w-1.5"
                    :class="{
                        'bg-[#D7FF53]': t.type === 'success',
                        'bg-rose-500': t.type === 'error',
                        'bg-amber-500': t.type === 'warning',
                        'bg-blue-600': t.type === 'info'
                    }"></div>

                <div class="p-4 pl-5 flex items-start gap-3.5">
                    <!-- Icon Squircle -->
                    <div class="shrink-0 mt-0.5">
                        <!-- Success: ERPSaaS Signature Obsidian Squircle + Lime Check -->
                        <template x-if="t.type === 'success'">
                            <div class="w-9 h-9 rounded-xl bg-[#091315] text-[#D7FF53] flex items-center justify-center shadow-xs border border-neutral-800">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                            </div>
                        </template>

                        <!-- Error: Soft Crimson Squircle with Alert Icon -->
                        <template x-if="t.type === 'error'">
                            <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 border border-rose-200 flex items-center justify-center shadow-xs">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="15" y1="9" x2="9" y2="15"></line>
                                    <line x1="9" y1="9" x2="15" y2="15"></line>
                                </svg>
                            </div>
                        </template>

                        <!-- Warning: Amber Squircle with Triangle -->
                        <template x-if="t.type === 'warning'">
                            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center shadow-xs">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path>
                                    <line x1="12" y1="9" x2="12" y2="13"></line>
                                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                </svg>
                            </div>
                        </template>

                        <!-- Info: Blue Squircle with Info Icon -->
                        <template x-if="t.type === 'info'">
                            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center shadow-xs">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                            </div>
                        </template>
                    </div>

                    <!-- Toast Body -->
                    <div class="flex-1 min-w-0 pt-0.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="text-xs font-bold tracking-tight text-neutral-900 font-display" x-text="t.title"></h4>
                                <span class="text-[9px] font-mono font-bold px-2 py-0.5 rounded-full"
                                    :class="{
                                        'bg-[#F3FED4] text-[#091315] border border-[#D7FF53]': t.type === 'success',
                                        'bg-rose-50 text-rose-700 border border-rose-200': t.type === 'error',
                                        'bg-amber-50 text-amber-700 border border-amber-200': t.type === 'warning',
                                        'bg-blue-50 text-blue-700 border border-blue-200': t.type === 'info'
                                    }"
                                    x-text="t.type.toUpperCase()"></span>
                            </div>

                            <!-- Dismiss button with circular hover ring -->
                            <button type="button" @click="removeToast(t.id)"
                                class="w-6 h-6 rounded-full flex items-center justify-center text-neutral-400 hover:text-neutral-900 hover:bg-neutral-100 transition-colors cursor-pointer shrink-0"
                                title="Dismiss notification">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>

                        <!-- Message -->
                        <p class="text-xs text-neutral-600 font-medium leading-relaxed mt-1 break-words" x-text="t.message"></p>

                        <!-- Footer micro-status -->
                        <div class="mt-2 flex items-center justify-between text-[10px] font-mono text-neutral-400">
                            <span x-show="t.paused" class="text-amber-600 font-bold flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                Timer Paused
                            </span>
                            <span x-show="!t.paused" x-text="t.timestamp"></span>
                            <span class="opacity-60 text-[9px]">Click ✕ or auto-closes</span>
                        </div>
                    </div>
                </div>

                <!-- Smooth Bottom Progress Meter -->
                <div class="h-1 w-full bg-neutral-100 overflow-hidden">
                    <div class="h-full transition-all duration-75 ease-linear"
                        :class="{
                            'bg-[#091315]': t.type === 'success',
                            'bg-rose-500': t.type === 'error',
                            'bg-amber-500': t.type === 'warning',
                            'bg-blue-600': t.type === 'info'
                        }"
                        :style="`width: ${t.progressPct}%`"></div>
                </div>
            </div>
        </template>
    </div>

    <!-- Global In-Page Live Transporter Tracking Modal (Zero Redirect) -->
    <div x-data="liveTrackingModalApp()"
        @open-live-tracking.window="open($event.detail.shipmentId, $event.detail.lrNumber, $event.detail.transporter)">
        <template x-teleport="body">
            <div x-show="isOpen" x-cloak
                class="fixed inset-0 z-[99990] bg-black/60 backdrop-blur-md flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
                <div @click.outside="isOpen = false" x-show="isOpen"
                    x-transition:enter="transition ease-out duration-300 transform"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200 transform"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-4"
                    class="bg-white rounded-3xl shadow-2xl border border-neutral-200/80 w-full max-w-2xl overflow-hidden flex flex-col max-h-[92vh] my-auto text-xs">
                    <!-- Header -->
                    <div class="bg-[#091315] text-white p-5 flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="w-10 h-10 rounded-2xl bg-[#D7FF53]/20 border border-[#D7FF53]/30 text-[#D7FF53] flex items-center justify-center text-lg shrink-0">
                                🚚
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="font-extrabold text-sm sm:text-base text-white truncate font-display"
                                        x-text="data.transporter"></h3>
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold tracking-wider uppercase border"
                                        :class="{
                                            'bg-emerald-500/20 text-emerald-300 border-emerald-500/30': data.mode === 'LIVE',
                                            'bg-[#D7FF53]/20 text-[#D7FF53] border-[#D7FF53]/30': data.mode !== 'LIVE'
                                        }"
                                        x-text="data.mode === 'LIVE' ? '🟢 Live Carrier Radar' : '📡 Carrier Hub'"></span>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs font-mono font-bold text-[#D7FF53]"
                                        x-text="'LR: ' + (data.lr_number || 'N/A')"></span>
                                    <button type="button" @click="copyLr()"
                                        class="text-[10px] text-neutral-400 hover:text-white transition-colors cursor-pointer"
                                        title="Copy LR number">
                                        <span x-text="copied ? '✓ Copied' : '📋 Copy'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="button" @click="isOpen = false"
                            class="w-8 h-8 rounded-full bg-neutral-800 hover:bg-neutral-700 text-neutral-400 hover:text-white flex items-center justify-center transition-colors cursor-pointer shrink-0 ml-2">
                            ✕
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-5 overflow-y-auto space-y-5 flex-1">

                        <!-- Loading State -->
                        <div x-show="isLoading"
                            class="py-12 flex flex-col items-center justify-center text-center space-y-3">
                            <div
                                class="w-10 h-10 border-3 border-amber-500 border-t-transparent rounded-full animate-spin">
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-800">Contacting Carrier Tracking Network...</p>
                                <p class="text-xs text-slate-400">Querying real-time satellite transit & branch scans
                                </p>
                            </div>
                        </div>

                        <div x-show="!isLoading" class="space-y-5">
                            <!-- Visual Milestone Stepper (4 Stages) -->
                            <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 sm:p-5">
                                <div class="grid grid-cols-4 gap-2 relative">
                                    <!-- Step 1: Booked -->
                                    <div class="flex flex-col items-center text-center relative z-10">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shadow-xs transition-all"
                                            :class="isStagePassed('BOOKED') ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-500'">
                                            <template x-if="isStagePassed('BOOKED')"><span>✓</span></template>
                                            <template x-if="!isStagePassed('BOOKED')"><span>1</span></template>
                                        </div>
                                        <span class="text-[11px] font-bold mt-1.5"
                                            :class="isStagePassed('BOOKED') ? 'text-emerald-700' : 'text-slate-500'">Booked</span>
                                        <span class="text-[9px] text-slate-400"
                                            x-text="data.booking_date || 'Dispatched'"></span>
                                    </div>

                                    <!-- Step 2: In-Transit -->
                                    <div class="flex flex-col items-center text-center relative z-10">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shadow-xs transition-all"
                                            :class="isStagePassed('IN_TRANSIT') ? 'bg-emerald-500 text-white' : (data.stage === 'IN_TRANSIT' ? 'bg-blue-600 text-white animate-pulse' : 'bg-slate-200 text-slate-500')">
                                            <template x-if="isStagePassed('IN_TRANSIT')"><span>✓</span></template>
                                            <template x-if="!isStagePassed('IN_TRANSIT')"><span>2</span></template>
                                        </div>
                                        <span class="text-[11px] font-bold mt-1.5"
                                            :class="isStagePassed('IN_TRANSIT') ? 'text-emerald-700' : (data.stage === 'IN_TRANSIT' ? 'text-blue-700 font-extrabold' : 'text-slate-500')">In
                                            Transit</span>
                                        <span class="text-[9px] text-slate-400">On Highway</span>
                                    </div>

                                    <!-- Step 3: Arrived Destination -->
                                    <div class="flex flex-col items-center text-center relative z-10">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shadow-xs transition-all"
                                            :class="isStagePassed('ARRIVED') ? 'bg-emerald-500 text-white' : (data.stage === 'ARRIVED' ? 'bg-amber-500 text-white ring-4 ring-amber-100 animate-pulse' : 'bg-slate-200 text-slate-500')">
                                            <template x-if="isStagePassed('ARRIVED')"><span>✓</span></template>
                                            <template x-if="!isStagePassed('ARRIVED')"><span>3</span></template>
                                        </div>
                                        <span class="text-[11px] font-bold mt-1.5"
                                            :class="isStagePassed('ARRIVED') ? 'text-emerald-700' : (data.stage === 'ARRIVED' ? 'text-amber-700 font-extrabold' : 'text-slate-500')">At
                                            Dest. Hub</span>
                                        <span class="text-[9px] text-slate-400"
                                            x-text="data.current_location || 'Unloaded'"></span>
                                    </div>

                                    <!-- Step 4: Delivered -->
                                    <div class="flex flex-col items-center text-center relative z-10">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shadow-xs transition-all"
                                            :class="data.stage === 'DELIVERED' ? 'bg-emerald-600 text-white ring-4 ring-emerald-100' : 'bg-slate-200 text-slate-500'">
                                            <template x-if="data.stage === 'DELIVERED'"><span>✓</span></template>
                                            <template x-if="data.stage !== 'DELIVERED'"><span>4</span></template>
                                        </div>
                                        <span class="text-[11px] font-bold mt-1.5"
                                            :class="data.stage === 'DELIVERED' ? 'text-emerald-700 font-extrabold' : 'text-slate-500'">Delivered</span>
                                        <span class="text-[9px] text-slate-400"
                                            x-text="data.delivered_date ? ('On ' + data.delivered_date) : (data.stage === 'DELIVERED' ? 'POD Done' : 'Pending POD')"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Route Cards -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200">
                                    <span
                                        class="text-[10px] uppercase tracking-wider font-bold text-slate-400 block">Origin
                                        Hub</span>
                                    <span class="text-xs font-black text-slate-800"
                                        x-text="data.origin || 'Factory Origin'"></span>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200">
                                    <span
                                        class="text-[10px] uppercase tracking-wider font-bold text-slate-400 block">Current
                                        Hub</span>
                                    <span class="text-xs font-black text-slate-800"
                                        x-text="data.current_location || (data.destination || 'Consignee Hub')"></span>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200">
                                    <span
                                        class="text-[10px] uppercase tracking-wider font-bold text-slate-400 block">Destination</span>
                                    <span class="text-xs font-black text-slate-800"
                                        x-text="data.destination || 'Consignee Hub'"></span>
                                </div>
                            </div>

                            <!-- Consignment Specifications Strip (Vehicle, Packages, Weight, Dates) -->
                            <template
                                x-if="data.vehicle_no || data.packages || data.weight || data.expected_date || data.delivered_date">
                                <div
                                    class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3.5 bg-[#091315] text-white rounded-2xl border border-neutral-800 shadow-inner">
                                    <div class="space-y-0.5">
                                        <span
                                            class="text-[9px] font-bold text-neutral-400 uppercase tracking-wider block">🚛
                                            Vehicle</span>
                                        <span class="text-xs font-mono font-bold text-[#D7FF53] truncate block"
                                            x-text="data.vehicle_no || 'Fleet Assigned'"></span>
                                    </div>
                                    <div class="space-y-0.5">
                                        <span
                                            class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">📦
                                            Packages</span>
                                        <span class="text-xs font-bold text-slate-100 truncate block"
                                            x-text="data.packages ? (data.packages + ' Units') : 'Standard'"></span>
                                    </div>
                                    <div class="space-y-0.5">
                                        <span
                                            class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">⚖️
                                            Weight</span>
                                        <span class="text-xs font-bold text-slate-100 truncate block"
                                            x-text="data.weight || 'As Bilty'"></span>
                                    </div>
                                    <div class="space-y-0.5">
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block"
                                            x-text="data.delivered_date ? '🏁 Delivered' : '📅 Expected'"></span>
                                        <span class="text-xs font-bold truncate block"
                                            :class="data.delivered_date ? 'text-emerald-400' : 'text-amber-300'"
                                            x-text="data.delivered_date || (data.expected_date || 'In Transit')"></span>
                                    </div>
                                </div>
                            </template>

                            <!-- Current Status Highlight -->
                            <div class="p-4 rounded-2xl border flex items-start gap-3 transition-colors" :class="{
                                    'bg-emerald-50/80 border-emerald-200/90 text-emerald-950': data.stage === 'DELIVERED',
                                    'bg-amber-50/80 border-amber-200/90 text-amber-950': data.stage === 'ARRIVED' || data.stage === 'OUT_FOR_DELIVERY',
                                    'bg-blue-50/80 border-blue-200/90 text-blue-950': data.stage !== 'DELIVERED' && data.stage !== 'ARRIVED' && data.stage !== 'OUT_FOR_DELIVERY'
                                }">
                                <span class="text-xl shrink-0" x-text="data.stage === 'DELIVERED' ? '✅' : '📍'"></span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-[11px] font-bold uppercase tracking-wider opacity-75">Latest
                                            Carrier Status</h4>
                                        <span
                                            class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-wide"
                                            :class="{
                                                'bg-emerald-200 text-emerald-900': data.stage === 'DELIVERED',
                                                'bg-amber-200 text-amber-900': data.stage === 'ARRIVED' || data.stage === 'OUT_FOR_DELIVERY',
                                                'bg-blue-200 text-blue-900': data.stage !== 'DELIVERED' && data.stage !== 'ARRIVED' && data.stage !== 'OUT_FOR_DELIVERY'
                                            }" x-text="data.stage"></span>
                                    </div>
                                    <p class="text-sm font-black mt-0.5"
                                        x-text="data.current_status || 'Consignment in transit'"></p>
                                </div>
                            </div>

                            <!-- Live Scan Timeline Table -->
                            <template x-if="data.scans && data.scans.length > 0">
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <h4
                                            class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                            <span>🕒</span>
                                            <span
                                                x-text="'Chronological Scan Activity (' + data.scans.length + ' Checkpoints)'"></span>
                                        </h4>
                                        <span class="text-[10px] text-slate-400 font-medium">Most recent first</span>
                                    </div>
                                    <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
                                        <table class="w-full text-left text-xs">
                                            <thead
                                                class="bg-slate-100 text-slate-600 font-bold border-b border-slate-200">
                                                <tr>
                                                    <th class="p-3">Location / Hub</th>
                                                    <th class="p-3">Activity / Status</th>
                                                    <th class="p-3 text-right">Date & Time</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 bg-white">
                                                <template x-for="(s, idx) in data.scans" :key="idx">
                                                    <tr
                                                        :class="idx === 0 ? 'bg-emerald-50/40 font-semibold' : 'hover:bg-slate-50'">
                                                        <td class="p-3 font-bold text-slate-900">
                                                            <div class="flex items-center gap-1.5">
                                                                <template x-if="idx === 0">
                                                                    <span
                                                                        class="w-2 h-2 rounded-full bg-emerald-500 animate-ping shrink-0"></span>
                                                                </template>
                                                                <span x-text="s.location"></span>
                                                            </div>
                                                        </td>
                                                        <td class="p-3">
                                                            <span class="px-2 py-0.5 rounded-md text-[11px] font-bold"
                                                                :class="{
                                                                    'bg-emerald-100 text-emerald-800': s.activity.toLowerCase().includes('deliver'),
                                                                    'bg-blue-100 text-blue-800': s.activity.toLowerCase().includes('arrive') || s.activity.toLowerCase().includes('reached'),
                                                                    'bg-amber-100 text-amber-800': s.activity.toLowerCase().includes('out for') || s.activity.toLowerCase().includes('transit'),
                                                                    'bg-slate-100 text-slate-700': !s.activity.toLowerCase().includes('deliver') && !s.activity.toLowerCase().includes('arrive') && !s.activity.toLowerCase().includes('reached') && !s.activity.toLowerCase().includes('out for')
                                                                }" x-text="s.activity"></span>
                                                        </td>
                                                        <td
                                                            class="p-3 text-right font-mono text-slate-600 whitespace-nowrap">
                                                            <span x-text="s.date"></span>
                                                            <span class="text-[10px] text-slate-400 ml-1"
                                                                x-text="s.time"></span>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>

                            <!-- If carrier portal fallback (when 0 scans recorded) -->
                            <template x-if="!data.scans || data.scans.length === 0">
                                <div class="p-4 rounded-2xl bg-blue-50/70 border border-blue-200 space-y-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-base">ℹ️</span>
                                        <h5 class="text-xs font-bold text-blue-950">Carrier Milestone Notification</h5>
                                    </div>
                                    <p class="text-xs text-blue-800 leading-relaxed">
                                        Consignment LR <b class="font-mono text-blue-950" x-text="data.lr_number"></b>
                                        is registered with <span class="font-bold" x-text="data.transporter"></span>.
                                        Checkpoints will appear automatically as barcode scanners process packages at
                                        transit stations.
                                    </p>
                                    <div class="pt-1" x-show="data.external_url">
                                        <a :href="data.external_url" target="_blank" rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-xs transition-all">
                                            <span>Carrier Web Portal</span>
                                            <span>↗</span>
                                        </a>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div
                        class="p-4 bg-[#F5F6F8] border-t border-neutral-200/80 flex items-center justify-between shrink-0">
                        <button type="button" @click="refresh()"
                            class="px-3.5 py-2 text-xs font-bold text-neutral-600 hover:text-neutral-900 hover:bg-neutral-200/60 rounded-full transition-all cursor-pointer flex items-center gap-1">
                            <span>🔄</span>
                            <span>Refresh Live Scan</span>
                        </button>

                        <div class="flex items-center gap-2">
                            <!-- Sync delivery button -->
                            <template x-if="data.can_sync && (data.stage === 'ARRIVED' || data.stage === 'DELIVERED')">
                                <button type="button" @click="syncDelivery()" :disabled="isSyncing"
                                    class="px-4 py-2 text-xs font-bold text-emerald-950 bg-[#D7FF53] hover:bg-[#c8f043] rounded-full transition-all cursor-pointer flex items-center gap-1 border border-[#c8f043]">
                                    <span>✓</span>
                                    <span x-text="isSyncing ? 'Updating ERP...' : 'Confirm Delivered in ERP'"></span>
                                </button>
                            </template>

                            <button type="button" @click="isOpen = false"
                                class="px-5 py-2 text-xs font-bold text-white bg-[#091315] hover:bg-neutral-800 rounded-full transition-all cursor-pointer">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
    @livewireScripts
    @stack('scripts')
    <script src="{{ asset('js/pwa-offline-sync.js') }}"></script>
</body>

</html>