<?php

namespace SilverShop\VatCompliance\Checkout;

use SilverShop\Checkout\Component\CheckoutComponentNamespaced;
use SilverShop\Checkout\Component\Notes;
use SilverShop\Checkout\SinglePageCheckoutComponentConfig;
use SilverShop\Model\Order;
use SilverStripe\Model\List\ArrayList;

/**
 * The default single-page checkout plus the VAT-number field, inserted just before the Notes field.
 * Wired in via an Injector override of SinglePageCheckoutComponentConfig (see _config/vat-compliance.yml),
 * so shops get VAT capture out of the box; remove that override (or re-point it) to opt out.
 *
 * insertBefore() in the parent matches on the concrete class, but single-page checkout wraps every
 * component in CheckoutComponentNamespaced, so the ordering is done here by unwrapping the proxy.
 */
class VatAwareSinglePageCheckoutComponentConfig extends SinglePageCheckoutComponentConfig
{
    public function __construct(Order $order)
    {
        parent::__construct($order);

        $vat = VatNumberCheckout::create();
        if ($this->namespaced) {
            $vat = CheckoutComponentNamespaced::create($vat);
        }

        $reordered = ArrayList::create();
        $inserted = false;
        foreach ($this->getComponents() as $component) {
            if (!$inserted && $this->isNotes($component)) {
                $reordered->push($vat);
                $inserted = true;
            }
            $reordered->push($component);
        }
        if (!$inserted) {
            $reordered->push($vat);
        }

        $this->components = $reordered;
    }

    private function isNotes(object $component): bool
    {
        if ($component instanceof CheckoutComponentNamespaced) {
            return $component->Proxy() instanceof Notes;
        }
        return $component instanceof Notes;
    }
}
