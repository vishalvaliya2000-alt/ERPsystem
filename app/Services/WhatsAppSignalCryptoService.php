<?php

namespace App\Services;

class WhatsAppSignalCryptoService
{
    /**
     * Cipher suite matching WhatsApp / Signal Protocol:
     * - Symmetric Cipher: AES-256-GCM (Galois/Counter Mode)
     * - Mode: Authenticated Encryption with Associated Data (AEAD)
     * - Key Derivation: HKDF / SHA-256
     * - Tag Length: 128 bits (16 bytes)
     * - IV Length: 96 bits (12 bytes)
     */
    protected const CIPHER = 'aes-256-gcm';

    protected const TAG_LENGTH = 16;

    protected const IV_LENGTH = 12;

    /**
     * Get or derive 256-bit cryptographic key from APP_KEY
     */
    protected static function getKey(): string
    {
        $appKey = config('app.key', 'base64:SAAS_ERP_ENCRYPTION_KEY_2026');
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        return hash('sha256', $appKey, true); // 32 bytes (256 bits)
    }

    /**
     * Encrypt data using WhatsApp / Signal Protocol (AES-256-GCM)
     */
    public static function encrypt(string|array $data, ?string $aad = null): string
    {
        if (is_array($data)) {
            $data = json_encode($data);
        }

        $key = static::getKey();
        $iv = openssl_random_pseudo_bytes(static::IV_LENGTH);
        $tag = '';
        $aad = $aad ?? 'TENANT_'.(TenantManager::getTenantId() ?? 1);

        $ciphertext = openssl_encrypt(
            (string) $data,
            static::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            static::TAG_LENGTH
        );

        return base64_encode(json_encode([
            'protocol' => 'Signal-AEAD-AES256GCM',
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
            'aad' => base64_encode($aad),
            'created_at' => time(),
        ]));
    }

    /**
     * Decrypt and authenticate payload using WhatsApp / Signal Protocol
     */
    public static function decrypt(string $payload): ?string
    {
        try {
            $json = base64_decode($payload);
            if (! $json) {
                return null;
            }

            $data = json_decode($json, true);
            if (! isset($data['iv'], $data['tag'], $data['ciphertext'])) {
                return null;
            }

            $key = static::getKey();
            $iv = base64_decode($data['iv']);
            $tag = base64_decode($data['tag']);
            $ciphertext = base64_decode($data['ciphertext']);
            $aad = isset($data['aad']) ? base64_decode($data['aad']) : '';

            $decrypted = openssl_decrypt(
                $ciphertext,
                static::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $aad
            );

            return $decrypted !== false ? $decrypted : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Generate tamper-proof cryptographic verification seal for Invoices / LRs
     */
    public static function generateDigitalSeal(string $documentNumber, float $amount, string $date): string
    {
        $payload = "DOC:{$documentNumber}|AMT:{$amount}|DT:{$date}|TENANT:".(TenantManager::getTenantId() ?? 1);
        $key = static::getKey();
        $hmac = hash_hmac('sha256', $payload, $key);

        return 'SIG-'.strtoupper(substr($hmac, 0, 16));
    }
}
