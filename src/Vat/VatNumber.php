<?php

namespace SilverShop\VatCompliance\Vat;

/**
 * A parsed, normalised EU VAT number: a two-letter country prefix + the national number, uppercased with
 * all separators stripped (e.g. "nl 8012.34.567 b01" -> country NL, number 801234567B01).
 */
final class VatNumber
{
    private function __construct(
        private readonly string $country,
        private readonly string $number,
    ) {
    }

    /**
     * Parse raw input. Returns null when it cannot be a VAT number (no two-letter prefix, or no national
     * part). Does not check the prefix is a real country — use isEu()/Eu for that.
     */
    public static function parse(string $raw): ?self
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $raw));
        if (strlen($clean) < 3) {
            return null;
        }

        $country = substr($clean, 0, 2);
        $number = substr($clean, 2);

        if (!ctype_alpha($country) || $number === '') {
            return null;
        }

        return new self($country, $number);
    }

    public function country(): string
    {
        return $this->country;
    }

    public function number(): string
    {
        return $this->number;
    }

    /** The full normalised number, prefix included (e.g. "NL801234567B01"). */
    public function full(): string
    {
        return $this->country . $this->number;
    }

    public function isEu(): bool
    {
        return Eu::isMember($this->country);
    }
}
