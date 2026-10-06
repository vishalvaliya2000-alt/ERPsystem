<?php

namespace App\Http\Controllers;

use App\Models\CommercialShipment;
use App\Models\Invoice;
use App\Models\TenantWhatsAppSetting;
use App\Services\CompanyFirmContext;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService
    ) {}

    /**
     * Sends an Invoice PDF and details via WhatsApp.
     */
    public function sendInvoice(Request $request, int $invoiceId)
    {
        $invoice = Invoice::with(['customer', 'firm'])->findOrFail($invoiceId);
        $phone = $request->input('phone');

        $res = $this->whatsAppService->sendInvoice($invoice, $phone);

        if ($res['success']) {
            return back()->with('success', "✓ Tax Invoice {$invoice->invoice_number} sent via WhatsApp to {$invoice->customer?->company_name}!");
        }

        return back()->with('error', "Failed to send WhatsApp invoice: {$res['error']}");
    }

    /**
     * Sends Shipment LR Tracking update via WhatsApp.
     */
    public function sendShipment(Request $request, int $shipmentId)
    {
        $shipment = CommercialShipment::with(['firm', 'items'])->findOrFail($shipmentId);
        $phone = $request->input('phone');

        $res = $this->whatsAppService->sendShipmentNotification($shipment, $phone);

        if ($res['success']) {
            return back()->with('success', "✓ Consignment dispatch update for LR {$shipment->lr_number} sent via WhatsApp!");
        }

        return back()->with('error', "Failed to send WhatsApp shipment update: {$res['error']}");
    }

    /**
     * Shows WhatsApp Business Cloud API settings.
     */
    public function settings(Request $request)
    {
        $firmId = CompanyFirmContext::getCurrentFirmId();
        $setting = TenantWhatsAppSetting::where('firm_id', $firmId)->first()
            ?? TenantWhatsAppSetting::whereNull('firm_id')->first()
            ?? new TenantWhatsAppSetting;

        return view('organization.whatsapp-settings', compact('setting', 'firmId'));
    }

    /**
     * Updates WhatsApp Business Cloud API credentials.
     */
    public function updateSettings(Request $request)
    {
        $firmId = CompanyFirmContext::getCurrentFirmId();

        $validated = $request->validate([
            'provider' => 'required|string|in:META_CLOUD,MOCK',
            'phone_number_id' => 'nullable|string|max:100',
            'access_token' => 'nullable|string',
            'waba_id' => 'nullable|string|max:100',
            'sender_phone_number' => 'nullable|string|max:30',
            'is_active' => 'nullable|boolean',
            'auto_send_invoices' => 'nullable|boolean',
            'auto_send_shipments' => 'nullable|boolean',
            'auto_send_receipts' => 'nullable|boolean',
            'auto_send_reminders' => 'nullable|boolean',
        ]);

        $setting = TenantWhatsAppSetting::firstOrNew(['firm_id' => $firmId]);

        $setting->provider = $validated['provider'];
        $setting->phone_number_id = $validated['phone_number_id'] ?? null;
        if (! empty($validated['access_token'])) {
            $setting->access_token = $validated['access_token']; // Encrypted via accessor
        }
        $setting->waba_id = $validated['waba_id'] ?? null;
        $setting->sender_phone_number = $validated['sender_phone_number'] ?? null;
        $setting->is_active = (bool) ($validated['is_active'] ?? true);
        $setting->auto_send_invoices = (bool) ($validated['auto_send_invoices'] ?? false);
        $setting->auto_send_shipments = (bool) ($validated['auto_send_shipments'] ?? false);
        $setting->auto_send_receipts = (bool) ($validated['auto_send_receipts'] ?? false);
        $setting->auto_send_reminders = (bool) ($validated['auto_send_reminders'] ?? true);
        $setting->save();

        return redirect()->route('whatsapp.settings')->with('success', '✓ WhatsApp Cloud API settings saved successfully.');
    }
}
