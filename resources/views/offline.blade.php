<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline Mode - ERPSaaS</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#091315">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&family=IBM+Plex+Mono:wght@500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'IBM Plex Mono', monospace; }
    </style>
</head>
<body class="bg-[#091315] text-white min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-[#112225] border border-neutral-800 rounded-3xl p-8 shadow-2xl text-center space-y-6">
        <!-- Brand Icon -->
        <div class="w-16 h-16 mx-auto rounded-2xl bg-neutral-900 border border-neutral-800 flex items-center justify-center text-[#D7FF53] shadow-inner">
            <svg class="w-8 h-8 fill-current" viewBox="0 0 24 24">
                <path d="M13 2L4 14h6v8l9-12h-6z" />
            </svg>
        </div>

        <div class="space-y-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-bold font-mono">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                <span>OFFLINE MODE</span>
            </span>
            <h1 class="text-xl font-black text-white">Connection Lost</h1>
            <p class="text-xs text-neutral-400 leading-relaxed">
                You are currently disconnected from the internet. ERPSaaS is running in offline mode.
            </p>
        </div>

        <!-- Offline Queue Status Box -->
        <div class="bg-neutral-900/80 border border-neutral-800 rounded-2xl p-4 text-left space-y-2">
            <div class="flex items-center justify-between text-xs">
                <span class="text-neutral-400">Offline Draft Queue:</span>
                <span id="offline-queue-badge" class="font-mono font-bold text-[#D7FF53]">Active</span>
            </div>
            <p class="text-[11px] text-neutral-400 leading-normal">
                Any orders, client leads, or field transactions created while offline remain securely cached on your device and will automatically sync once your connection resumes.
            </p>
        </div>

        <div class="space-y-3">
            <button onclick="window.location.reload()"
                    class="w-full py-3 bg-[#D7FF53] hover:bg-[#cbf540] text-neutral-950 font-black rounded-xl text-xs transition-transform active:scale-95 shadow-lg flex items-center justify-center gap-2">
                <span>🔄</span>
                <span>Retry Connection</span>
            </button>

            <a href="/" class="block text-xs font-bold text-neutral-400 hover:text-white transition-colors">
                Return to Cached Dashboard &rarr;
            </a>
        </div>

        <div class="pt-4 border-t border-neutral-800/80 text-[10px] text-neutral-400 font-mono">
            ERPSaaS Progressive Web Application
        </div>
    </div>

    <script>
        window.addEventListener('online', function() {
            window.location.reload();
        });
    </script>
</body>
</html>
