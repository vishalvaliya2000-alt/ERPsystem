<!DOCTYPE html>
<html lang="en" class="h-full bg-[#F5F6F8]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenant Directory — Super-Admin</title>
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
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full bg-[#F5F6F8] font-sans antialiased text-neutral-900 selection:bg-[#D7FF53] selection:text-[#091315]">

    <nav class="h-16 bg-[#091315] text-white px-6 flex items-center justify-between border-b border-neutral-800">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-[#D7FF53] text-[#091315] flex items-center justify-center font-black text-sm">
                ⚡
            </div>
            <div>
                <span class="font-black text-base tracking-tight">ERPSaaS Central</span>
                <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-widest font-mono ml-2">Super-Admin</span>
            </div>
        </div>
        <div class="flex items-center gap-4 text-xs font-bold">
            <a href="{{ route('central.superadmin.dashboard') }}" class="text-neutral-300 hover:text-white">Dashboard</a>
            <a href="{{ route('central.superadmin.tenants') }}" class="text-[#D7FF53]">Tenants</a>
            <a href="{{ route('central.home') }}" target="_blank" class="text-neutral-400 hover:text-white">View Site ↗</a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 space-y-6">
        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-[#091315]">All Provisioned Tenants</h1>
                <p class="text-xs text-neutral-500 font-medium">Manage, inspect, and configure isolated tenant databases</p>
            </div>

            <!-- Search and Filter Form -->
            <form action="{{ route('central.superadmin.tenants') }}" method="GET" class="flex items-center gap-2">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search subdomain, company, email..."
                       class="px-3.5 py-2 rounded-xl bg-white border border-neutral-200 text-xs w-64 focus:outline-hidden focus:ring-2 focus:ring-[#091315]">
                <select name="status" class="px-3 py-2 rounded-xl bg-white border border-neutral-200 text-xs">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                </select>
                <button type="submit" class="px-3 py-2 rounded-xl bg-[#091315] text-[#D7FF53] text-xs font-bold">
                    Filter
                </button>
            </form>
        </div>

        <!-- Tenants Directory Table -->
        <div class="bg-white rounded-3xl border border-neutral-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-[#F5F6F8] text-neutral-500 font-mono uppercase text-[10px]">
                        <tr>
                            <th class="px-5 py-3">Tenant ID / Subdomain</th>
                            <th class="px-5 py-3">Company Legal Name</th>
                            <th class="px-5 py-3">Owner Contact</th>
                            <th class="px-5 py-3">Domain</th>
                            <th class="px-5 py-3">Plan</th>
                            <th class="px-5 py-3">Created Date</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse($tenants as $t)
                            <tr class="hover:bg-neutral-50/60">
                                <td class="px-5 py-3.5 font-bold font-mono text-neutral-900">
                                    {{ $t->id }}
                                </td>
                                <td class="px-5 py-3.5 font-bold text-neutral-900">
                                    {{ $t->name }}
                                    @if($t->gstin)
                                        <span class="block text-[10px] text-neutral-400 font-mono">GST: {{ $t->gstin }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="font-bold text-neutral-800">{{ $t->owner_name ?? '—' }}</span>
                                    <span class="block text-[10px] text-neutral-400">{{ $t->owner_email ?? '' }}</span>
                                </td>
                                <td class="px-5 py-3.5 font-mono">
                                    <a href="//{{ $t->primary_domain }}" target="_blank" class="text-blue-600 hover:underline">
                                        {{ $t->primary_domain ?? ($t->id . '.erpsaas.com') }}
                                    </a>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="px-2 py-0.5 rounded-full font-bold font-mono text-[10px] {{ $t->plan === 'Premium' ? 'bg-[#F3FED4] text-[#091315] border border-[#D7FF53]' : 'bg-neutral-100 text-neutral-700' }}">
                                        {{ $t->plan }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-neutral-500 font-mono">
                                    {{ $t->created_at ? $t->created_at->format('d/m/Y') : '—' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="px-2 py-0.5 rounded-full font-bold font-mono text-[10px] {{ $t->status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                        {{ strtoupper($t->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right space-x-2">
                                    <form action="{{ route('central.superadmin.tenants.toggle', $t->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 rounded-lg text-[10px] font-bold border transition-colors {{ $t->status === 'active' ? 'border-amber-300 text-amber-700 hover:bg-amber-50' : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50' }}">
                                            {{ $t->status === 'active' ? 'Suspend' : 'Activate' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('central.superadmin.tenants.impersonate', $t->id) }}" method="POST" class="inline" target="_blank">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-[#091315] text-[#D7FF53] hover:bg-neutral-800">
                                            Inspect ↗
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-8 text-center text-neutral-400">
                                    No tenants match your search.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($tenants->hasPages())
                <div class="p-4 border-t border-neutral-100">
                    {{ $tenants->links() }}
                </div>
            @endif
        </div>
    </main>

</body>
</html>
