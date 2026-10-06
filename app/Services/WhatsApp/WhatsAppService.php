<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\Models\CommercialShipment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\PaymentReminderLog;
use App\Models\TenantWhatsAppSetting;
use App\Models\WhatsAppMessage;

class WhatsAppService
{
    public function __construct(
        protected MockWhatsAppDriver $mockDriver,
        protected WhatsAppCloudApiDriver $cloudDriver
    ) {}

    public function getDriver(?int $firmId = null): WhatsAppProvider
    {
        $setting = TenantWhatsAppSetting::where('is_active', true)
            ->when($firmId, fn ($q) => $q->where('firm_id', $firmId))
            ->first();

        $provider = $setting?->provider ?: config('services.whatsapp.provider', 'mock');

        return match (strtoupper($provider)) {
            'META_CLOUD' => $this->cloudDriver,
            default => $this->mockDriver,
        };
    }

    /**
     * Sends Tax Invoice link / PDF via WhatsApp.
     */
    public function sendInvoice(Invoice $invoice, ?string $recipientPhone = null): array
    {
        $phone = $recipientPhone ?: ($invoice->customer?->primaryContact?->phone ?: $invoice->customer?->phone);

        if (empty($phone)) {
            return [
                'success' => false,
                'message_id' => null,
                'status' => 'FAILED',
                'error' => 'No valid recipient phone number found for this customer.',
            ];
        }

        $firmName = $invoice->firm?->firm_name ?: 'ERPSaaS Enterprise';
        $totalFormatted = '₹'.number_format((float) $invoice->total_amount, 2);
        $balanceFormatted = '₹'.number_format((float) $invoice->balance_due, 2);
        $dueDateFormatted = $invoice->due_date ? $invoice->due_date->format('d M Y') : 'Immediate';

        $text = "📄 *Tax Invoice from {$firmName}*\n\n"
            ."Dear {$invoice->customer?->company_name},\n"
            ."Your Tax Invoice *#{$invoice->invoice_number}* has been issued.\n\n"
            ."• *Invoice Date:* {$invoice->invoice_date->format('d M Y')}\n"
            ."• *Total Amount:* {$totalFormatted}\n"
            ."• *Balance Due:* {$balanceFormatted}\n"
            ."• *Due Date:* {$dueDateFormatted}\n\n"
            ."You can download your official tax invoice using the link below:\n"
            .route('invoices.pdf', $invoice->id)."\n\n"
            .'Thank you for your business!';

        $driver = $this->getDriver($invoice->firm_id);
        $res = $driver->sendMessage($phone, $text, ['firm_id' => $invoice->firm_id]);

        WhatsAppMessage::create([
            'firm_id' => $invoice->firm_id,
            'recipient_phone' => $phone,
            'recipient_name' => $invoice->customer?->company_name,
            'message_type' => 'INVOICE',
            'reference_type' => 'INVOICE',
            'reference_id' => $invoice->id,
            'message_body' => $text,
            'meta_message_id' => $res['message_id'],
            'status' => $res['status'],
            'error_message' => $res['error'],
        ]);

        return $res;
    }

    /**
     * Sends Consignment Dispatch & LR Tracking notification.
     */
    public function sendShipmentNotification(CommercialShipment $shipment, ?string $recipientPhone = null): array
    {
        $orders = $shipment->getDistinctOrders();
        $customer = $orders->first()?->customer;
        $phone = $recipientPhone ?: ($customer?->primaryContact?->phone ?: $customer?->phone);

        if (empty($phone)) {
            return [
                'success' => false,
                'message_id' => null,
                'status' => 'FAILED',
                'error' => 'No valid recipient phone number found.',
            ];
        }

        $firmName = $shipment->firm?->firm_name ?: 'ERPSaaS Enterprise';
        $expDate = $shipment->expected_delivery_date ? $shipment->expected_delivery_date->format('d M Y') : 'In transit';

        $text = "🚚 *Consignment Dispatched - {$firmName}*\n\n"
            ."Dear {$customer?->company_name},\n"
            ."Your consignment has been dispatched for delivery.\n\n"
            ."• *Lorry Receipt (LR) No:* {$shipment->lr_number}\n"
            ."• *Transporter:* {$shipment->transporter}\n"
            ."• *Vehicle Number:* {$shipment->vehicle_number}\n"
            ."• *Destination:* {$shipment->destination}\n"
            ."• *Expected Delivery:* {$expDate}\n\n"
            ."Track live shipment movement:\n"
            .route('shipments.live-tracking', $shipment->id)."\n\n"
            .'Have a safe delivery!';

        $driver = $this->getDriver($shipment->firm_id);
        $res = $driver->sendMessage($phone, $text, ['firm_id' => $shipment->firm_id]);

        WhatsAppMessage::create([
            'firm_id' => $shipment->firm_id,
            'recipient_phone' => $phone,
            'recipient_name' => $customer?->company_name,
            'message_type' => 'SHIPMENT',
            'reference_type' => 'SHIPMENT',
            'reference_id' => $shipment->id,
            'message_body' => $text,
            'meta_message_id' => $res['message_id'],
            'status' => $res['status'],
            'error_message' => $res['error'],
        ]);

        return $res;
    }

