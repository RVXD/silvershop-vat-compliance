<?php

namespace SilverShop\VatCompliance\Vat;

/**
 * Outcome of a VIES check. Unavailable is distinct from Invalid: VIES (or a member-state database) was
 * unreachable, so the number is simply unconfirmed — callers must not grant a reverse-charge benefit on
 * Unavailable.
 */
enum ViesStatus: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Unavailable = 'unavailable';
}
