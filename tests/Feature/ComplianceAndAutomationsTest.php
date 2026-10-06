<?php

use App\Actions\ProvisionTenantAction;
use App\Models\CommercialShipment;
use App\Models\CommercialShipmentItem;
use App\Models\Customer;
use App\Models\EwayBill;
use App\Models\Firm;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\PaymentReminderLog;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\ServiceReminder;
use App\Models\TenantWhatsAppSetting;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\CompanyFirmContext;
use App\Services\EwayBill\EwayBillService;
use App\Services\WhatsApp\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('tenancy.database.prefix', 'saas_erp_test_tenant_');
    config()->set('app.tenant_domain', 'localhost');

    if (tenancy()->initialized) {
        tenancy()->end();
    }

    DB::table('domains')->whereIn('tenant_id', ['comptestcorp'])->delete();
    DB::table('tenants')->whereIn('id', ['comptestcorp'])->delete();
    auth()->logout();
    session()->flush();
    CompanyFirmContext::flushCache();

    try {
        DB::statement('DROP DATABASE IF EXISTS `saas_erp_test_tenant_comptestcorp`');
    } catch (Throwable $e) {
        // Ignore
    }

    // Provision test tenant
    $provisioner = app(ProvisionTenantAction::class);
    $this->tenant = $provisioner->execute([
        'name' => 'Gujarat Agro Exports Ltd',
        'subdomain' => 'comptestcorp',
        'owner_name' => 'Bhavin Patel',
        'owner_email' => 'bhavin@gujaratagro.com',
        'password' => 'OwnerPassword123!',
        'plan' => 'Standard',
        'gstin' => '24AABCG1234F1Z9',
    ]);

    tenancy()->initialize($this->tenant);
    $this->owner = User::where('email', 'bhavin@gujaratagro.com')->firstOrFail();
    $this->firm = Firm::first();

    // Create a client and product
    $this->customer = Customer::create([
        'customer_code' => 'CUST-COMP-01',
        'company_name' => 'Apex Spices & Foods Pvt Ltd',
        'primary_contact_person' => 'Rajesh Sharma',
        'primary_email' => 'rajesh@apexspices.com',
        'primary_phone' => '+919876543210',
        'address_line' => 'Plot 44, APMC Market Yard, Navi Mumbai',
        'city' => 'Navi Mumbai',
        'state' => 'Maharashtra',
        'state_code' => 27,
        'pincode' => '400705',
        'gst_number' => '27AABCA5555F1Z2',
        'status' => 'ACTIVE',
    ]);

    $this->product = Product::create([
        'product_name' => 'Premium White Dehydrated Onion Flakes',
        'product_code' => 'PRD-WOF-01',
        'hsn_code' => '07122000',
        'unit' => 'KG',
        'base_price' => 180.00,
        'tax_rate' => 5.00,
        'status' => 'ACTIVE',
    ]);

    // Create a confirmed sales order
    $this->order = SalesOrder::create([
        'order_number' => 'SO-2026-COMP-01',
        'customer_id' => $this->customer->id,
        'order_date' => Carbon::now()->subDays(2),
        'status' => 'CONFIRMED',
        'currency' => 'INR',
        'subtotal' => 360000.00,
        'tax_total' => 18000.00,
        'grand_total' => 378000.00,
    ]);

    $this->orderItem = SalesOrderItem::create([
        'sales_order_id' => $this->order->id,
        'product_id' => $this->product->id,
        'order_qty' => 2000.00,
        'shipped_qty' => 2000.00,
        'balance_qty' => 0.00,
        'rate' => 180.00,
        'order_value' => 360000.00,
    ]);

    // Create a commercial shipment
    $this->shipment = CommercialShipment::create([
        'shipment_number' => 'SHP-2026-0001',
        'sales_order_id' => $this->order->id,
        'firm_id' => $this->firm?->id,
        'shipment_date' => Carbon::today(),
        'transporter' => 'V-Trans Logistics India Ltd',
        'lr_number' => 'VT-MUM-98421',
        'vehicle_number' => 'GJ04AX7788',
        'destination' => 'Navi Mumbai Hub, Maharashtra',
        'status' => 'DISPATCHED',
        'total_consignment_value' => 378000.00,
        'freight_payment_type' => 'TO_PAY',
        'freight_amount' => 0.00,
    ]);

    CommercialShipmentItem::create([
        'commercial_shipment_id' => $this->shipment->id,
        'sales_order_item_id' => $this->orderItem->id,
        'quantity' => 2000.00,
        'unit_value' => 180.00,
        'total_value' => 360000.00,
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    DB::table('domains')->whereIn('tenant_id', ['comptestcorp'])->delete();
    DB::table('tenants')->whereIn('id', ['comptestcorp'])->delete();
    try {
        DB::statement('DROP DATABASE IF EXISTS `saas_erp_test_tenant_comptestcorp`');
    } catch (Throwable $e) {
        // Ignore
    }
});

