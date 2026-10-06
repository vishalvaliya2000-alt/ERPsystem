<?php

use App\Models\BusinessDocument;
use App\Models\CommercialShipment;
use App\Models\Customer;
use App\Models\DocumentVersion;
use App\Models\FollowupTask;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->tenant = Tenant::first() ?? Tenant::create([
        'name' => 'Acme Architecture Tenant',
        'slug' => 'acme-architecture-tenant',
        'industry' => 'Food Processing & Exports',
        'plan' => 'Enterprise',
        'currency_code' => 'INR',
        'currency_symbol' => '₹',
        'invoice_prefix' => 'INV-',
        'quotation_prefix' => 'QTN-',
        'po_prefix' => 'PO-',
        'shipment_prefix' => 'SHP-',
    ]);

    $this->user = User::first() ?? User::factory()->create(['tenant_id' => $this->tenant->id]);
    if (! $this->user->tenant_id) {
        $this->user->tenant_id = $this->tenant->id;
        $this->user->save();
    }

    TenantManager::setTenant($this->tenant);
    Storage::fake('public');

    // Create baseline customer and product
    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'customer_code' => 'CUST-TEST-'.rand(100, 999),
        'company_name' => 'Test Spice Global Foods',
        'city' => 'Mahuva',
        'state' => 'Gujarat',
        'gst_number' => '24AAACT'.rand(1000, 9999).'Z1Z1',
        'payment_terms_days' => 30,
        'stage' => 'ACTIVE',
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'product_code' => 'PRD-'.rand(100, 999),
        'product_name' => 'Premium White Onion Powder',
        'granulation' => 'Powder 80-100 Mesh',
        'standard_rate' => 140,
        'hsn_code' => '07122000',
        'is_active' => true,
    ]);
});

test('1. Sales order creation directly attaches customer po document with bidirectional linking', function () {
    $poFile = UploadedFile::fake()->create('customer_po_101.pdf', 500, 'application/pdf');

    $postData = [
        'customer_id' => $this->customer->id,
        'po_number' => 'PO-CUST-9988',
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => now()->addDays(15)->toDateString(),
        'payment_terms' => '30 Days Net',
        'delivery_terms' => 'FOR Mahuva Plant',
        'po_document' => $poFile,
        'items' => [
            [
                'product_id' => $this->product->id,
                'order_qty' => 2000,
                'rate' => 140,
            ],
        ],
    ];

    $response = $this->actingAs($this->user)->post(route('orders.store'), $postData);

    $response->assertRedirect(route('orders.index'));
    $response->assertSessionHas('success');

    $order = SalesOrder::where('po_number', 'PO-CUST-9988')->first();
    expect($order)->not->toBeNull();
    expect($order->customer_id)->toBe($this->customer->id);

    // Verify BusinessDocument record created with proper type and link
    $doc = BusinessDocument::where('sales_order_id', $order->id)->where('document_type', 'PO')->first();
    expect($doc)->not->toBeNull();
    expect($doc->document_number)->toBe('PO-CUST-9988');
    expect($doc->customer_id)->toBe($this->customer->id);
    expect($doc->activeVersion)->not->toBeNull();

    // Verify file stored in storage disk
    Storage::disk('public')->assertExists($doc->activeVersion->file_path);

    // Verify bidirectional relationships
    expect($order->poDocument)->not->toBeNull();
    expect($order->poDocument->id)->toBe($doc->id);
    expect($doc->salesOrder->id)->toBe($order->id);
});

test('2. Sales order creation rejects disallowed file types without storing documents or files', function () {
    $badFile = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

    $initialOrdersCount = SalesOrder::count();
    $initialDocsCount = BusinessDocument::count();

    $postData = [
        'customer_id' => $this->customer->id,
        'po_number' => 'PO-BAD-FILE',
        'order_date' => now()->toDateString(),
        'po_document' => $badFile,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1000,
                'rate' => 140,
            ],
        ],
    ];

    $response = $this->actingAs($this->user)->post(route('orders.store'), $postData);
    $response->assertSessionHasErrors('po_document');

    expect(SalesOrder::count())->toBe($initialOrdersCount);
    expect(BusinessDocument::count())->toBe($initialDocsCount);
});

