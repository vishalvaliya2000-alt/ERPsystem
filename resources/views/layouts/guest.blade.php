<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#F5F6F8]">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('title')@yield('title') — {{ config('app.name', 'ERPSaaS') }}@else{{ $title ?? 'Operations Platform — ' . config('app.name', 'ERPSaaS') }}@endif</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    <!-- Google Fonts: Outfit, Plus Jakarta Sans, IBM Plex Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

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
                        display: ['"Outfit"', 'sans-serif'],
                        mono: ['"IBM Plex Mono"', 'ui-monospace', 'monospace'],
                    },
                    colors: {
                        erpsaa: {
                            dark: '#091315',
                            lime: '#D7FF53',
                            'lime-hover': '#c8f043',
                            'lime-faded': '#F3FED4',
                            canvas: '#F5F6F8',
                        },
                        brand: {
                            DEFAULT: '#091315',
                            accent: '#D7FF53',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] {
            display: none !important;
        }

        /* Safe area insets for iPhones and Foldables */
        .pb-safe {
            padding-bottom: max(0.5rem, env(safe-area-inset-bottom, 0px));
        }
        .pt-safe {
            padding-top: max(0.5rem, env(safe-area-inset-top, 0px));
        }

        ::selection {
            background-color: #D7FF53;
            color: #091315;
        }
    </style>
</head>

<body
    class="font-sans text-neutral-900 antialiased bg-[#F5F6F8] selection:bg-[#D7FF53] selection:text-[#091315] min-h-screen flex flex-col justify-center items-center py-8 sm:py-12 px-3 sm:px-6 lg:px-8 relative overflow-x-hidden pb-safe">

    <!-- Subtle Ambient Glow -->
    <div
        class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-96 bg-gradient-to-b from-[#D7FF53]/10 via-transparent to-transparent pointer-events-none blur-3xl">
    </div>

    <!-- Brand Header -->
    <div class="mb-6 sm:mb-8 text-center relative z-10">
        <a href="/" class="inline-flex flex-col items-center gap-3 group">
            <div
                class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl sm:rounded-3xl bg-[#091315] text-[#D7FF53] flex items-center justify-center font-black text-xl sm:text-2xl shadow-xl shadow-black/10 border border-neutral-800 group-hover:scale-105 transition-all">
                <svg class="w-6 h-6 sm:w-7 sm:h-7 fill-current" viewBox="0 0 24 24">
                    <path d="M13 2L4 14h6v8l9-12h-6z" />
                </svg>
            </div>
            <div class="text-center space-y-0.5">
                <span class="text-xl sm:text-2xl font-black tracking-tight text-neutral-900 block font-display">
                    {{ function_exists('tenant') && tenant('name') ? tenant('name') : config('app.name', 'ERPSaaS') }}
                </span>
                @if(function_exists('tenant') && tenant('id'))
                    <span class="text-[10px] sm:text-[11px] font-bold tracking-widest text-emerald-600 uppercase block font-mono">
                        Workspace: {{ tenant('id') }} • Online
                    </span>
                @else
                    <span class="text-[10px] sm:text-[11px] font-bold tracking-widest text-neutral-400 uppercase block font-mono">
                        Operations Platform
                    </span>
                @endif
            </div>
        </a>
    </div>

    <!-- Centered Card Slot -->
    <div
        class="relative z-10 w-full {{ $maxWidth ?? 'sm:max-w-md' }} bg-white px-5 sm:px-8 py-6 sm:py-9 shadow-[0_4px_24px_rgba(0,0,0,0.04)] rounded-2xl sm:rounded-[32px] border border-neutral-200/80">
        @yield('content')
        {{ $slot ?? '' }}
    </div>

    <!-- Subtle Footer -->
    <div class="mt-8 text-center text-xs text-neutral-400 font-mono relative z-10">
        &copy; {{ date('Y') }} ERPSaaS Operations Cloud. All rights reserved.
    </div>

</body>

</html>