<?php

use SilverShop\VatCompliance\Extension\InvoiceVatExtension;
use SilverShop\VatCompliance\Extension\TaxLineExtension;
use SilverShop\VatCompliance\Report\VatByRateReport;
use SilverStripe\Core\Config\Config;
use SilverStripe\Reports\Report;

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

// VatByRateReport reads silvershop/invoicing's per-rate VAT breakdown (its ShopInvoiceLine). Without
// invoicing there is no such data, so hide the report from the CMS report list — it also returns an
// empty list from sourceRecords(). Keeps vat-compliance installable standalone.
if (!class_exists(\SilverShop\Invoicing\ShopInvoiceLine::class)) {
    Config::modify()->merge(Report::class, 'excluded_reports', [VatByRateReport::class]);
}
