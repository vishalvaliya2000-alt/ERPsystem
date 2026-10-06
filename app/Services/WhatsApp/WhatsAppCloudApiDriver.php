<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\Models\TenantWhatsAppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudApiDriver implements WhatsAppProvider
{
    public function __construct(
        protected MockWhatsAppDriver $fallbackDriver
    ) {}

    /**
     * Resolves active Meta credentials from tenant settings or env.
     *
     * @return array{phone_number_id: string|null, access_token: string|null}
     */
    protected function getCredentials(?int $firmId = null): array
    {
        $setting = TenantWhatsAppSetting::where('is_active', true)
            ->when($firmId, fn ($q) => $q->where('firm_id', $firmId))
            ->first();

        $phoneId = $setting?->phone_number_id ?: config('services.whatsapp.phone_number_id');
        $token = $setting?->access_token ?: config('services.whatsapp.access_token');

        return [
            'phone_number_id' => $phoneId,
            'access_token' => $token,
        ];
    }

    public function sendMessage(string $recipientPhone, string $message, array $options = []): array
    {
        $creds = $this->getCredentials($options['firm_id'] ?? null);

        if (empty($creds['phone_number_id']) || empty($creds['access_token'])) {
            Log::info('WhatsApp Cloud API credentials not configured. Utilizing MockWhatsAppDriver.');

            return $this->fallbackDriver->sendMessage($recipientPhone, $message, $options);
        }

        $phoneId = $creds['phone_number_id'];
        $cleanPhone = preg_replace('/[^0-9]/', '', $recipientPhone);
        if (strlen($cleanPhone) === 10) {
            $cleanPhone = '91'.$cleanPhone; // Default India prefix
        }

        try {
            $response = Http::withToken($creds['access_token'])
                ->timeout(15)
                ->post("https://graph.facebook.com/v19.0/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanPhone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => true,
                        'body' => $message,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $msgId = $data['messages'][0]['id'] ?? null;

                return [
                    'success' => true,
                    'message_id' => $msgId,
                    'status' => 'SENT',
                    'error' => null,
                ];
            }

            Log::warning('WhatsApp Cloud API returned non-200. Falling back to sandbox driver.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->fallbackDriver->sendMessage($recipientPhone, $message, $options);
        } catch (\Throwable $e) {
            Log::error('WhatsApp Cloud API exception: '.$e->getMessage().'. Falling back to sandbox driver.');

            return $this->fallbackDriver->sendMessage($recipientPhone, $message, $options);
        }
    }

    public function sendDocument(string $recipientPhone, string $documentUrl, string $filename, string $caption = '', array $options = []): array
    {
        $creds = $this->getCredentials($options['firm_id'] ?? null);

        if (empty($creds['phone_number_id']) || empty($creds['access_token'])) {
            Log::info('WhatsApp Cloud API credentials not configured. Utilizing MockWhatsAppDriver for document.');

            return $this->fallbackDriver->sendDocument($recipientPhone, $documentUrl, $filename, $caption, $options);
        }

        $phoneId = $creds['phone_number_id'];
        $cleanPhone = preg_replace('/[^0-9]/', '', $recipientPhone);
        if (strlen($cleanPhone) === 10) {
            $cleanPhone = '91'.$cleanPhone;
        }

        try {
            $response = Http::withToken($creds['access_token'])
                ->timeout(20)
                ->post("https://graph.facebook.com/v19.0/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanPhone,
                    'type' => 'document',
                    'document' => [
                        'link' => $documentUrl,
                        'caption' => $caption,
                        'filename' => $filename,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $msgId = $data['messages'][0]['id'] ?? null;

                return [
                    'success' => true,
                    'message_id' => $msgId,
                    'status' => 'SENT',
                    'error' => null,
                ];
            }

            Log::warning('WhatsApp document API failed. Falling back to mock driver.');

            return $this->fallbackDriver->sendDocument($recipientPhone, $documentUrl, $filename, $caption, $options);
        } catch (\Throwable $e) {
            Log::error('WhatsApp document exception: '.$e->getMessage());

            return $this->fallbackDriver->sendDocument($recipientPhone, $documentUrl, $filename, $caption, $options);
        }
    }
}