test('3. Shipment creation directly attaches transporter LR document with bidirectional linking', function () {
    // Create sales order first
    $order = SalesOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'SO-TEST-'.rand(1000, 9999),
        'po_number' => 'PO-SHP-001',
        'order_date' => now(),
        'total_amount' => 280000,
        'status' => 'APPROVED',
    ]);

    $orderItem = SalesOrderItem::create([
        'sales_order_id' => $order->id,
        'product_id' => $this->product->id,
        'order_qty' => 2000,
        'shipped_qty' => 0,
        'balance_qty' => 2000,
        'rate' => 140,
        'order_value' => 280000,
    ]);

    $lrFile = UploadedFile::fake()->create('transporter_lr_5544.pdf', 300, 'application/pdf');

    $postData = [
        'sales_order_id' => $order->id,
        'lr_number' => 'LR-TR-8899',
        'transporter' => 'Om Logistics Ltd',
        'vehicle_number' => 'GJ-04-AX-1234',
        'shipment_date' => now()->toDateString(),
        'freight_amount' => 15000,
        'freight_payment_type' => 'TO_PAY',
        'status' => 'IN_TRANSIT',
        'lr_document' => $lrFile,
        'items' => [
            [
                'sales_order_item_id' => $orderItem->id,
                'quantity' => 1000,
            ],
        ],
    ];

    $response = $this->actingAs($this->user)->post(route('shipments.store'), $postData);
    $response->assertRedirect(route('shipments.index'));
    $response->assertSessionHas('success');

    $shipment = CommercialShipment::where('lr_number', 'LR-TR-8899')->first();
    expect($shipment)->not->toBeNull();

    // Verify BusinessDocument record created for LR
    $doc = BusinessDocument::where('commercial_shipment_id', $shipment->id)->where('document_type', 'LR')->first();
    expect($doc)->not->toBeNull();
    expect($doc->document_number)->toBe('LR-TR-8899');
    expect($doc->customer_id)->toBe($this->customer->id);

    // Verify file storage and relations
    Storage::disk('public')->assertExists($doc->activeVersion->file_path);
    expect($shipment->lrDocument)->not->toBeNull();
    expect($shipment->lrDocument->id)->toBe($doc->id);
    expect($doc->commercialShipment->id)->toBe($shipment->id);
});

test('4. Invoice creation attaches supporting doc and generates official ERP Tax Invoice PDF', function () {
    $order = SalesOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'SO-INV-TEST',
        'po_number' => 'PO-INV-001',
        'order_date' => now(),
        'total_amount' => 140000,
        'status' => 'IN_PROGRESS',
    ]);

    $supportingDoc = UploadedFile::fake()->create('delivery_challan_signed.jpg', 200, 'image/jpeg');

    $postData = [
        'customer_id' => $this->customer->id,
        'sales_order_id' => $order->id,
        'invoice_number' => 'INV-TEST-9001',
        'invoice_date' => now()->toDateString(),
        'material_subtotal' => 140000,
        'freight_amount' => 5000,
        'gst_rate' => 5,
        'notes' => 'Dispatched via Road Carrier',
        'invoice_document' => $supportingDoc,
    ];

    $response = $this->actingAs($this->user)->post(route('invoices.store'), $postData);
    $response->assertRedirect(route('invoices.index'));
    $response->assertSessionHas('success');

    $invoice = Invoice::where('invoice_number', 'INV-TEST-9001')->first();
    expect($invoice)->not->toBeNull();

    // Verify supporting document was attached and stored
    $suppDoc = BusinessDocument::where('invoice_id', $invoice->id)
        ->where('notes', 'like', '%Dispatched via Road Carrier%')
        ->first();
    expect($suppDoc)->not->toBeNull();
    Storage::disk('public')->assertExists($suppDoc->activeVersion->file_path);

    // Verify ERP-generated Tax Invoice PDF was created and stored
    $pdfDoc = BusinessDocument::where('invoice_id', $invoice->id)
        ->where('document_number', 'INV-TEST-9001')
        ->first();
    expect($pdfDoc)->not->toBeNull();
    expect($pdfDoc->document_type)->toBe('INVOICE');
    Storage::disk('public')->assertExists($pdfDoc->activeVersion->file_path);

    // Verify invoice PDF download route
    $pdfResponse = $this->actingAs($this->user)->get(route('invoices.pdf', $invoice->id));
    $pdfResponse->assertStatus(200);
    $pdfResponse->assertHeader('content-type', 'application/pdf');
});

test('5. Document Centre page is completely removed and route redirects to dashboard', function () {
    $response = $this->actingAs($this->user)->get(route('documents.index'));
    $response->assertRedirect(route('dashboard'));
});

