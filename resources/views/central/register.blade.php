<!DOCTYPE html>
<html lang="en" class="h-full bg-[#F5F6F8]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <title>Create Enterprise Workspace — ERPSaaS</title>
    <!-- Google Fonts: Outfit & Plus Jakarta Sans & IBM Plex Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
<body class="min-h-full bg-[#F5F6F8] font-sans antialiased text-neutral-900 py-10 px-4 flex items-center justify-center selection:bg-[#D7FF53] selection:text-[#091315]">

    <div class="max-w-2xl w-full bg-white rounded-3xl p-6 sm:p-10 border border-neutral-200/80 shadow-xl space-y-8"
         x-data="registrationForm()">

        <!-- Header -->
        <div class="text-center space-y-2">
            <div class="w-12 h-12 rounded-2xl bg-[#091315] text-[#D7FF53] flex items-center justify-center mx-auto shadow-md border border-neutral-800">
                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                    <path d="M13 2L4 14h6v8l9-12h-6z" />
                </svg>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-[#091315] tracking-tight">
                Launch Your Enterprise ERP
            </h1>
            <p class="text-xs sm:text-sm text-neutral-500 font-medium">
                Provision a dedicated database and private workspace in under 30 seconds.
            </p>
        </div>

        @if ($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
                <p class="font-bold">Please correct the following errors:</p>
                <ul class="list-disc list-inside space-y-0.5 text-rose-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('central.register.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- 1. Company & Subdomain -->
            <div class="space-y-4 pt-2">
                <h2 class="text-xs font-bold uppercase tracking-wider text-neutral-400 font-mono">1. Workspace Identifier</h2>

                <div>
                    <label class="block text-xs font-bold text-neutral-700 mb-1">Company / Business Legal Name *</label>
                    <input type="text" name="business_name" required
                           x-model="businessName"
                           @input="suggestSubdomain()"
                           value="{{ old('business_name') }}"
                           placeholder="e.g. Acme Dehydrates Private Limited"
                           class="w-full px-4 py-3 rounded-2xl bg-[#F5F6F8] border border-neutral-200 text-sm focus:outline-hidden focus:ring-2 focus:ring-[#091315]">
                </div>

                <div>
                    <label class="block text-xs font-bold text-neutral-700 mb-1">Desired Workspace Subdomain *</label>
                    <div class="relative flex items-center">
                        <input type="text" name="subdomain" required
                               x-model="subdomain"
                               @input.debounce.400ms="checkAvailability()"
                               value="{{ old('subdomain') }}"
                               placeholder="acme"
                               class="w-full px-4 py-3 rounded-2xl bg-[#F5F6F8] border border-neutral-200 text-sm font-mono lowercase pr-36 focus:outline-hidden focus:ring-2 focus:ring-[#091315]">
                        <span class="absolute right-4 text-xs font-bold font-mono text-neutral-400 pointer-events-none">
                            .{{ $tenantRootDomain }}
                        </span>
                    </div>

                    <!-- Live Status Feedback -->
                    <div class="mt-1.5 flex items-center justify-between text-xs font-mono">
                        <span x-show="isChecking" class="text-neutral-400 animate-pulse">Checking availability...</span>
                        <span x-show="!isChecking && isAvailable === true" class="text-emerald-600 font-bold flex items-center gap-1">
                            <span>✓</span> Available: <code x-text="previewUrl" class="font-bold"></code>
                        </span>
                        <span x-show="!isChecking && isAvailable === false" class="text-rose-600 font-bold" x-text="errorMessage"></span>
                    </div>
                </div>
            </div>

            <!-- 2. Owner Account Details -->
            <div class="space-y-4 pt-4 border-t border-neutral-100">
                <h2 class="text-xs font-bold uppercase tracking-wider text-neutral-400 font-mono">2. Administrator / Owner</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Owner Full Name *</label>
                        <input type="text" name="owner_name" required value="{{ old('owner_name') }}"
                               placeholder="Vishal Valiya"
                               class="w-full px-4 py-3 rounded-2xl bg-[#F5F6F8] border border-neutral-200 text-sm focus:outline-hidden focus:ring-2 focus:ring-[#091315]">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Work Email Address *</label>
                        <input type="email" name="email" required value="{{ old('email') }}"
                               placeholder="vishal@acme.com"
                               class="w-full px-4 py-3 rounded-2xl bg-[#F5F6F8] border border-neutral-200 text-sm focus:outline-hidden focus:ring-2 focus:ring-[#091315]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Mobile Number (India)</label>
                        <div class="relative flex items-center">
                            <span class="absolute left-4 text-xs font-bold text-neutral-400">+91</span>
                            <input type="tel" name="phone" value="{{ old('phone') }}"
                                   placeholder="9876543210"
                                   class="w-full pl-12 pr-4 py-3 rounded-2xl bg-[#F5F6F8] border border-neutral-200 text-sm font-mono focus:outline-hidden focus:ring-2 focus:ring-[#091315]">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">GSTIN (Optional)</label>
                        <input type="text" name="gstin" value="{{ old('gstin') }}"
                               placeholder="24AAACR1234F1Z1"
                               class="w-full px-4 py-3 rounded-2xl bg-[#F5F6F8] border border-neutral-200 text-sm uppercase font-mono focus:outline-hidden focus:ring-2 focus:ring-[#091315]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Password * (min 8 chars)</label>
                        <input type="password" name="password" required
                               placeholder="••••••••••••"
                               class="w-full px-4 py-3 rounded-2xl bg-[#F5F6F8] border border-neutral-200 text-sm focus:outline-hidden focus:ring-2 focus:ring-[#091315]">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-neutral-700 mb-1">Confirm Password *</label>
                        <input type="password" name="password_confirmation" required
                               placeholder="••••••••••••"
                               class="w-full px-4 py-3 rounded-2xl bg-[#F5F6F8] border border-neutral-200 text-sm focus:outline-hidden focus:ring-2 focus:ring-[#091315]">
                    </div>
                </div>
            </div>

            <!-- 3. Plan Selection -->
            <div class="space-y-3 pt-4 border-t border-neutral-100">
                <h2 class="text-xs font-bold uppercase tracking-wider text-neutral-400 font-mono">3. Select Initial Plan</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="relative flex items-start gap-3 p-4 rounded-2xl border cursor-pointer transition-all"
                           :class="plan === 'Standard' ? 'border-[#091315] bg-[#F3FED4]/30 ring-2 ring-[#091315]' : 'border-neutral-200 bg-white'">
                        <input type="radio" name="plan" value="Standard" x-model="plan" class="mt-1 accent-[#091315]">
                        <div class="space-y-0.5">
                            <span class="block text-sm font-extrabold text-[#091315]">Standard Plan</span>
                            <span class="block text-xs font-bold text-neutral-500">₹1,999 / mo • 3 Firms • 10 E-way bills</span>
                        </div>
                    </label>

                    <label class="relative flex items-start gap-3 p-4 rounded-2xl border cursor-pointer transition-all"
                           :class="plan === 'Premium' ? 'border-[#091315] bg-[#F3FED4]/30 ring-2 ring-[#091315]' : 'border-neutral-200 bg-white'">
                        <input type="radio" name="plan" value="Premium" x-model="plan" class="mt-1 accent-[#091315]">
                        <div class="space-y-0.5">
                            <span class="block text-sm font-extrabold text-[#091315]">Premium Plan</span>
                            <span class="block text-xs font-bold text-neutral-500">₹4,999 / mo • Unlimited E-way • Ads removed</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- 4. Terms Agreement -->
            <div class="pt-2">
                <label class="flex items-start gap-3 cursor-pointer text-xs text-neutral-600">
                    <input type="checkbox" name="terms" required class="mt-0.5 rounded accent-[#091315]">
                    <span>I agree to the <a href="#" class="font-bold underline text-neutral-800">Terms of Service</a>, <a href="#" class="font-bold underline text-neutral-800">Privacy Policy</a>, and consent to dedicated database provisioning.</span>
                </label>
            </div>

            <!-- Submit Button -->
            <button type="submit"
                    :disabled="isAvailable === false"
                    class="w-full py-4 rounded-2xl bg-[#091315] hover:bg-neutral-800 text-[#D7FF53] font-black text-sm tracking-wide shadow-xl transition-all disabled:opacity-50 cursor-pointer flex items-center justify-center gap-2 border border-neutral-800">
                <span>🚀</span> Provision Dedicated ERP Workspace
            </button>
        </form>

        <p class="text-center text-xs text-neutral-400">
            Already have a workspace? <a href="{{ route('central.find-workspace') }}" class="font-bold text-neutral-800 underline">Find your workspace here</a>
        </p>
    </div>

    <script>
        function registrationForm() {
            return {
                businessName: '{{ old("business_name") }}',
                subdomain: '{{ old("subdomain") }}',
                plan: '{{ request("plan", "Standard") }}',
                isChecking: false,
                isAvailable: null,
                previewUrl: '',
                errorMessage: '',
                suggestSubdomain() {
                    if (!this.subdomain || this.subdomain === this.cleanSlug(this.businessName.slice(0, -1))) {
                        this.subdomain = this.cleanSlug(this.businessName);
                        if (this.subdomain.length >= 3) {
                            this.checkAvailability();
                        }
                    }
                },
                cleanSlug(text) {
                    return text.toLowerCase()
                        .replace(/[^a-z0-9\-]/g, '-')
                        .replace(/-+/g, '-')
                        .replace(/^-|-$/g, '')
                        .slice(0, 30);
                },
                async checkAvailability() {
                    const clean = this.cleanSlug(this.subdomain);
                    this.subdomain = clean;
                    if (clean.length < 3) {
                        this.isAvailable = false;
                        this.errorMessage = 'Subdomain must be at least 3 characters.';
                        return;
                    }
                    this.isChecking = true;
                    try {
                        const res = await fetch('{{ route("central.check-subdomain") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ subdomain: clean })
                        });
                        const data = await res.json();
                        this.isAvailable = data.available;
                        this.previewUrl = data.preview;
                        this.errorMessage = data.message;
                    } catch (e) {
                        this.isAvailable = null;
                    } finally {
                        this.isChecking = false;
                    }
                }
            };
        }
    </script>
</body>
</html>
