<?php

namespace App\Services\EwayBill;

use App\Contracts\EwayBillProvider;
use App\Models\CommercialShipment;
use App\Models\EwayBill;
use Carbon\Carbon;

class MockEwayBillDriver implements EwayBillProvider
{
    /**
     * Generates a simulated official 12-digit Indian E-way Bill conforming to NIC GST guidelines.
     */
    public function generate(CommercialShipment $shipment, array $params = []): array
    {
        $generatedAt = Carbon::now();

        // 1. Calculate simulated distance based on origin & destination pin or default
        $originPin = (int) ($shipment->firm?->pincode ?: 364290); // Default Mahuva, Gujarat
        $destPin = (int) ($params['destination_pincode'] ?? 400001); // Default Mumbai, Maharashtra

        $distanceKm = (int) ($params['distance_km'] ?? 0);
        if ($distanceKm <= 0) {
            // Realistic simulated transit distance
            $diff = abs($originPin - $destPin);
            $distanceKm = max(80, min(2400, (int) round(($diff / 100) * 1.8)));
            if ($distanceKm <= 80) {
                $distanceKm = rand(150, 480);
            }
        }

        // 2. Compute statutory validity window: 1 day per 200 km (or fraction thereof)
        $validDays = max(1, (int) ceil($distanceKm / 200));
        $validUntil = $generatedAt->copy()->addDays($validDays)->endOfDay();

        // 3. Generate valid 12-digit numeric E-way Bill number
        // State code prefix (e.g. 24 for Gujarat) + 10 random digits
        $stateCode = $shipment->firm ? $shipment->firm->resolveStateCode() : '24';
        $randDigits = str_pad((string) rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
        $ewayBillNumber = $stateCode.$randDigits;

        // 4. Construct NIC API compliant payload
        $invoice = $shipment->invoices()->first();
        $orders = $shipment->getDistinctOrders();
        $customer = $orders->first()?->customer;

        $totalValue = (float) ($invoice?->total_amount ?? ($shipment->items->sum('total_amount') ?: 100000.00));
        $cgst = (float) ($invoice?->cgst_amount ?? 0.0);
        $sgst = (float) ($invoice?->sgst_amount ?? 0.0);
        $igst = (float) ($invoice?->igst_amount ?? 0.0);

        $payload = [
            'supplyType' => 'O',
            'subSupplyType' => 1,
            'docType' => 'INV',
            'docNo' => $invoice?->invoice_number ?? $shipment->shipment_number,
            'docDate' => ($invoice?->invoice_date ?? $shipment->shipment_date ?? $generatedAt)->format('d/m/Y'),
            'fromGstin' => $shipment->firm?->gstin ?? '24AABCR1234F1Z1',
            'fromTrdName' => $shipment->firm?->firm_name ?? 'ERPSaaS Enterprise Unit',
            'fromAddr1' => $shipment->firm?->address_line1 ?? 'GIDC Industrial Estate',
            'fromPlace' => $shipment->firm?->city ?? 'Mahuva',
            'fromPincode' => $originPin,
            'actFromStateCode' => (int) $stateCode,
            'toGstin' => $customer?->gst_number ?? '27AABCK9999F1Z5',
            'toTrdName' => $customer?->company_name ?? 'Consignee Enterprise',
            'toAddr1' => $customer?->address ?? 'APMC Market Yard',
            'toPlace' => $shipment->destination ?: 'Mumbai',
            'toPincode' => $destPin,
            'actToStateCode' => (int) ($customer?->state_code ?? 27),
            'totalValue' => $totalValue,
            'cgstValue' => $cgst,
            'sgstValue' => $sgst,
            'igstValue' => $igst,
            'transMode' => 1, // Road
            'transDistance' => $distanceKm,
            'transporterId' => $shipment->transporter ?: 'IN-TRANS-01',
            'transporterName' => $shipment->transporter ?: 'Direct Logistics',
            'transDocNo' => $shipment->lr_number ?: 'LR-'.$shipment->id,
            'transDocDate' => $generatedAt->format('d/m/Y'),
            'vehicleNo' => $shipment->vehicle_number ?: 'GJ-04-AX-9999',
            'vehicleType' => 'R', // Regular
        ];

        // 5. Generate QR code payload string
        $qrCodeData = json_encode([
            'ewbNo' => $ewayBillNumber,
            'genDate' => $generatedAt->toIso8601String(),
            'validUntil' => $validUntil->toIso8601String(),
            'fromGstin' => $payload['fromGstin'],
            'toGstin' => $payload['toGstin'],
            'docNo' => $payload['docNo'],
            'totVal' => $totalValue,
            'vehNo' => $payload['vehicleNo'],
            'status' => 'ACT',
        ]);

        return [
            'eway_bill_number' => $ewayBillNumber,
            'generated_at' => $generatedAt->toDateTimeString(),
            'valid_until' => $validUntil->toDateTimeString(),
            'distance_km' => $distanceKm,
            'qr_code_data' => $qrCodeData ?: '',
            'raw_response' => [
                'status' => 1,
                'error' => null,
                'data' => [
                    'ewayBillNo' => $ewayBillNumber,
                    'ewayBillDate' => $generatedAt->format('d/m/Y h:i:s A'),
                    'validUpto' => $validUntil->format('d/m/Y h:i:s A'),
                    'alert' => 'MOCK_SANDBOX_SUCCESS: Valid 12-digit E-way Bill generated in compliance with NIC schema.',
                ],
                'request_payload' => $payload,
            ],
        ];
    }

    /**
     * Cancels an active E-way bill.
     */
    public function cancel(EwayBill $ewayBill, string $reason, string $remarks = ''): array
    {
        $hoursSinceGen = $ewayBill->generated_at ? $ewayBill->generated_at->diffInHours(now()) : 0;

        if ($hoursSinceGen > 24) {
            return [
                'success' => false,
                'cancel_date' => now()->toDateTimeString(),
                'message' => 'Disallowed: E-way Bill cannot be cancelled after 24 hours of generation under GST rules.',
            ];
        }

        return [
            'success' => true,
            'cancel_date' => now()->toDateTimeString(),
            'message' => "E-way Bill {$ewayBill->eway_bill_number} cancelled successfully. Reason: {$reason}.",
        ];
    }

    /**
     * Extends validity of an E-way bill.
     */
    public function extend(EwayBill $ewayBill, int $additionalKm, string $reason, string $remarks = ''): array
    {
        $currentValid = $ewayBill->valid_until ?: now();
        $extraDays = max(1, (int) ceil($additionalKm / 200));
        $newValidUntil = $currentValid->copy()->addDays($extraDays)->endOfDay();

        return [
            'success' => true,
            'new_valid_until' => $newValidUntil->toDateTimeString(),
            'message' => "Validity extended by {$extraDays} day(s) until {$newValidUntil->format('d M Y, h:i A')}. Reason: {$reason}.",
        ];
    }
}
