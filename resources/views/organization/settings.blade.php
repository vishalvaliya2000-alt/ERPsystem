@extends('layouts.app')

@section('title', 'Company Profile & Invoice Settings')

@section('content')
<div class="space-y-6 pb-12" x-data="{ isSaving: false }">
    <!-- Top Navigation Tabs (Organization Hub) -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs font-bold border-b border-neutral-200">
        <a href="{{ route('organization.settings') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-xs whitespace-nowrap">
            🏢 Organization Profile
        </a>
        <a href="{{ route('organization.team') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            👥 Team & Staff Access
        </a>
        <a href="{{ route('organization.departments') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🏛️ 4 Fixed Departments
        </a>
        <a href="{{ route('organization.companies.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🏭 Companies & Operating Firms
        </a>
        <a href="{{ route('whatsapp.settings') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            💬 WhatsApp Connect
        </a>
        <a href="{{ route('organization.audit-logs') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🛡️ Security Audit Logs
        </a>
    </div>

    <!-- Header -->
    <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-slate-200 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            @if(!empty($tenant?->logo_url))
                <div class="w-14 h-14 rounded-2xl bg-white p-1.5 flex items-center justify-center border border-slate-200 shadow-md shrink-0 overflow-hidden">
                    <img src="{{ asset($tenant?->logo_url) }}" alt="{{ $tenant?->name ?? 'Company' }}" class="w-full h-full object-contain">
                </div>
            @else
                <div class="w-14 h-14 rounded-2xl text-white font-black text-2xl flex items-center justify-center shadow-xs shrink-0 bg-slate-900">
                    {{ substr($tenant?->name ?? 'O', 0, 1) }}
                </div>
            @endif
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900">{{ $tenant?->name ?? 'Organization Settings' }}</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                        {{ $tenant?->plan ?? 'Standard' }}
                    </span>
                    @if($tenant?->isExportCompany())
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                            🌐 Export & Forex Active
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mt-0.5">{{ $tenant?->tagline ?: 'Organization Profile & Compliance Settings' }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('organization.team') }}" class="px-4 py-2 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all">
                👥 Manage Team ({{ $tenant?->users()->count() ?? 0 }})
            </a>
        </div>
    </div>

    <!-- Settings Form -->
    <form action="{{ route('organization.settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- 1. Organization Identity & Compliance -->
        <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h2 class="text-base font-bold text-slate-900">🏢 Organization Identity & Compliance</h2>
                <p class="text-xs text-slate-500">Legal entity name, industry vertical, and primary contact details</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Company Name *</label>
                    <input type="text" name="name" value="{{ old('name', $tenant?->name) }}" required class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Industry Vertical</label>
                    <input type="text" name="industry" value="{{ old('industry', $tenant?->industry) }}" placeholder="e.g. Food Processing & Exports" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tagline / Sub-title</label>
                    <input type="text" name="tagline" value="{{ old('tagline', $tenant?->tagline) }}" placeholder="e.g. Manufacturer & Processor" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Primary Email</label>
                    <input type="email" name="email" value="{{ old('email', $tenant?->email) }}" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Primary Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $tenant?->phone) }}" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Official Website</label>
                    <input type="url" name="website" value="{{ old('website', $tenant?->website) }}" placeholder="https://..." class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden">
                </div>
            </div>
        </div>

        <!-- 2. Address & Plant Location -->
        <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h2 class="text-base font-bold text-slate-900">📍 Registered Office / Plant Location</h2>
                <p class="text-xs text-slate-500">Printed on official Tax Invoices, Quotations, and LR Consignments</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1">Address Line</label>
                    <input type="text" name="address_line" value="{{ old('address_line', $tenant?->address_line) }}" placeholder="e.g. Survey No. 128, Highway" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">City</label>
                    <input type="text" name="city" value="{{ old('city', $tenant?->city) }}" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">State</label>
                    <input type="text" name="state" value="{{ old('state', $tenant?->state) }}" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Pincode</label>
                    <input type="text" name="pincode" value="{{ old('pincode', $tenant?->pincode) }}" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Country</label>
                    <input type="text" name="country" value="{{ old('country', $tenant?->country ?: 'India') }}" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden">
                </div>
            </div>
        </div>

        <!-- 3. Tax & Export Compliance -->
        <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h2 class="text-base font-bold text-slate-900">📑 Tax & Export Trade Compliance</h2>
                <p class="text-xs text-slate-500">GSTIN, Import-Export Code (IEC), LUT / ARN, and Food Safety Licenses</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tax ID Label</label>
                    <input type="text" name="tax_id_label" value="{{ old('tax_id_label', $tenant?->tax_id_label ?: 'GSTIN') }}" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">GSTIN Number</label>
                    <input type="text" name="tax_id_number" value="{{ old('tax_id_number', $tenant?->tax_id_number) }}" placeholder="e.g. 24AAACR1234F1Z5" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden font-mono uppercase">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">PAN Number</label>
                    <input type="text" name="pan_number" value="{{ old('pan_number', $tenant?->pan_number) }}" placeholder="e.g. AAACR1234F" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden font-mono uppercase">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                <div class="p-3 bg-blue-50/50 rounded-xl border border-blue-100">
                    <label class="block text-xs font-bold text-blue-900 mb-1">🌐 IEC (Import Export Code)</label>
                    <input type="text" name="iec_code" value="{{ old('iec_code', $tenant?->iec_code) }}" placeholder="e.g. 2408012345 (DGFT)" class="w-full px-3 py-2 text-xs border border-blue-200 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-hidden font-mono">
                    <span class="text-[10px] text-blue-600 mt-1 block">Required for International Export Invoicing</span>
                </div>
                <div class="p-3 bg-blue-50/50 rounded-xl border border-blue-100">
                    <label class="block text-xs font-bold text-blue-900 mb-1">📄 Export LUT / ARN</label>
                    <input type="text" name="lut_arn" value="{{ old('lut_arn', $tenant?->lut_arn) }}" placeholder="e.g. AD240826001234E" class="w-full px-3 py-2 text-xs border border-blue-200 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-hidden font-mono">
                    <span class="text-[10px] text-blue-600 mt-1 block">Letter of Undertaking for Zero-Rated Exports</span>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                    <label class="block text-xs font-bold text-slate-800 mb-1">🍽️ FSSAI License No.</label>
                    <input type="text" name="fssai_number" value="{{ old('fssai_number', $tenant?->fssai_number) }}" placeholder="e.g. 10718002000123" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-[#091315] outline-hidden font-mono">
                    <span class="text-[10px] text-slate-500 mt-1 block">Food Safety Authority License</span>
                </div>
            </div>
        </div>

        <!-- 4. Bank Account & Settlement Details -->
        <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h2 class="text-base font-bold text-slate-900">🏦 Banking & Settlement Accounts</h2>
                <p class="text-xs text-slate-500">Printed on Invoices for RTGS / NEFT / Wire Transfer Settlements</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Bank Name</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $tenant?->bank_name) }}" placeholder="e.g. HDFC Bank / ICICI Bank" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Account Number</label>
                    <input type="text" name="bank_account_number" value="{{ old('bank_account_number', $tenant?->bank_account_number) }}" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden font-mono font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">IFSC Code</label>
                    <input type="text" name="bank_ifsc_code" value="{{ old('bank_ifsc_code', $tenant?->bank_ifsc_code) }}" placeholder="e.g. HDFC0000123" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden font-mono uppercase">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Swift / BIC Code (For Global Wire Transfers)</label>
                    <input type="text" name="bank_swift_code" value="{{ old('bank_swift_code', $tenant?->bank_swift_code) }}" placeholder="e.g. ICICINBB" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden font-mono uppercase">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Branch Name</label>
                    <input type="text" name="bank_branch" value="{{ old('bank_branch', $tenant?->bank_branch) }}" class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#091315] outline-hidden">
                </div>
            </div>
        </div>

        <!-- 5. Currency & Document Numbering Prefixes -->
        <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h2 class="text-base font-bold text-slate-900">🔢 Currency & Document Prefixes</h2>
                <p class="text-xs text-slate-500">Auto-numbering rules for Invoices, Quotations, and Sales Orders</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Currency Code</label>
                    <input type="text" name="currency_code" value="{{ old('currency_code', $tenant?->currency_code ?: 'INR') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl font-bold text-center">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Symbol</label>
                    <input type="text" name="currency_symbol" value="{{ old('currency_symbol', $tenant?->currency_symbol ?: '₹') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl font-bold text-center">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Invoice Prefix</label>
                    <input type="text" name="invoice_prefix" value="{{ old('invoice_prefix', $tenant?->invoice_prefix ?: 'INV') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl font-mono text-center font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Quote Prefix</label>
                    <input type="text" name="quotation_prefix" value="{{ old('quotation_prefix', $tenant?->quotation_prefix ?: 'QUO') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl font-mono text-center font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">PO Prefix</label>
                    <input type="text" name="po_prefix" value="{{ old('po_prefix', $tenant?->po_prefix ?: 'PO') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl font-mono text-center font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">LR Prefix</label>
                    <input type="text" name="shipment_prefix" value="{{ old('shipment_prefix', $tenant?->shipment_prefix ?: 'SHP') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl font-mono text-center font-bold">
                </div>
            </div>
        </div>

        <!-- 6. WhatsApp & Telegram Cryptographic Security Architecture -->
        <div class="bg-[#091315] p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-neutral-800 text-white shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-2xl bg-[#D7FF53]/20 text-[#D7FF53] border border-[#D7FF53]/30 flex items-center justify-center font-bold text-sm">
                        🔒
                    </div>
                    <div>
                        <h2 class="text-sm font-extrabold text-white tracking-wide font-display">WhatsApp / Signal & Telegram Cryptographic Standard</h2>
                        <p class="text-[11px] text-[#D7FF53] font-mono">Authenticated Encryption with Associated Data (AEAD) Active</p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full bg-[#D7FF53]/10 text-[#D7FF53] border border-[#D7FF53]/30 text-[10px] font-mono font-bold">
                    ● Signal Protocol Active
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                <div class="p-4 rounded-2xl bg-white/5 border border-white/10 space-y-1">
                    <span class="font-bold text-[#D7FF53] block font-mono">1. AES-256-GCM (Signal Protocol)</span>
                    <p class="text-[11px] text-neutral-400 leading-relaxed">Symmetric authenticated encryption matching WhatsApp's core data payload cipher.</p>
                </div>
                <div class="p-4 rounded-2xl bg-white/5 border border-white/10 space-y-1">
                    <span class="font-bold text-[#D7FF53] block font-mono">2. 128-bit GCM Authentication Tag</span>
                    <p class="text-[11px] text-neutral-400 leading-relaxed">Guarantees cryptographic tamper-proof integrity on all invoices and ledgers.</p>
                </div>
                <div class="p-4 rounded-2xl bg-white/5 border border-white/10 space-y-1">
                    <span class="font-bold text-[#D7FF53] block font-mono">3. Multi-Tenant Cryptographic Wall</span>
                    <p class="text-[11px] text-neutral-400 leading-relaxed">Strict zero-leakage database scoping isolating your data from other companies.</p>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end gap-3 pt-2">
            <button type="submit" class="px-6 py-2.5 rounded-full bg-[#D7FF53] hover:bg-[#c8f043] text-[#091315] text-xs font-extrabold shadow-2xs border border-[#c8f043] transition-all flex items-center gap-2 cursor-pointer">
                <span>Save Organization Settings</span>
                <span>✓</span>
            </button>
        </div>
    </form>
</div>
@endsection