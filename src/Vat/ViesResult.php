<?php

namespace SilverShop\VatCompliance\Vat;

use DateTimeImmutable;

/**
 * Immutable result of validating a VAT number against VIES. For Valid results VIES may also return the
 * registered name/address and a requestIdentifier (keep the latter as proof the check was made).
 */
final class ViesResult
{
    public function __construct(
        public readonly ViesStatus $status,
        public readonly string $vatNumber,
        public readonly ?string $name = null,
        public readonly ?string $address = null,
        public readonly ?string $requestIdentifier = null,
        public readonly ?DateTimeImmutable $checkedAt = null,
    ) {
    }

    public function isValid(): bool
    {
        return $this->status === ViesStatus::Valid;
    }

    public function isUnavailable(): bool
    {
        return $this->status === ViesStatus::Unavailable;
    }
}
