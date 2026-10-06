<?php

namespace App\Services\EwayBill;

use App\Contracts\EwayBillProvider;
use App\Models\CommercialShipment;
use App\Models\EwayBill;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GspEwayBillDriver implements EwayBillProvider
{
    public function __construct(
        protected MockEwayBillDriver $fallbackDriver
    ) {}

    public function generate(CommercialShipment $shipment, array $params = []): array
    {
        $baseUrl = config('services.eway_gsp.base_url');
        $clientId = config('services.eway_gsp.client_id');
        $clientSecret = config('services.eway_gsp.client_secret');

        // If credentials are not configured in environment, fall back to sandbox mock driver
        if (empty($baseUrl) || empty($clientId) || empty($clientSecret)) {
            Log::info('GSP E-way bill credentials not configured. Utilizing MockEwayBillDriver sandbox.');

            return $this->fallbackDriver->generate($shipment, $params);
        }

        try {
            $response = Http::withHeaders([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post("{$baseUrl}/ewayapi/v1.03/bills/generate", [
                'shipment_id' => $shipment->id,
                'params' => $params,
            ]);

            if ($response->successful()) {
                $resData = $response->json();

                return [
                    'eway_bill_number' => (string) $resData['data']['ewayBillNo'],
                    'generated_at' => Carbon::parse($resData['data']['ewayBillDate'])->toDateTimeString(),
                    'valid_until' => Carbon::parse($resData['data']['validUpto'])->toDateTimeString(),
                    'distance_km' => (int) ($resData['data']['distance'] ?? 350),
                    'qr_code_data' => (string) ($resData['data']['qrCode'] ?? ''),
                    'raw_response' => $resData,
                ];
            }

            Log::warning('GSP E-way Bill generation API returned non-200. Falling back to sandbox driver.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->fallbackDriver->generate($shipment, $params);
        } catch (\Throwable $e) {
            Log::error('GSP E-way Bill API exception: '.$e->getMessage().'. Falling back to sandbox driver.');

            return $this->fallbackDriver->generate($shipment, $params);
        }
    }

    public function cancel(EwayBill $ewayBill, string $reason, string $remarks = ''): array
    {
        return $this->fallbackDriver->cancel($ewayBill, $reason, $remarks);
    }

    public function extend(EwayBill $ewayBill, int $additionalKm, string $reason, string $remarks = ''): array
    {
        return $this->fallbackDriver->extend($ewayBill, $additionalKm, $reason, $remarks);
    }
}
