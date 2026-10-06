<!DOCTYPE html>
<html lang="en" class="h-full bg-[#F5F6F8]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <title>Find Your Workspace — ERPSaaS</title>
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
                            lime: '#D7FF53',
                            canvas: '#F5F6F8',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full bg-[#F5F6F8] font-sans antialiased text-neutral-900 flex items-center justify-center p-4 selection:bg-[#D7FF53] selection:text-[#091315]">

    <div class="max-w-md w-full bg-white rounded-3xl p-8 border border-neutral-200/80 shadow-xl space-y-6">
        <div class="text-center space-y-2">
            <div class="w-12 h-12 rounded-2xl bg-[#091315] text-[#D7FF53] flex items-center justify-center mx-auto shadow-md border border-neutral-800">
                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                    <path d="M13 2L4 14h6v8l9-12h-6z" />
                </svg>
            </div>
            <h1 class="text-2xl font-black text-[#091315] tracking-tight">
                Find Your Workspace
            </h1>
            <p class="text-xs text-neutral-500 font-medium">
                Enter your company's subdomain or registered email to navigate to your isolated workspace login.
            </p>
        </div>

        @if ($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('central.find-workspace.lookup') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-bold text-neutral-700 mb-1">Workspace Subdomain or Company Name</label>
                <div class="relative flex items-center">
                    <input type="text" name="workspace" required autofocus
                           value="{{ old('workspace') }}"
                           placeholder="acme"
                           class="w-full px-4 py-3 rounded-2xl bg-[#F5F6F8] border border-neutral-200 text-sm font-mono lowercase pr-24 focus:outline-hidden focus:ring-2 focus:ring-[#091315]">
                    <span class="absolute right-3 text-xs font-bold font-mono text-neutral-400">
                        .{{ $tenantRootDomain }}
                    </span>
                </div>
            </div>

            <button type="submit"
                    class="w-full py-3.5 rounded-2xl bg-[#091315] hover:bg-neutral-800 text-[#D7FF53] font-black text-xs shadow-md transition-all flex items-center justify-center gap-2 border border-neutral-800 cursor-pointer">
                <span>Go to Workspace Login</span>
                <span>→</span>
            </button>
        </form>

        <div class="p-4 rounded-2xl bg-[#F5F6F8] border border-neutral-200/60 text-xs text-neutral-600 space-y-1">
            <span class="font-bold text-neutral-800 block">💡 Quick Tip</span>
            <p class="text-neutral-500 text-[11px]">
                You can also navigate directly to your workspace URL: <code class="font-bold text-neutral-700">https://yourcompany.{{ $tenantRootDomain }}</code>.
            </p>
        </div>

        <div class="pt-2 text-center text-xs text-neutral-500 space-y-2">
            <p>Don't have a workspace yet? <a href="{{ route('central.register') }}" class="font-bold text-neutral-900 underline">Register here</a></p>
            <p><a href="{{ route('central.home') }}" class="text-neutral-400 hover:text-neutral-700">← Back to Homepage</a></p>
        </div>
    </div>

</body>
</html>
