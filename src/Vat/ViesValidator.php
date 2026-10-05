<?php

namespace SilverShop\VatCompliance\Vat;

use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;

/**
 * Validates a VAT number against VIES, applying the cross-cutting policy the transport layer shouldn't own:
 *  - normalise the input (via VatNumber); malformed input is Invalid without a network call;
 *  - cache Valid/Invalid results (not Unavailable) so repeat checks and VIES rate limits don't bite;
 *  - map transport failures to ViesStatus::Unavailable (never Invalid).
 *
 * Dependencies are constructor-injected (no service locator) so it unit-tests with a fake ViesLookup and
 * no cache. In SilverStripe it is wired via Injector (see _config/vat-compliance.yml).
 */
class ViesValidator
{
    public function __construct(
        private readonly ViesLookup $lookup,
        private readonly ?CacheInterface $cache = null,
        private readonly int $cacheTtlSeconds = 86400,
    ) {
    }

    public function validate(string $raw): ViesResult
    {
        $vat = VatNumber::parse($raw);
        if ($vat === null) {
            return new ViesResult(ViesStatus::Invalid, trim($raw), checkedAt: new DateTimeImmutable());
        }

        $cacheKey = 'vies_' . $vat->full();
        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if ($cached instanceof ViesResult) {
                return $cached;
            }
        }

        try {
            $response = $this->lookup->check($vat->country(), $vat->number());
        } catch (ViesUnavailableException) {
            // Unconfirmed — not cached, so a later check can succeed once VIES is back.
            return new ViesResult(ViesStatus::Unavailable, $vat->full(), checkedAt: new DateTimeImmutable());
        }

        $result = new ViesResult(
            $response['valid'] ? ViesStatus::Valid : ViesStatus::Invalid,
            $vat->full(),
            $response['name'] ?? null,
            $response['address'] ?? null,
            $response['requestIdentifier'] ?? null,
            new DateTimeImmutable(),
        );

        $this->cache?->set($cacheKey, $result, $this->cacheTtlSeconds);

        return $result;
    }
}
