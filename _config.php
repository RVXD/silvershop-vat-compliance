<?php

use SilverShop\VatCompliance\Extension\TaxLineExtension;

/**
 * The shipping-VAT line tagging only applies when silvershop/invoicing is installed (it owns the
 * ShopInvoiceLine / ShopCreditMemoLine classes that build the per-rate VAT breakdown). Guarding with
 * class_exists() keeps silvershop/vat-compliance installable without invoicing — the SiteConfig
 * "Shipping tax class" setting still appears; only the breakdown integration is skipped.
 */
$lineClasses = [
    \SilverShop\Invoicing\ShopInvoiceLine::class,
    \SilverShop\Invoicing\ShopCreditMemoLine::class,
];

foreach ($lineClasses as $lineClass) {
    if (class_exists($lineClass)) {
        $lineClass::add_extension(TaxLineExtension::class);
    }
}
