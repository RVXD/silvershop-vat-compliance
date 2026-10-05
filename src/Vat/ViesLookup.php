<?php

namespace SilverShop\VatCompliance\Vat;

/**
 * Transport contract for a single VIES check. Implementations do the network call only; normalisation,
 * caching and soft-fail policy live in ViesValidator. Injecting this interface makes the validator
 * testable without hitting the live VIES service.
 */
interface ViesLookup
{
    /**
     * @param string $countryCode two-letter VAT prefix (e.g. "NL", "EL")
     * @param string $number      the national part, without the prefix
     * @return array{valid: bool, name?: ?string, address?: ?string, requestIdentifier?: ?string}
     * @throws ViesUnavailableException if VIES cannot be reached or returns an unusable response
     */
    public function check(string $countryCode, string $number): array;
}
