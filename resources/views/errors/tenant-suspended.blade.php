<!DOCTYPE html>
<html lang="en" class="h-full bg-[#F5F6F8]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Workspace Suspended — ERPSaaS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Outfit"', '"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        erpsaa: {
                            dark: '#091315',
                            lime: '#D7FF53',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full bg-[#F5F6F8] text-neutral-900 font-sans flex items-center justify-center p-4 antialiased">
    <div class="max-w-md w-full bg-white rounded-3xl p-8 border border-neutral-200/80 shadow-xl text-center space-y-6">
        <div class="w-14 h-14 rounded-2xl bg-amber-500 text-white flex items-center justify-center mx-auto shadow-md">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>

        <div class="space-y-2">
            <span class="inline-block px-3 py-1 rounded-full bg-amber-50 text-amber-800 text-xs font-bold font-mono border border-amber-200">
                403 • Workspace Suspended
            </span>
            <h1 class="text-2xl font-black text-[#091315] tracking-tight">
                Access Temporarily Paused
            </h1>
            <p class="text-sm text-neutral-500">
                This workspace has been suspended by administration or billing review. Please contact support or your organization owner.
            </p>
        </div>

        <div class="p-4 rounded-2xl bg-[#F5F6F8] border border-neutral-200/60 text-xs text-neutral-600 text-left">
            <p class="font-bold text-neutral-800 mb-1">Support Contact</p>
            <p class="text-neutral-500">Email: <a href="mailto:support@erpsaas.in" class="text-blue-600 underline">support@erpsaas.in</a></p>
            <p class="text-neutral-500">Phone: +91 98765 43210 (Mon-Sat, 9AM - 7PM IST)</p>
        </div>

        <p class="text-[11px] text-neutral-400 font-mono">
            ERPSaaS Cloud Operations
        </p>
    </div>
</body>
</html>