test('generates valid 12-digit statutory E-Way bill with distance-based Rule 138 validity', function () {
    $service = app(EwayBillService::class);

    $ewayBill = $service->generateForShipment($this->shipment, [
        'distance_km' => 640,
        'destination_pincode' => '400705',
        'vehicle_number' => 'GJ04AX7788',
        'transporter' => 'V-Trans Logistics India Ltd',
    ]);

    expect($ewayBill)->toBeInstanceOf(EwayBill::class)
        ->and($ewayBill->eway_bill_number)->toMatch('/^\d{12}$/')
        ->and($ewayBill->status)->toBe('ACTIVE')
        ->and($ewayBill->commercial_shipment_id)->toBe($this->shipment->id);

    // Rule 138: 640 km / 200 km = ceil(3.2) = 4 days of validity
    $diffDays = (int) round($ewayBill->generated_at->diffInDays($ewayBill->valid_until));
    expect($diffDays)->toBeGreaterThanOrEqual(4);

    // QR code payload verification
    expect($ewayBill->qr_code_path)->not->toBeEmpty();
    $qr = json_decode($ewayBill->qr_code_path, true);
    expect($qr['ewbNo'])->toBe($ewayBill->eway_bill_number)
        ->and($qr['vehNo'])->toBe('GJ04AX7788');

    // HTTP Route test for generating eway bill
    $shipment2 = CommercialShipment::create([
        'shipment_number' => 'SHP-2026-0002',
        'sales_order_id' => $this->order->id,
        'firm_id' => $this->firm?->id,
        'shipment_date' => Carbon::today(),
        'transporter' => 'Shreeji Roadlines',
        'vehicle_number' => 'MH04DE1234',
        'destination' => 'Bhiwandi Warehouse',
        'status' => 'DISPATCHED',
        'total_consignment_value' => 200000.00,
    ]);

    $response = $this->actingAs($this->owner)
        ->post("http://comptestcorp.localhost/shipments/{$shipment2->id}/eway", [
            'distance_km' => 350,
            'destination_pincode' => '421302',
            'vehicle_number' => 'MH04DE1234',
            'transporter' => 'Shreeji Roadlines',
        ]);

    $response->assertRedirect();
    $newEwb = EwayBill::where('commercial_shipment_id', $shipment2->id)->first();
    expect($newEwb)->not->toBeNull()
        ->and($newEwb->eway_bill_number)->toMatch('/^\d{12}$/');
});

test('enforces statutory 24-hour cancellation rule for E-Way bills', function () {
    $service = app(EwayBillService::class);

    $ewayBill = $service->generateForShipment($this->shipment, [
        'distance_km' => 250,
        'vehicle_number' => 'GJ04AX7788',
    ]);

    // 1. Cancellation within 24h succeeds
    $cancelRes = $service->cancelEwayBill($ewayBill, 'Order cancelled by buyer', 'Goods unloaded at factory');
    expect($cancelRes['success'])->toBeTrue();

    $ewayBill->refresh();
    expect($ewayBill->status)->toBe('CANCELLED')
        ->and($ewayBill->cancel_reason)->toContain('Order cancelled by buyer');

    // 2. Cancellation after 24h is strictly disallowed by statutory Rule 138
    $oldEwb = EwayBill::create([
        'commercial_shipment_id' => $this->shipment->id,
        'firm_id' => $this->firm?->id,
        'eway_bill_number' => '241122334455',
        'generated_at' => Carbon::now()->subHours(26), // 26 hours ago
        'valid_until' => Carbon::now()->addDays(2),
        'status' => 'ACTIVE',
        'doc_type' => 'INV',
        'supply_type' => 'OUTWARD',
    ]);

    $lateCancelRes = $service->cancelEwayBill($oldEwb, 'Delayed shipment cancel');
    expect($lateCancelRes['success'])->toBeFalse()
        ->and($lateCancelRes['message'])->toContain('cannot be cancelled after 24 hours');

    $oldEwb->refresh();
    expect($oldEwb->status)->toBe('ACTIVE'); // Still ACTIVE, cancellation was rejected
});

