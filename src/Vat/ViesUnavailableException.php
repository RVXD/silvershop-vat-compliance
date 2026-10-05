<?php

namespace SilverShop\VatCompliance\Vat;

use RuntimeException;

/**
 * Thrown by a ViesLookup when VIES could not be reached or returned an unusable response (timeout,
 * transport error, non-200, malformed body). The validator maps this to ViesStatus::Unavailable — never
 * to Invalid — so an outage can't silently grant or deny a reverse charge.
 */
class ViesUnavailableException extends RuntimeException
{
}
