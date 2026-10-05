<?php

namespace SilverShop\VatCompliance\Vat;

/**
 * The EU intra-community reverse-charge decision. Reverse charge applies when the buyer gives a VAT number
 * that VIES confirmed valid, both the buyer's and the seller's countries are in the EU VAT area, and they
 * are different member states. Domestic B2B (same country) is normal VAT; an unconfirmed (Unavailable) or
 * invalid number never qualifies.
 *
 * Pure decision logic — the caller supplies the seller country (e.g. from silvershop/store-profile) and the
 * already-validated buyer number. Applying the 0% to the order total is a separate concern (later slice).
 */
final class ReverseChargeRule
{
    public function applies(VatNumber $buyer, ViesResult $result, string $sellerCountry): bool
    {
        if (!$result->isValid()) {
            return false;
        }

        $sellerCountry = strtoupper(trim($sellerCountry));

        return $buyer->isEu()
            && Eu::isMember($sellerCountry)
            && $buyer->country() !== $sellerCountry;
    }
}