test('6. Customer 360 page renders all connected documents and transaction badges', function () {
    $order = SalesOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'SO-CUST-360',
        'po_number' => 'PO-360-XYZ',
        'order_date' => now(),
        'total_amount' => 100000,
        'status' => 'APPROVED',
    ]);

    // Attach PO doc to order
    $poDoc = BusinessDocument::create([
        'tenant_id' => $this->tenant->id,
        'document_type' => 'PO',
        'document_number' => 'PO-360-XYZ',
        'document_date' => now(),
        'customer_id' => $this->customer->id,
        'sales_order_id' => $order->id,
        'total_amount' => 100000,
        'status' => 'VERIFIED',
    ]);
    DocumentVersion::create([
        'document_id' => $poDoc->id,
        'version_number' => 1,
        'file_path' => 'documents/'.$this->tenant->id.'/test_po.pdf',
        'file_name' => 'test_po.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->get(route('customers.show', $this->customer->id));
    $response->assertStatus(200);
    $response->assertSee('Customer PO Doc');
    $response->assertSee('Connected Transaction');
    $response->assertSee('SO: SO-CUST-360');
});

test('7. Two-phase shipment flow: scheduling reserves balance without LR and confirm dispatch updates LR to DISPATCHED', function () {
    $order = SalesOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'SO-SCHED-'.rand(100, 999),
        'order_date' => now(),
        'total_amount' => 140000,
        'status' => 'APPROVED',
    ]);

    $orderItem = SalesOrderItem::create([
        'sales_order_id' => $order->id,
        'product_id' => $this->product->id,
        'order_qty' => 1000,
        'rate' => 140,
        'order_value' => 140000,
        'balance_qty' => 1000,
        'shipped_qty' => 0,
        'status' => 'PENDING',
    ]);

    // Step 1: Schedule future shipment (No LR, status SCHEDULED)
    $scheduleData = [
        'status' => 'SCHEDULED',
        'shipment_date' => now()->addDays(5)->toDateString(),
        'freight_payment_type' => 'TO_PAY',
        'items' => [
            [
                'sales_order_item_id' => $orderItem->id,
                'quantity' => 600,
            ],
        ],
    ];

    $response = $this->actingAs($this->user)->post(route('shipments.store'), $scheduleData);
    $response->assertRedirect(route('shipments.index'));
    $response->assertSessionHas('success');

    // Verify shipment created with status SCHEDULED, null LR
    $shipment = CommercialShipment::where('status', 'SCHEDULED')->latest()->first();
    expect($shipment)->not->toBeNull();
    expect($shipment->lr_number)->toBeNull();
    expect($shipment->transporter)->toBe('TBD (To Be Decided)');

    // Verify item quantity deducted/reserved from PO balance
    $orderItem->refresh();
    expect((float) $orderItem->balance_qty)->toBe(400.0);
    expect((float) $orderItem->shipped_qty)->toBe(600.0);

    // Verify FollowupTask created for DISPATCH_SCHEDULE
    $task = FollowupTask::where('customer_id', $this->customer->id)
        ->where('type', 'DISPATCH_SCHEDULE')
        ->latest()
        ->first();
    expect($task)->not->toBeNull();

    // Step 2: Confirm Dispatch (Assign LR & vehicle, set status to DISPATCHED)
    $lrFile = UploadedFile::fake()->create('actual_bilty.pdf', 200, 'application/pdf');
    $confirmData = [
        'status' => 'DISPATCHED',
        'transporter' => 'Shree Mahalakshmi Express',
        'lr_number' => 'LR-SCHED-CONFIRM-99',
        'shipment_date' => now()->toDateString(),
        'vehicle_number' => 'GJ-04-AX-5555',
        'freight_payment_type' => 'TO_PAY',
        'lr_document' => $lrFile,
    ];

    $updateResponse = $this->actingAs($this->user)->post("/shipments/{$shipment->id}/update", $confirmData);
    $updateResponse->assertRedirect(route('shipments.index'));

    $shipment->refresh();
    expect($shipment->status)->toBe('DISPATCHED');
    expect($shipment->lr_number)->toBe('LR-SCHED-CONFIRM-99');
    expect($shipment->transporter)->toBe('Shree Mahalakshmi Express');
    expect($shipment->vehicle_number)->toBe('GJ-04-AX-5555');

    // Verify delivery confirmation task created
    $deliveryTask = FollowupTask::where('customer_id', $this->customer->id)
        ->where('type', 'DELIVERY_CONFIRMATION')
        ->latest()
        ->first();
    expect($deliveryTask)->not->toBeNull();
});
