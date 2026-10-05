<?php

use SilverShop\VatCompliance\Extension\InvoiceVatExtension;
use SilverShop\VatCompliance\Extension\TaxLineExtension;

/**
 * The invoicing integrations only apply when silvershop/invoicing is installed (it owns the
 * ShopInvoiceLine / ShopCreditMemoLine / ShopInvoice classes). Guarding with class_exists() keeps
 * silvershop/vat-compliance installable without invoicing — the SiteConfig "Shipping tax class" setting
 * and the reverse-charge modifier still work; only the document integration is skipped.
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

if (class_exists(\SilverShop\Invoicing\ShopInvoice::class)) {
    \SilverShop\Invoicing\ShopInvoice::add_extension(InvoiceVatExtension::class);
}