test('extends E-Way bill validity with in-transit delay justification', function () {
    $service = app(EwayBillService::class);

    $ewayBill = $service->generateForShipment($this->shipment, [
        'distance_km' => 200,
        'vehicle_number' => 'GJ04AX7788',
    ]);

    $originalValidUntil = $ewayBill->valid_until->copy();

    // Extend validity due to transshipment or breakdown
    $extendRes = $service->extendEwayBill($ewayBill, 250, 'Vehicle axle breakdown on NH-48 near Surat', 'Transshipped to new truck GJ-05-ZZ-1122');

    expect($extendRes['success'])->toBeTrue();

    $ewayBill->refresh();
    expect($ewayBill->status)->toBe('EXTENDED')
        ->and($ewayBill->valid_until->isAfter($originalValidUntil))->toBeTrue()
        ->and($ewayBill->cancel_reason)->toContain('axle breakdown');
});

test('dispatches WhatsApp notifications for Invoices, LR tracking, and Payment Receipts', function () {
    $whatsApp = app(WhatsAppService::class);

    // Create an Invoice
    $invoice = Invoice::create([
        'invoice_number' => 'INV-2026-COMP-01',
        'customer_id' => $this->customer->id,
        'commercial_shipment_id' => $this->shipment->id,
        'firm_id' => $this->firm?->id,
        'invoice_date' => Carbon::today(),
        'due_date' => Carbon::today()->addDays(15),
        'subtotal' => 360000.00,
        'cgst_amount' => 9000.00,
        'sgst_amount' => 9000.00,
        'total_amount' => 378000.00,
        'amount_received' => 0.00,
        'balance_due' => 378000.00,
        'status' => 'UNPAID',
    ]);

    // 1. Dispatch Invoice via WhatsApp
    $invRes = $whatsApp->sendInvoice($invoice);
    expect($invRes['success'])->toBeTrue();

    $invMsg = WhatsAppMessage::where('reference_type', 'INVOICE')->where('reference_id', $invoice->id)->first();
    expect($invMsg)->not->toBeNull()
        ->and($invMsg->status)->toBe('SENT');

    // 2. Dispatch Shipment LR Tracking
    $shipRes = $whatsApp->sendShipmentNotification($this->shipment, '+919876543210');
    expect($shipRes['success'])->toBeTrue();

    $shipMsg = WhatsAppMessage::where('reference_type', 'SHIPMENT')->where('reference_id', $this->shipment->id)->first();
    expect($shipMsg)->not->toBeNull()
        ->and($shipMsg->message_body)->toContain('VT-MUM-98421');

    // 3. Dispatch Receipt Notification
    $receipt = PaymentReceipt::create([
        'receipt_number' => 'RCT-2026-001',
        'customer_id' => $this->customer->id,
        'invoice_id' => $invoice->id,
        'receipt_date' => Carbon::today(),
        'amount_received' => 150000.00,
        'payment_mode' => 'NEFT',
        'reference_number' => 'UTR99887766',
        'status' => 'COMPLETED',
    ]);
    $receiptRes = $whatsApp->sendPaymentReceiptNotification($receipt);
    expect($receiptRes['success'])->toBeTrue();

    // 4. Encrypted Meta Access Token Storage
    $setting = TenantWhatsAppSetting::updateOrCreate(
        ['firm_id' => $this->firm?->id],
        [
            'phone_number_id' => '998877665544',
            'waba_id' => '112233445566',
            'access_token' => 'EAAG_SECRET_META_CLOUD_API_TOKEN_123',
            'is_active' => true,
            'provider' => 'META_CLOUD',
        ]
    );

    // Verify token is encrypted in database but transparently decrypted via Eloquent accessor
    $rawToken = DB::table('tenant_whatsapp_settings')->where('id', $setting->id)->value('access_token_encrypted');
    expect($rawToken)->not->toBe('EAAG_SECRET_META_CLOUD_API_TOKEN_123'); // Encrypted cipher

    $setting->refresh();
    expect($setting->access_token)->toBe('EAAG_SECRET_META_CLOUD_API_TOKEN_123'); // Decrypted
});