    /**
     * Sends Payment Receipt Confirmation.
     */
    public function sendPaymentReceiptNotification(PaymentReceipt $receipt, ?string $recipientPhone = null): array
    {
        $customer = $receipt->customer;
        $phone = $recipientPhone ?: ($customer?->primaryContact?->phone ?: $customer?->phone);

        if (empty($phone)) {
            return [
                'success' => false,
                'message_id' => null,
                'status' => 'FAILED',
                'error' => 'No valid phone number for customer.',
            ];
        }

        $amountFormatted = '₹'.number_format((float) $receipt->amount_received, 2);
        $invoiceNum = $receipt->invoice?->invoice_number ?: 'General Account';

        $text = "💳 *Payment Received with Thanks*\n\n"
            ."Dear {$customer?->company_name},\n"
            ."We have successfully received your payment.\n\n"
            ."• *Receipt No:* {$receipt->receipt_number}\n"
            ."• *Amount Received:* {$amountFormatted}\n"
            ."• *Payment Mode:* {$receipt->payment_mode}\n"
            .'• *Reference / UTR:* '.($receipt->reference_number ?: 'N/A')."\n"
            ."• *Settled Against:* {$invoiceNum}\n"
            ."• *Date:* {$receipt->receipt_date->format('d M Y')}\n\n"
            .'Thank you for your prompt settlement!';

        $driver = $this->getDriver($receipt->invoice?->firm_id);
        $res = $driver->sendMessage($phone, $text, ['firm_id' => $receipt->invoice?->firm_id]);

        WhatsAppMessage::create([
            'firm_id' => $receipt->invoice?->firm_id,
            'recipient_phone' => $phone,
            'recipient_name' => $customer?->company_name,
            'message_type' => 'RECEIPT',
            'reference_type' => 'PAYMENT',
            'reference_id' => $receipt->id,
            'message_body' => $text,
            'meta_message_id' => $res['message_id'],
            'status' => $res['status'],
            'error_message' => $res['error'],
        ]);

        return $res;
    }

    /**
     * Sends automated or manual payment reminders based on due status.
     */
    public function sendPaymentReminder(Customer $customer, Invoice $invoice, string $reminderType, int $daysOverdue = 0): array
    {
        $phone = $customer->primaryContact?->phone ?: $customer->phone;

        if (empty($phone)) {
            return [
                'success' => false,
                'message_id' => null,
                'status' => 'FAILED',
                'error' => 'Customer has no registered mobile phone number.',
            ];
        }

        $balanceFormatted = '₹'.number_format((float) $invoice->balance_due, 2);
        $dueDateStr = $invoice->due_date ? $invoice->due_date->format('d M Y') : 'Immediately';
        $firmName = $invoice->firm?->firm_name ?: 'ERPSaaS Enterprise';

        $text = match (strtoupper($reminderType)) {
            'BEFORE_DUE' => "🔔 *Upcoming Payment Reminder - {$firmName}*\n\n"
                ."Dear {$customer->company_name},\n"
                ."This is a gentle reminder that Tax Invoice *#{$invoice->invoice_number}* for *{$balanceFormatted}* will become due on *{$dueDateStr}* (in 3 days).\n\n"
                ."Kindly arrange payment to ensure smooth credit operations.\n"
                .'Download Invoice: '.route('invoices.pdf', $invoice->id),

            'ON_DUE' => "⚠️ *Payment Due Today - {$firmName}*\n\n"
                ."Dear {$customer->company_name},\n"
                ."Tax Invoice *#{$invoice->invoice_number}* for *{$balanceFormatted}* is due for settlement *today ({$dueDateStr})*.\n\n"
                ."Please share the payment reference or RTGS UTR number once processed.\n"
                .'Download Invoice: '.route('invoices.pdf', $invoice->id),

            default => "🚨 *Urgent: Payment Overdue - {$firmName}*\n\n"
                ."Dear {$customer->company_name},\n"
                ."Tax Invoice *#{$invoice->invoice_number}* with an outstanding balance of *{$balanceFormatted}* is now *{$daysOverdue} days overdue* (Due date was {$dueDateStr}).\n\n"
                ."To avoid any hold on pending orders and consignments, please settle this bill immediately.\n"
                .'Download Invoice: '.route('invoices.pdf', $invoice->id),
        };

        $driver = $this->getDriver($invoice->firm_id);
        $res = $driver->sendMessage($phone, $text, ['firm_id' => $invoice->firm_id]);

        PaymentReminderLog::create([
            'firm_id' => $invoice->firm_id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'reminder_type' => strtoupper($reminderType),
            'channel' => 'WHATSAPP',
            'recipient_phone' => $phone,
            'recipient_email' => $customer->email,
            'sent_at' => now(),
            'status' => $res['status'],
            'notes' => $text,
        ]);

        WhatsAppMessage::create([
            'firm_id' => $invoice->firm_id,
            'recipient_phone' => $phone,
            'recipient_name' => $customer->company_name,
            'message_type' => 'PAYMENT_REMINDER',
            'reference_type' => 'INVOICE',
            'reference_id' => $invoice->id,
            'message_body' => $text,
            'meta_message_id' => $res['message_id'],
            'status' => $res['status'],
            'error_message' => $res['error'],
        ]);

        return $res;
    }
}
