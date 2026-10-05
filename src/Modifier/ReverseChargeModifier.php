<?php

namespace SilverShop\VatCompliance\Modifier;

use SilverShop\Model\Modifiers\OrderModifier;
use SilverShop\Model\Order;
use SilverShop\VatCompliance\Extension\TaxLineExtension;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Removes the VAT embedded in a reverse-charged order so the buyer pays the net amount.
 *
 * The shop prices VAT-inclusive, so a reverse-charge B2B buyer must be billed ex-VAT. This Deductable
 * modifier subtracts the VAT contained in the taxed item lines (per their product tax rate) plus the
 * taxed shipping charge (v1's shipping tax class), but only when the order is flagged ReverseCharge
 * (set by OrderVatExtension from a VIES-validated foreign-EU VAT number). Otherwise it deducts nothing.
 *
 * Must run after the shipping modifier so the shipping charge is known — register it last in
 * Order.modifiers (this module appends it; a shop with a custom shipping modifier should keep it last).
 */
class ReverseChargeModifier extends OrderModifier
{
    private static string $table_name = 'SilverShop_ReverseChargeModifier';

    private static string $singular_name = 'VAT reverse charge';

    private static string $plural_name = 'VAT reverse charges';

    private static array $defaults = [
        'Type' => 'Deductable',
    ];

    public function value($incoming): int|float
    {
        $order = $this->Order();
        if (!$order || !$order->exists() || !$order->ReverseCharge) {
            return 0;
        }

        $precision = (int) Order::config()->get('rounding_precision');

        // Apportion any order-level discount across items by value, matching how silvershop/invoicing's
        // snapshotTaxSummary() computes the per-rate VAT — so the VAT removed equals the VAT the invoice drops.
        $items = $order->Items();
        $itemGross = 0.0;
        foreach ($items as $item) {
            $itemGross += (float) (string) $item->Total();
        }
        $discount = $this->orderDiscount($order);

        $vat = 0.0;
        foreach ($items as $item) {
            $rate = $this->itemRate($item);
            if ($rate <= 0) {
                continue;
            }
            $gross = (float) (string) $item->Total();
            $taxable = $itemGross > 0 ? $gross - ($gross / $itemGross) * $discount : $gross;
            $vat += $taxable - round($taxable / (1 + $rate), $precision);
        }

        $vat += $this->shippingVat($order, $precision);

        return round($vat, $precision);
    }

    /**
     * Sum of order-level discounts (Deductable modifiers other than this one), already calculated this
     * run because the reverse-charge modifier is registered last.
     */
    private function orderDiscount(Order $order): float
    {
        $discount = 0.0;
        foreach ($order->Modifiers() as $modifier) {
            if ($modifier->Type === 'Deductable' && !($modifier instanceof self)) {
                $discount += (float) $modifier->Amount;
            }
        }
        return $discount;
    }

    private function itemRate(DataObject $item): float
    {
        $buyable = $item->hasMethod('Buyable') ? $item->Buyable() : null;
        if ($buyable instanceof DataObject && $buyable->hasMethod('getTaxRate') && $buyable->getTaxRate() !== null) {
            return max(0.0, (float) $buyable->getTaxRate());
        }
        return 0.0;
    }

    private function shippingVat(Order $order, int $precision): float
    {
        $config = SiteConfig::current_site_config();
        $rate = $config->hasMethod('getShippingTaxRate') ? $config->getShippingTaxRate() : null;
        if (!$rate || $rate <= 0) {
            return 0.0;
        }

        $shippingClasses = (array) Config::inst()->get(TaxLineExtension::class, 'shipping_modifier_classes');
        $vat = 0.0;
        foreach ($order->Modifiers() as $modifier) {
            foreach ($shippingClasses as $class) {
                if ($class !== '' && $modifier instanceof $class) {
                    $gross = (float) $modifier->Amount;
                    if ($gross > 0) {
                        $vat += $gross - round($gross / (1 + $rate), $precision);
                    }
                    break;
                }
            }
        }

        return $vat;
    }

    public function getTableTitle(): string
    {
        return _t(self::class . '.TableTitle', 'VAT reverse-charged');
    }

    public function ShowInTable(): bool
    {
        return (float) $this->Amount > 0;
    }

    public function canRemove(): bool
    {
        return false;
    }
}