test('automated payment reminder command runs accurately on 3-day before, on-due, and overdue cadences without duplicate spamming', function () {
    // Invoice 1: Due in 3 days (BEFORE_DUE)
    $inv1 = Invoice::create([
        'invoice_number' => 'INV-BEFORE-01',
        'customer_id' => $this->customer->id,
        'invoice_date' => Carbon::today()->subDays(10),
        'due_date' => Carbon::today()->addDays(3),
        'total_amount' => 100000.00,
        'balance_due' => 100000.00,
        'status' => 'UNPAID',
    ]);

    // Invoice 2: Due Today (ON_DUE)
    $inv2 = Invoice::create([
        'invoice_number' => 'INV-TODAY-02',
        'customer_id' => $this->customer->id,
        'invoice_date' => Carbon::today()->subDays(15),
        'due_date' => Carbon::today(),
        'total_amount' => 50000.00,
        'balance_due' => 50000.00,
        'status' => 'UNPAID',
    ]);

    // Invoice 3: Overdue by 5 days (OVERDUE)
    $inv3 = Invoice::create([
        'invoice_number' => 'INV-OVERDUE-03',
        'customer_id' => $this->customer->id,
        'invoice_date' => Carbon::today()->subDays(20),
        'due_date' => Carbon::today()->subDays(5),
        'total_amount' => 75000.00,
        'balance_due' => 75000.00,
        'status' => 'UNPAID',
    ]);

    // Invoice 4: Already fully paid (should NOT receive reminder)
    $inv4 = Invoice::create([
        'invoice_number' => 'INV-PAID-04',
        'customer_id' => $this->customer->id,
        'invoice_date' => Carbon::today()->subDays(20),
        'due_date' => Carbon::today(),
        'total_amount' => 80000.00,
        'balance_due' => 0.00,
        'status' => 'PAID',
    ]);

    // Run the automated payment reminder command
    $exitCode = Artisan::call('reminders:send-payment');
    expect($exitCode)->toBe(0);

    // Verify reminder logs
    $log1 = PaymentReminderLog::where('invoice_id', $inv1->id)->where('reminder_type', 'BEFORE_DUE')->first();
    $log2 = PaymentReminderLog::where('invoice_id', $inv2->id)->where('reminder_type', 'ON_DUE')->first();
    $log3 = PaymentReminderLog::where('invoice_id', $inv3->id)->where('reminder_type', 'OVERDUE')->first();
    $log4 = PaymentReminderLog::where('invoice_id', $inv4->id)->first();

    expect($log1)->not->toBeNull()
        ->and($log2)->not->toBeNull()
        ->and($log3)->not->toBeNull()
        ->and($log4)->toBeNull(); // Paid invoice never gets a reminder

    // Test Anti-Spam Protection: Running the command immediately again should NOT resend reminders
    $secondRun = Artisan::call('reminders:send-payment');
    expect($secondRun)->toBe(0);

    expect(PaymentReminderLog::where('invoice_id', $inv1->id)->count())->toBe(1);
    expect(PaymentReminderLog::where('invoice_id', $inv2->id)->count())->toBe(1);
    expect(PaymentReminderLog::where('invoice_id', $inv3->id)->count())->toBe(1);
});

test('service reminders track machinery maintenance and advance next due date on completion', function () {
    $reminder = ServiceReminder::create([
        'service_name' => 'Rotary Dryer Main Bearing Greasing & Alignment',
        'service_type' => 'PREVENTIVE',
        'frequency_days' => 45,
        'next_due_date' => Carbon::today()->addDays(2),
        'priority' => 'HIGH',
        'status' => 'PENDING',
    ]);

    expect($reminder->service_name)->toBe('Rotary Dryer Main Bearing Greasing & Alignment')
        ->and($reminder->frequency_days)->toBe(45);

    // Mark completed
    $reminder->markCompleted('Greased SKF bearings with high-temp grease, vibration levels nominal.');

    $reminder->refresh();
    expect($reminder->last_serviced_date->isSameDay(Carbon::today()))->toBeTrue();
    // Next due date should be today + 45 days
    expect($reminder->next_due_date->isSameDay(Carbon::today()->addDays(45)))->toBeTrue();
    expect($reminder->notes)->toContain('SKF bearings');

    // Test controller complete action
    $reminder2 = ServiceReminder::create([
        'service_name' => 'Air Compressor Oil Filter Change',
        'service_type' => 'PREVENTIVE',
        'frequency_days' => 90,
        'next_due_date' => Carbon::today(),
        'priority' => 'MEDIUM',
        'status' => 'PENDING',
    ]);

    $response = $this->actingAs($this->owner)
        ->post("http://comptestcorp.localhost/service-reminders/{$reminder2->id}/complete", [
            'notes' => 'Synthetic lubricant topped up and intake cartridge renewed.',
        ]);

    $response->assertRedirect();
    $reminder2->refresh();
    expect($reminder2->next_due_date->isSameDay(Carbon::today()->addDays(90)))->toBeTrue();
});
