@extends('layouts.app')

@section('title', 'WhatsApp Business Cloud API Connect')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Top Navigation Tabs (Organization Hub) -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs font-bold border-b border-neutral-200">
        <a href="{{ route('organization.settings') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🏢 Organization Profile
        </a>
        <a href="{{ route('organization.team') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            👥 Team & Staff Access
        </a>
        <a href="{{ route('organization.departments') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🏛️ 4 Fixed Departments
        </a>
        <a href="{{ route('organization.companies.index') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🏭 Companies & Operating Units
        </a>
        <a href="{{ route('whatsapp.settings') }}" class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white shadow-sm whitespace-nowrap">
            💬 WhatsApp Connect
        </a>
        <a href="{{ route('organization.audit-logs') }}" class="px-4 py-2.5 rounded-xl text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 transition-colors whitespace-nowrap">
            🛡️ Security Audit Logs
        </a>
    </div>

    <!-- Status Header Card -->
    <div class="bg-white border border-neutral-200 rounded-2xl p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl shrink-0 border border-emerald-100">
                💬
            </div>
            <div>
                <h2 class="text-base font-black text-neutral-900">WhatsApp Business Cloud API</h2>
                <p class="text-xs text-neutral-500">Official Meta Graph API v19.0 integration with per-tenant encrypted credentials.</p>
            </div>
        </div>
        <div>
            @if($setting->is_active && $setting->provider === 'META_CLOUD' && $setting->phone_number_id)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 font-bold text-xs border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Meta API Connected
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-neutral-100 text-neutral-600 font-bold text-xs">
                    <span class="w-2 h-2 rounded-full bg-neutral-400"></span>
                    Sandbox / Mock Active
                </span>
            @endif
        </div>
    </div>

    <!-- WhatsApp Settings Form -->
    <div class="bg-white border border-neutral-200 rounded-2xl p-6 shadow-xs">
        <form method="POST" action="{{ route('whatsapp.settings.update') }}" class="space-y-6">
            @csrf
            <div>
                <h3 class="font-black text-sm text-neutral-900 mb-1">API Provider & Credentials</h3>
                <p class="text-xs text-neutral-500 mb-4">Credentials are securely encrypted on disk using AES-256-CBC encryption.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Driver Mode *</label>
                        <select name="provider" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900">
                            <option value="MOCK" {{ $setting->provider === 'MOCK' ? 'selected' : '' }}>Sandbox / Mock Driver (Simulation & Logs)</option>
                            <option value="META_CLOUD" {{ $setting->provider === 'META_CLOUD' ? 'selected' : '' }}>Official Meta Cloud API (Production)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Sender WhatsApp Phone Number</label>
                        <input type="text" name="sender_phone_number" value="{{ $setting->sender_phone_number }}" placeholder="e.g. +91 98250 12345" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Meta Phone Number ID</label>
                        <input type="text" name="phone_number_id" value="{{ $setting->phone_number_id }}" placeholder="e.g. 104829104819203" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">WhatsApp Business Account ID (WABA ID)</label>
                        <input type="text" name="waba_id" value="{{ $setting->waba_id }}" placeholder="e.g. 981293812039120" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-neutral-600 mb-1">Permanent Meta System User Access Token</label>
                        <input type="password" name="access_token" placeholder="{{ $setting->access_token ? '•••••••••••••••••••••••••••••••• (Encrypted in database)' : 'Paste EAAB... token from Meta Business Manager' }}" class="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 focus:outline-none focus:border-neutral-900 font-mono">
                        <p class="text-[11px] text-neutral-400 mt-1">Leave blank to retain currently saved token.</p>
                    </div>
                </div>
            </div>

            <!-- Automation Triggers -->
            <div class="pt-4 border-t border-neutral-100">
                <h3 class="font-black text-sm text-neutral-900 mb-1">Automated Message Triggers</h3>
                <p class="text-xs text-neutral-500 mb-4">Select which customer transactions automatically trigger WhatsApp alerts.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="p-3.5 rounded-xl border border-neutral-200 flex items-start gap-3 cursor-pointer hover:bg-neutral-50 transition-colors">
                        <input type="checkbox" name="auto_send_invoices" value="1" {{ $setting->auto_send_invoices ? 'checked' : '' }} class="mt-0.5 rounded text-neutral-900">
                        <div>
                            <span class="text-xs font-bold text-neutral-900 block">📄 Tax Invoice Dispatch</span>
                            <span class="text-[11px] text-neutral-500">Send PDF download link and invoice breakdown as soon as an invoice is issued.</span>
                        </div>
                    </label>

                    <label class="p-3.5 rounded-xl border border-neutral-200 flex items-start gap-3 cursor-pointer hover:bg-neutral-50 transition-colors">
                        <input type="checkbox" name="auto_send_shipments" value="1" {{ $setting->auto_send_shipments ? 'checked' : '' }} class="mt-0.5 rounded text-neutral-900">
                        <div>
                            <span class="text-xs font-bold text-neutral-900 block">🚚 Consignment Dispatch & LR</span>
                            <span class="text-[11px] text-neutral-500">Notify buyer with Transporter, Vehicle number, and Live LR Tracking link upon dispatch.</span>
                        </div>
                    </label>

                    <label class="p-3.5 rounded-xl border border-neutral-200 flex items-start gap-3 cursor-pointer hover:bg-neutral-50 transition-colors">
                        <input type="checkbox" name="auto_send_receipts" value="1" {{ $setting->auto_send_receipts ? 'checked' : '' }} class="mt-0.5 rounded text-neutral-900">
                        <div>
                            <span class="text-xs font-bold text-neutral-900 block">💳 Payment Receipt Acknowledgement</span>
                            <span class="text-[11px] text-neutral-500">Send an instant thank-you confirmation with reference/UTR upon payment recording.</span>
                        </div>
                    </label>

                    <label class="p-3.5 rounded-xl border border-neutral-200 flex items-start gap-3 cursor-pointer hover:bg-neutral-50 transition-colors">
                        <input type="checkbox" name="auto_send_reminders" value="1" {{ $setting->auto_send_reminders ? 'checked' : '' }} class="mt-0.5 rounded text-neutral-900">
                        <div>
                            <span class="text-xs font-bold text-neutral-900 block">🔔 Automated Payment Reminders</span>
                            <span class="text-[11px] text-neutral-500">Dispatch reminders 3 days before due date, on due date, and every 5 days overdue.</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-neutral-100">
                <button type="submit" class="px-5 py-2.5 text-xs font-bold bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl shadow-xs transition-colors">
                    Save Configuration
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
