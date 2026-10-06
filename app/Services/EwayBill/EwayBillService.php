<?php

namespace App\Services\EwayBill;

use App\Contracts\EwayBillProvider;
use App\Models\CommercialShipment;
use App\Models\EwayBill;
use App\Models\TenantUsage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EwayBillService
{
    public function __construct(
        protected MockEwayBillDriver $mockDriver,
        protected GspEwayBillDriver $gspDriver
    ) {}

    /**
     * Resolves the appropriate E-way bill driver.
     */
    public function getDriver(): EwayBillProvider
    {
        $driverName = config('services.eway_bill.driver', 'mock');

        return match (strtolower($driverName)) {
            'gsp' => $this->gspDriver,
            default => $this->mockDriver,
        };
    }

    /**
     * Generates and stores an E-way Bill for a given Commercial Shipment.
     *
     * @param  array<string, mixed>  $params
     */
    public function generateForShipment(CommercialShipment $shipment, array $params = []): EwayBill
    {
        // 1. Validation: check if an active E-way bill already exists for this shipment
        $existing = EwayBill::where('commercial_shipment_id', $shipment->id)
            ->where('status', 'ACTIVE')
            ->first();

        if ($existing && $existing->isValid()) {
            throw ValidationException::withMessages([
                'shipment' => ["Shipment {$shipment->shipment_number} already has an active E-way Bill (#{$existing->eway_bill_number})."],
            ]);
        }

        // 2. Validate Vehicle / Transporter details
        if (empty($shipment->vehicle_number) && empty($shipment->transporter)) {
            throw ValidationException::withMessages([
                'vehicle_number' => ['Either Vehicle Number or Transporter must be specified to generate an E-way Bill under Rule 138.'],
            ]);
        }

        $driver = $this->getDriver();
        $result = $driver->generate($shipment, $params);

        return DB::transaction(function () use ($shipment, $result) {
            $ewayBill = EwayBill::create([
                'commercial_shipment_id' => $shipment->id,
                'firm_id' => $shipment->firm_id,
                'eway_bill_number' => $result['eway_bill_number'],
                'generated_at' => Carbon::parse($result['generated_at']),
                'valid_until' => Carbon::parse($result['valid_until']),
                'status' => 'ACTIVE',
                'doc_type' => 'INV',
                'supply_type' => 'OUTWARD',
                'request_payload' => $result['raw_response']['request_payload'] ?? null,
                'response_payload' => $result['raw_response'],
                'qr_code_path' => $result['qr_code_data'],
            ]);

            // Track usage counter in central tenant_usage
            if (tenancy()->initialized) {
                TenantUsage::incrementMetric('eway_bills_generated');
            }

            return $ewayBill;
        });
    }

    /**
     * Cancels an E-way bill with reason code.
     */
    public function cancelEwayBill(EwayBill $ewayBill, string $reason, string $remarks = ''): array
    {
        $driver = $this->getDriver();
        $res = $driver->cancel($ewayBill, $reason, $remarks);

        if ($res['success']) {
            $ewayBill->update([
                'status' => 'CANCELLED',
                'cancel_reason' => $reason.($remarks ? " - {$remarks}" : ''),
            ]);
        }

        return $res;
    }

    /**
     * Extends validity of an E-way bill with transit delay / transshipment justification.
     */
    public function extendEwayBill(EwayBill $ewayBill, int $additionalKm, string $reason, string $remarks = ''): array
    {
        $driver = $this->getDriver();
        $res = $driver->extend($ewayBill, $additionalKm, $reason, $remarks);

        if ($res['success']) {
            $ewayBill->update([
                'valid_until' => Carbon::parse($res['new_valid_until']),
                'status' => 'EXTENDED',
                'cancel_reason' => "Extended: {$reason}".($remarks ? " - {$remarks}" : ''),
            ]);
        }

        return $res;
    }
}
