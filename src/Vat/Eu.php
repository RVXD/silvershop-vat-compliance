<?php

namespace SilverShop\VatCompliance\Vat;

/**
 * EU VAT territory helper. The codes are VAT prefixes, not ISO country codes: Greece is EL (not GR) and
 * Northern Ireland is XI (post-Brexit, goods only) — both as used by VIES. GB is intentionally absent
 * (Great Britain is outside the EU VAT area).
 */
final class Eu
{
    /** The 27 member-state VAT prefixes plus XI (Northern Ireland, goods). */
    public const MEMBER_STATES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'EL', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'XI',
    ];

    public static function isMember(string $country): bool
    {
        return in_array(strtoupper(trim($country)), self::MEMBER_STATES, true);
    }
}
