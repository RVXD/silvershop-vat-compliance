<?php

namespace SilverShop\VatCompliance\Extension;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Tags a shipping modifier's snapshot line with the configured shipping tax rate.
 *
 * Applied to silvershop/invoicing's ShopInvoiceLine + ShopCreditMemoLine (only when invoicing is
 * installed — see the module's _config.php). The snapshotter fires `updateSnapshotLine($source, $order)`
 * on each line as it is frozen; when $source is one of the configured shipping modifiers and a shipping
 * tax class is set on SiteConfig, the line's TaxRate is set so snapshotTaxSummary() includes the
 * shipping charge in the per-rate VAT breakdown.
 *
 * @extends Extension<\SilverStripe\ORM\DataObject>
 */
class TaxLineExtension extends Extension
{
    /**
     * Modifier classes treated as "shipping" (their line is eligible for the shipping tax rate).
     * Shops with a custom shipping modifier add its class here via config.
     */
    private static array $shipping_modifier_classes = [
        'SilverShop\Shipping\ShippingFrameworkModifier',
    ];

    /**
     * @param object $source the OrderItem or OrderModifier this line was snapshotted from
     * @param object $order   the source Order (unused; part of the extend signature)
     */
    public function updateSnapshotLine($source, $order): void
    {
        if (!is_object($source)) {
            return;
        }

        $shippingClasses = (array) Config::inst()->get(static::class, 'shipping_modifier_classes');
        $isShipping = false;
        foreach ($shippingClasses as $class) {
            if ($source instanceof $class) {
                $isShipping = true;
                break;
            }
        }
        if (!$isShipping) {
            return;
        }

        $config = SiteConfig::current_site_config();
        $rate = ($config && $config->hasMethod('getShippingTaxRate')) ? $config->getShippingTaxRate() : null;

        if ($rate !== null && $rate > 0) {
            $this->getOwner()->TaxRate = $rate;
        }
    }
}
