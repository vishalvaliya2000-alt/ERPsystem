<!DOCTYPE html>
<html lang="en" class="h-full bg-[#F5F6F8]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <title>ERPSaaS — Enterprise Cloud ERP for Indian Manufacturers & Exporters</title>
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
                        sans: ['"Outfit"', '"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
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
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full bg-[#F5F6F8] font-sans antialiased text-neutral-900 selection:bg-[#D7FF53] selection:text-[#091315]">

    <!-- Navigation Header -->
    <nav class="h-20 bg-white/90 backdrop-blur-md border-b border-neutral-200/80 sticky top-0 z-50 px-4 sm:px-8 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-[#091315] flex items-center justify-center text-[#D7FF53] shadow-md border border-neutral-800">
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                    <path d="M13 2L4 14h6v8l9-12h-6z" />
                </svg>
            </div>
            <div>
                <span class="font-black text-xl text-[#091315] tracking-tight block leading-tight">ERPSaaS</span>
                <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-widest font-mono">Platform</span>
            </div>
        </div>

        <div class="hidden md:flex items-center gap-6 text-sm font-bold text-neutral-600">
            <a href="#features" class="hover:text-neutral-900 transition-colors">Features</a>
            <a href="#pricing" class="hover:text-neutral-900 transition-colors">Pricing</a>
            <a href="#security" class="hover:text-neutral-900 transition-colors">Security & Isolation</a>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('central.find-workspace') }}"
               class="px-4 py-2.5 rounded-2xl text-xs font-bold text-neutral-700 hover:text-neutral-900 bg-neutral-100 hover:bg-neutral-200 transition-colors">
                Find Workspace
            </a>
            <a href="{{ route('central.register') }}"
               class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-[#091315] text-[#D7FF53] hover:bg-neutral-800 shadow-sm transition-all border border-neutral-800">
                Get Started Free →
            </a>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="py-16 sm:py-24 px-4 sm:px-8 max-w-7xl mx-auto text-center space-y-8">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#F3FED4] border border-[#D7FF53] text-[#091315] text-xs font-bold shadow-xs">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Database-Per-Tenant Architecture • 100% Data Isolation
        </div>

        <h1 class="text-4xl sm:text-6xl font-black text-[#091315] tracking-tight max-w-4xl mx-auto leading-[1.1]">
            The Modern ERP Built for <br class="hidden sm:inline">
            <span class="text-emerald-700 underline decoration-[#D7FF53] decoration-wavy decoration-2">Indian Manufacturers</span> & Exporters
        </h1>

        <p class="text-lg sm:text-xl text-neutral-600 max-w-2xl mx-auto font-medium leading-relaxed">
            Manage your entire operational lifecycle from CRM, Samples & Quotations to Sales Orders, Transporter LR tracking, GST Invoicing, E-way bills, and Double-Entry Accounting.
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
            <a href="{{ route('central.register') }}"
               class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-[#091315] text-[#D7FF53] hover:bg-black font-extrabold text-base shadow-xl transition-transform hover:scale-105 border border-neutral-800">
                Launch Your Workspace Now
            </a>
            <a href="{{ route('central.find-workspace') }}"
               class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-white text-neutral-800 hover:bg-neutral-100 font-bold text-base border border-neutral-300 shadow-sm transition-colors">
                Sign In to Existing Workspace
            </a>
        </div>

        <!-- Metric Badges -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto pt-10">
            <div class="bg-white p-5 rounded-3xl border border-neutral-200/80 shadow-xs text-center">
                <p class="text-2xl font-black text-[#091315] font-mono">100%</p>
                <p class="text-xs font-bold text-neutral-400 mt-1 uppercase">Database Isolation</p>
            </div>
            <div class="bg-white p-5 rounded-3xl border border-neutral-200/80 shadow-xs text-center">
                <p class="text-2xl font-black text-[#091315] font-mono">GST & E-Way</p>
                <p class="text-xs font-bold text-neutral-400 mt-1 uppercase">Ready & Automated</p>
            </div>
            <div class="bg-white p-5 rounded-3xl border border-neutral-200/80 shadow-xs text-center">
                <p class="text-2xl font-black text-[#091315] font-mono">Multi-Firm</p>
                <p class="text-xs font-bold text-neutral-400 mt-1 uppercase">Company Switcher</p>
            </div>
            <div class="bg-white p-5 rounded-3xl border border-neutral-200/80 shadow-xs text-center">
                <p class="text-2xl font-black text-[#091315] font-mono">₹ Indian</p>
                <p class="text-xs font-bold text-neutral-400 mt-1 uppercase">Accounting & TCS/TDS</p>
            </div>
        </div>
    </header>

    <!-- Bento Features Section -->
    <section id="features" class="py-16 px-4 sm:px-8 max-w-7xl mx-auto">
        <div class="text-center space-y-3 mb-12">
            <span class="text-xs font-bold uppercase tracking-widest text-emerald-700 font-mono">Engineered for Bharat</span>
            <h2 class="text-3xl sm:text-4xl font-black text-[#091315] tracking-tight">Everything Indian Enterprises Need</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Bento Card 1 -->
            <div class="bg-white rounded-3xl p-8 border border-neutral-200/80 shadow-sm space-y-4 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-2xl bg-[#091315] text-[#D7FF53] flex items-center justify-center font-bold text-xl">
                    📦
                </div>
                <h3 class="text-lg font-black text-[#091315]">CRM, Samples & Quotations</h3>
                <p class="text-xs text-neutral-600 leading-relaxed">
                    Convert raw leads into commercial customers. Track evaluation sample dispatches via DTDC/courier, log trial feedback, and generate white-labeled PDF quotations.
                </p>
            </div>

            <!-- Bento Card 2 -->
            <div class="bg-white rounded-3xl p-8 border border-neutral-200/80 shadow-sm space-y-4 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-2xl bg-[#091315] text-[#D7FF53] flex items-center justify-center font-bold text-xl">
                    🚚
                </div>
                <h3 class="text-lg font-black text-[#091315]">Logistics & LR Management</h3>
                <p class="text-xs text-neutral-600 leading-relaxed">
                    Partial & full order shipments, Lorry Receipt (LR) tracking, transporter directories, delivery proof uploads, and automated E-way bill generation with barcode support.
                </p>
            </div>

            <!-- Bento Card 3 -->
            <div class="bg-white rounded-3xl p-8 border border-neutral-200/80 shadow-sm space-y-4 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-2xl bg-[#091315] text-[#D7FF53] flex items-center justify-center font-bold text-xl">
                    📑
                </div>
                <h3 class="text-lg font-black text-[#091315]">GST Invoices & Profit Check</h3>
                <p class="text-xs text-neutral-600 leading-relaxed">
                    Automatic CGST/SGST/IGST breakdown, Section 206C(1H) TCS, Section 194Q TDS, live cost vs profit margin calculation per invoice line, and consolidated billing.
                </p>
            </div>

            <!-- Bento Card 4 -->
            <div class="bg-white rounded-3xl p-8 border border-neutral-200/80 shadow-sm space-y-4 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-2xl bg-[#091315] text-[#D7FF53] flex items-center justify-center font-bold text-xl">
                    📊
                </div>
                <h3 class="text-lg font-black text-[#091315]">Double-Entry Accounting</h3>
                <p class="text-xs text-neutral-600 leading-relaxed">
                    Standard Indian Chart of Accounts with automatic journal voucher postings from invoices, payments, and expenses with Input Tax Credit (ITC) eligibility tracking.
                </p>
            </div>

            <!-- Bento Card 5 -->
            <div class="bg-white rounded-3xl p-8 border border-neutral-200/80 shadow-sm space-y-4 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-2xl bg-[#091315] text-[#D7FF53] flex items-center justify-center font-bold text-xl">
                    🏢
                </div>
                <h3 class="text-lg font-black text-[#091315]">Multi-Company & Multi-Firm</h3>
                <p class="text-xs text-neutral-600 leading-relaxed">
                    Operate multiple GSTIN units or manufacturing plants within a single workspace. Switch firm context instantly from the navigation header.
                </p>
            </div>

            <!-- Bento Card 6 -->
            <div class="bg-white rounded-3xl p-8 border border-neutral-200/80 shadow-sm space-y-4 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-2xl bg-[#091315] text-[#D7FF53] flex items-center justify-center font-bold text-xl">
                    💬
                </div>
                <h3 class="text-lg font-black text-[#091315]">WhatsApp Business Cloud API</h3>
                <p class="text-xs text-neutral-600 leading-relaxed">
                    Send invoice PDFs, dispatch tracking slips, and automated payment reminders directly to customers' WhatsApp with encrypted per-tenant credentials.
                </p>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-16 px-4 sm:px-8 max-w-5xl mx-auto">
        <div class="text-center space-y-3 mb-12">
            <span class="text-xs font-bold uppercase tracking-widest text-emerald-700 font-mono">Simple & Transparent</span>
            <h2 class="text-3xl sm:text-4xl font-black text-[#091315] tracking-tight">Flexible Plans for Every Stage</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Standard Plan -->
            <div class="bg-white rounded-3xl p-8 border border-neutral-200/80 shadow-md flex flex-col justify-between">
                <div class="space-y-4">
                    <span class="px-3 py-1 rounded-full bg-neutral-100 text-neutral-700 text-xs font-bold font-mono">Standard Plan</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-4xl font-black text-[#091315]">₹1,999</span>
                        <span class="text-xs text-neutral-400 font-bold">/ month</span>
                    </div>
                    <p class="text-xs text-neutral-500">Perfect for growing manufacturers and single-unit processors.</p>
                    <ul class="space-y-2.5 text-xs text-neutral-700 font-medium pt-4 border-t border-neutral-100">
                        <li class="flex items-center gap-2"><span>✓</span> <b>Dedicated Database</b> per tenant</li>
                        <li class="flex items-center gap-2"><span>✓</span> Up to 3 Companies & 3 Operating Firms</li>
                        <li class="flex items-center gap-2"><span>✓</span> 10 E-way Bills per month</li>
                        <li class="flex items-center gap-2"><span>✓</span> Complete CRM, Sales & Invoicing Pipeline</li>
                        <li class="flex items-center gap-2"><span>✓</span> Double-Entry Accounting & Ledger</li>
                    </ul>
                </div>
                <a href="{{ route('central.register') }}?plan=Standard"
                   class="mt-8 w-full py-3 rounded-2xl bg-neutral-100 hover:bg-neutral-200 text-neutral-900 font-bold text-xs text-center transition-colors">
                    Start with Standard
                </a>
            </div>

            <!-- Premium Plan -->
            <div class="bg-[#091315] text-white rounded-3xl p-8 border border-neutral-800 shadow-xl flex flex-col justify-between relative overflow-hidden">
                <div class="absolute top-4 right-4">
                    <span class="px-3 py-1 rounded-full bg-[#D7FF53] text-[#091315] text-[10px] font-black uppercase font-mono">Recommended</span>
                </div>
                <div class="space-y-4">
                    <span class="px-3 py-1 rounded-full bg-neutral-800 text-[#D7FF53] text-xs font-bold font-mono">Premium Plan</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-4xl font-black text-white">₹4,999</span>
                        <span class="text-xs text-neutral-400 font-bold">/ month</span>
                    </div>
                    <p class="text-xs text-neutral-400">For multi-plant exporters requiring unlimited compliance automation.</p>
                    <ul class="space-y-2.5 text-xs text-neutral-300 font-medium pt-4 border-t border-neutral-800">
                        <li class="flex items-center gap-2"><span class="text-[#D7FF53]">✓</span> <b>Dedicated Database</b> per tenant</li>
                        <li class="flex items-center gap-2"><span class="text-[#D7FF53]">✓</span> Up to 5 Companies & 5 Operating Firms</li>
                        <li class="flex items-center gap-2"><span class="text-[#D7FF53]">✓</span> <b>Unlimited E-way Bills</b> generation</li>
                        <li class="flex items-center gap-2"><span class="text-[#D7FF53]">✓</span> <b>Invoice Ads Completely Removed</b></li>
                        <li class="flex items-center gap-2"><span class="text-[#D7FF53]">✓</span> Unlimited Recycle Bin Restorations</li>
                        <li class="flex items-center gap-2"><span class="text-[#D7FF53]">✓</span> Priority WhatsApp Cloud API Support</li>
                    </ul>
                </div>
                <a href="{{ route('central.register') }}?plan=Premium"
                   class="mt-8 w-full py-3 rounded-2xl bg-[#D7FF53] hover:bg-[#C8F043] text-[#091315] font-black text-xs text-center transition-all shadow-md">
                    Upgrade to Premium →
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-neutral-200/80 bg-white py-12 px-4 sm:px-8 text-center text-xs text-neutral-500 space-y-3">
        <div class="flex items-center justify-center gap-2 font-bold text-neutral-800">
            <div class="w-6 h-6 rounded-lg bg-[#091315] text-[#D7FF53] flex items-center justify-center text-xs">
                ⚡
            </div>
            <span>ERPSaaS Platform</span>
        </div>
        <p>© 2026 ERPSaaS India. Designed for Indian Manufacturers, Wholesalers & Exporters.</p>
        <p class="text-[11px] text-neutral-400 font-mono">Strict Database-Per-Tenant Isolation Guaranteed</p>
    </footer>

</body>
</html>
