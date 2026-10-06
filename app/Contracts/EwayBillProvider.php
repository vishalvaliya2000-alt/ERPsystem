<?php

namespace App\Contracts;

use App\Models\CommercialShipment;
use App\Models\EwayBill;

interface EwayBillProvider
{
    /**
     * Generates an official or simulated E-way bill for a shipment.
     *
     * @param  array<string, mixed>  $params
     * @return array{
     *     eway_bill_number: string,
     *     generated_at: string,
     *     valid_until: string,
     *     distance_km: int,
     *     qr_code_data: string,
     *     raw_response: array<string, mixed>
     * }
     */
    public function generate(CommercialShipment $shipment, array $params = []): array;

    /**
     * Cancels an active E-way bill within statutory cancellation window.
     *
     * @return array{
     *     success: bool,
     *     cancel_date: string,
     *     message: string
     * }
     */
    public function cancel(EwayBill $ewayBill, string $reason, string $remarks = ''): array;

    /**
     * Extends validity of an active E-way bill.
     *
     * @return array{
     *     success: bool,
     *     new_valid_until: string,
     *     message: string
     * }
     */
    public function extend(EwayBill $ewayBill, int $additionalKm, string $reason, string $remarks = ''): array;
}
