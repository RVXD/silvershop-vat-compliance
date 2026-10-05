<?php

namespace SilverShop\VatCompliance\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Freezes the reverse-charge facts onto an invoice at creation, for a compliant, immutable document:
 * the reverse-charge flag, the buyer's VAT number (from the order) and the seller's (from
 * store-profile). invoicing's Invoice.ss renders the "VAT reverse-charged" note + buyer VAT number
 * from these; the seller VAT number already shows in the invoice header.
 *
 * Applied to silvershop/invoicing's ShopInvoice only when invoicing is installed (see _config.php).
 *
 * @extends Extension<\SilverShop\Invoicing\ShopInvoice>
 * @property bool $ReverseCharge
 * @property string $BuyerVatNumber
 * @property string $SellerVatNumber
 */
class InvoiceVatExtension extends Extension
{
    private static array $db = [
        'ReverseCharge' => 'Boolean',
        'BuyerVatNumber' => 'Varchar(20)',
        'SellerVatNumber' => 'Varchar(50)',
    ];

    /**
     * invoicing fires this on the invoice (with its order) just before the invoice is written.
     *
     * @param object $order the source order
     * @param object|null $payment the payment, if any (unused)
     */
    public function updateDocumentFromOrder($order, $payment = null): void
    {
        if (!is_object($order) || !$order->ReverseCharge) {
            return;
        }

        $owner = $this->getOwner();
        $owner->ReverseCharge = true;
        $owner->BuyerVatNumber = (string) $order->VATNumber;

        $config = SiteConfig::current_site_config();
        $owner->SellerVatNumber = $config && $config->hasField('VatNumber') ? (string) $config->VatNumber : '';
    }

    /**
     * The legal note shown on a reverse-charged invoice (empty otherwise). Template: $ReverseChargeNote.
     */
    public function getReverseChargeNote(): string
    {
        if (!$this->getOwner()->ReverseCharge) {
            return '';
        }

        return _t(
            self::class . '.Note',
            'VAT reverse-charged — the customer accounts for VAT (EU intra-community supply, Art. 196 Directive 2006/112/EC).'
        );
    }
}
