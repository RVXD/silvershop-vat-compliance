<?php

namespace SilverShop\VatCompliance\Vat;

/**
 * Default ViesLookup: the EU VIES REST API over curl, with a short timeout. Any transport problem,
 * non-200, or unparseable body raises ViesUnavailableException so the validator treats it as "unconfirmed"
 * rather than "invalid". Only the boolean "valid" plus optional name/address/requestIdentifier are read.
 *
 * Depends only on ext-curl (universally present in SilverStripe environments), so the module needs no HTTP
 * client dependency.
 */
class CurlViesLookup implements ViesLookup
{
    private const ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

    public function __construct(
        private readonly string $endpoint = self::ENDPOINT,
        private readonly int $timeoutSeconds = 5,
    ) {
    }

    public function check(string $countryCode, string $number): array
    {
        if (!function_exists('curl_init')) {
            throw new ViesUnavailableException('ext-curl is not available');
        }

        $payload = json_encode(['countryCode' => $countryCode, 'vatNumber' => $number]);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $error !== '') {
            throw new ViesUnavailableException('VIES request failed: ' . $error);
        }
        if ($httpCode !== 200) {
            throw new ViesUnavailableException('VIES returned HTTP ' . $httpCode);
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data) || !array_key_exists('valid', $data)) {
            throw new ViesUnavailableException('VIES returned an unparseable response');
        }

        return [
            'valid' => (bool) $data['valid'],
            'name' => isset($data['name']) ? trim((string) $data['name']) : null,
            'address' => isset($data['address']) ? trim((string) $data['address']) : null,
            'requestIdentifier' => isset($data['requestIdentifier']) ? (string) $data['requestIdentifier'] : null,
        ];
    }
}
