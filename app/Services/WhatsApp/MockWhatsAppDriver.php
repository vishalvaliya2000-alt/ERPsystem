<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProvider;
use Illuminate\Support\Str;

class MockWhatsAppDriver implements WhatsAppProvider
{
    /**
     * Simulates WhatsApp text or template message dispatch.
     */
    public function sendMessage(string $recipientPhone, string $message, array $options = []): array
    {
        $messageId = 'wamid.'.Str::random(32);

        return [
            'success' => true,
            'message_id' => $messageId,
            'status' => 'SENT',
            'error' => null,
            'simulated_payload' => [
                'to' => $recipientPhone,
                'type' => 'text',
                'body' => $message,
                'options' => $options,
            ],
        ];
    }

    /**
     * Simulates WhatsApp PDF document dispatch.
     */
    public function sendDocument(string $recipientPhone, string $documentUrl, string $filename, string $caption = '', array $options = []): array
    {
        $messageId = 'wamid.'.Str::random(32);

        return [
            'success' => true,
            'message_id' => $messageId,
            'status' => 'SENT',
            'error' => null,
            'simulated_payload' => [
                'to' => $recipientPhone,
                'type' => 'document',
                'document_url' => $documentUrl,
                'filename' => $filename,
                'caption' => $caption,
                'options' => $options,
            ],
        ];
    }
}
