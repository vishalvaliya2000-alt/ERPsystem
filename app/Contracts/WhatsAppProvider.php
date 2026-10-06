<?php

namespace App\Contracts;

interface WhatsAppProvider
{
    /**
     * Sends a text message or approved template notification.
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     success: bool,
     *     message_id: string|null,
     *     status: string,
     *     error: string|null
     * }
     */
    public function sendMessage(string $recipientPhone, string $message, array $options = []): array;

    /**
     * Sends a PDF document or media attachment.
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     success: bool,
     *     message_id: string|null,
     *     status: string,
     *     error: string|null
     * }
     */
    public function sendDocument(string $recipientPhone, string $documentUrl, string $filename, string $caption = '', array $options = []): array;
}
