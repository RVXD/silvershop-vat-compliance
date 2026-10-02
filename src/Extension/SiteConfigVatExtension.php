<?php

namespace SilverShop\VatCompliance\Extension;

use SilverShop\Model\TaxClass;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\ORM\DataObject;

/**
 * Adds a global "Shipping tax class" setting to SiteConfig. Picking a core {@see TaxClass} makes
 * delivery/shipping charges taxable at that class's rate; leaving it empty keeps shipping untaxed
 * (SilverShop's default). The rate is consumed by {@see TaxLineExtension}, which tags the shipping
 * modifier's invoice line so silvershop/invoicing folds it into the per-rate VAT breakdown.
 *
 * Region-neutral: this is just "tax shipping at rate X". EU-specific compliance (VIES, reverse-charge,
 * OSS) arrives in later versions of silvershop/vat-compliance.
 *
 * @extends Extension<\SilverStripe\SiteConfig\SiteConfig>
 */
class SiteConfigVatExtension extends Extension
{
    private static array $has_one = [
        'ShippingTaxClass' => TaxClass::class,
    ];

    protected function updateCMSFields(FieldList $fields): void
    {
        $classes = TaxClass::get();

        $dropdown = DropdownField::create(
            'ShippingTaxClassID',
            _t(self::class . '.ShippingTaxClass', 'Shipping tax class'),
            $classes->map('ID', 'Title')
        )
            ->setEmptyString(_t(self::class . '.NoShippingTax', '(shipping not taxed)'))
            ->setDescription(_t(
                self::class . '.ShippingTaxClassDesc',
                'Tax delivery/shipping charges at this rate. Leave empty to keep shipping untaxed.'
            ));

        // Root.Main is the only tab guaranteed to exist as a leaf Tab on SiteConfig regardless of which
        // shop modules have promoted Root.Shop to a TabSet, so the field lands reliably there.
        $fields->addFieldsToTab('Root.Main', [
            HeaderField::create('VatComplianceHeader', _t(self::class . '.VAT', 'VAT')),
            $dropdown,
        ]);
    }

    /**
     * The configured shipping tax rate (e.g. 0.21), or null when shipping is not taxed.
     */
    public function getShippingTaxRate(): ?float
    {
        /** @var DataObject&\SilverStripe\SiteConfig\SiteConfig $owner */
        $owner = $this->getOwner();
        $class = $owner->ShippingTaxClass();

        if (!$class || !$class->exists()) {
            return null;
        }

        $rate = (float) $class->Rate;

        return $rate > 0 ? $rate : null;
    }
}
