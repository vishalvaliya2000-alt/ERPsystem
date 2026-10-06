<!DOCTYPE html>
<html lang="en" class="h-full bg-[#F5F6F8]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Workspace Not Found — ERPSaaS</title>
    <!-- Google Fonts: Outfit & Plus Jakarta Sans & IBM Plex Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Outfit"', '"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"IBM Plex Mono"', 'monospace'],
                    },
                    colors: {
                        erpsaa: {
                            dark: '#091315',
                            'dark-surface': '#112225',
                            lime: '#D7FF53',
                            'lime-hover': '#C8F043',
                            canvas: '#F5F6F8',
                            border: '#E2E6E8',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full bg-[#F5F6F8] text-neutral-900 font-sans flex items-center justify-center p-4 antialiased">
    <div class="max-w-md w-full bg-white rounded-3xl p-8 border border-neutral-200/80 shadow-xl text-center space-y-6">
        <!-- Logo -->
        <div class="w-14 h-14 rounded-2xl bg-[#091315] text-[#D7FF53] flex items-center justify-center mx-auto shadow-md border border-neutral-800">
            <svg class="w-7 h-7 fill-current" viewBox="0 0 24 24">
                <path d="M13 2L4 14h6v8l9-12h-6z" />
            </svg>
        </div>

        <div class="space-y-2">
            <span class="inline-block px-3 py-1 rounded-full bg-rose-50 text-rose-700 text-xs font-bold font-mono border border-rose-200">
                404 • Workspace Not Found
            </span>
            <h1 class="text-2xl font-black text-[#091315] tracking-tight">
                No Workspace Located
            </h1>
            <p class="text-sm text-neutral-500">
                The workspace <code class="px-2 py-0.5 rounded bg-neutral-100 font-mono font-bold text-neutral-800">{{ $subdomain ?? 'unknown' }}</code> is not registered on this platform.
            </p>
        </div>

        <div class="p-4 rounded-2xl bg-[#F5F6F8] border border-neutral-200/60 text-xs text-neutral-600 text-left space-y-2">
            <div class="flex items-center gap-2 font-bold text-neutral-800">
                <span>💡</span> What can you do?
            </div>
            <ul class="list-disc list-inside space-y-1 text-neutral-500">
                <li>Check for typos in the subdomain URL.</li>
                <li>Search for your company workspace URL.</li>
                <li>Or register a new enterprise workspace.</li>
            </ul>
        </div>

        <div class="flex flex-col gap-2.5 pt-2">
            @php
                $centralDomain = config('tenancy.central_domains.0') ?? 'erpsaas.in';
                $centralPort = request()->getPort();
                $portSuffix = ($centralPort && !in_array($centralPort, [80, 443])) ? ':' . $centralPort : '';
                $scheme = request()->getScheme();
                $findUrl = "{$scheme}://{$centralDomain}{$portSuffix}/find-workspace";
                $registerUrl = "{$scheme}://{$centralDomain}{$portSuffix}/register";
            @endphp
            <a href="{{ $findUrl }}"
               class="w-full inline-flex items-center justify-center px-5 py-3 rounded-2xl bg-[#091315] hover:bg-neutral-800 text-[#D7FF53] font-bold text-sm shadow-md transition-all">
                🔍 Find My Workspace
            </a>
            <a href="{{ $registerUrl }}"
               class="w-full inline-flex items-center justify-center px-5 py-3 rounded-2xl bg-neutral-100 hover:bg-neutral-200 text-neutral-800 font-bold text-sm transition-all">
                ✨ Register New Workspace
            </a>
        </div>

        <p class="text-[11px] text-neutral-400 font-mono">
            ERPSaaS Multi-Tenant Cloud Platform
        </p>
    </div>
</body>
</html>
