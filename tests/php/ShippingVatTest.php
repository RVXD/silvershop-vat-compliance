<?php

namespace SilverShop\VatCompliance\Tests;

use SilverShop\Model\TaxClass;
use SilverShop\VatCompliance\Extension\TaxLineExtension;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Covers the shipping-VAT v1 slice:
 *  - SiteConfig resolves the configured shipping tax class to a rate (core-only).
 *  - TaxLineExtension tags a shipping modifier's line with that rate, and leaves other lines alone.
 */
class ShippingVatTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        ShippingVatTestLine::class,
        ShippingVatTestShipping::class,
        ShippingVatTestOther::class,
    ];

    private function setShippingRate(float $rate): void
    {
        $class = TaxClass::create();
        $class->Title = 'Shipping ' . $rate;
        $class->Rate = $rate;
        $class->write();

        $config = SiteConfig::current_site_config();
        $config->ShippingTaxClassID = $class->ID;
        $config->write();
    }

    public function testShippingTaxRateFromSiteConfig(): void
    {
        $config = SiteConfig::current_site_config();
        $this->assertNull($config->getShippingTaxRate(), 'No class set -> shipping untaxed');

        $this->setShippingRate(0.21);
        $this->assertEqualsWithDelta(
            0.21,
            SiteConfig::current_site_config()->getShippingTaxRate(),
            0.0001,
            'Configured class rate is returned'
        );
    }

    public function testZeroRateClassCountsAsUntaxed(): void
    {
        $this->setShippingRate(0.0);
        $this->assertNull(
            SiteConfig::current_site_config()->getShippingTaxRate(),
            'A 0% shipping class means no shipping VAT line'
        );
    }

    public function testShippingLineTaggedWithConfiguredRate(): void
    {
        Config::modify()->set(
            TaxLineExtension::class,
            'shipping_modifier_classes',
            [ShippingVatTestShipping::class]
        );
        $this->setShippingRate(0.21);

        $line = ShippingVatTestLine::create();
        $line->updateSnapshotLine(ShippingVatTestShipping::create(), null);

        $this->assertEqualsWithDelta(0.21, (float) $line->TaxRate, 0.0001, 'Shipping line gets the configured rate');
    }

    public function testNonShippingLineUntouched(): void
    {
        Config::modify()->set(
            TaxLineExtension::class,
            'shipping_modifier_classes',
            [ShippingVatTestShipping::class]
        );
        $this->setShippingRate(0.21);

        $line = ShippingVatTestLine::create();
        $line->updateSnapshotLine(ShippingVatTestOther::create(), null);

        $this->assertEqualsWithDelta(0.0, (float) $line->TaxRate, 0.0001, 'A non-shipping source leaves TaxRate at 0');
    }

    public function testNoRateConfiguredLeavesLineUntouched(): void
    {
        Config::modify()->set(
            TaxLineExtension::class,
            'shipping_modifier_classes',
            [ShippingVatTestShipping::class]
        );
        // No shipping tax class set on SiteConfig.

        $line = ShippingVatTestLine::create();
        $line->updateSnapshotLine(ShippingVatTestShipping::create(), null);

        $this->assertEqualsWithDelta(0.0, (float) $line->TaxRate, 0.0001, 'No shipping class -> line stays untaxed');
    }
}

/**
 * Stands in for an invoicing ShopInvoiceLine/ShopCreditMemoLine so the extension can be tested without
 * a hard dependency on silvershop/invoicing.
 */
class ShippingVatTestLine extends DataObject implements TestOnly
{
    private static string $table_name = 'ShippingVatTestLine';

    private static array $db = [
        'TaxRate' => 'Decimal(6,4)',
    ];

    private static array $extensions = [
        TaxLineExtension::class,
    ];
}

/**
 * Stands in for a shipping OrderModifier (its class is listed in shipping_modifier_classes).
 */
class ShippingVatTestShipping extends DataObject implements TestOnly
{
    private static string $table_name = 'ShippingVatTestShipping';
}

/**
 * Stands in for a non-shipping modifier / order item (its class is NOT listed).
 */
class ShippingVatTestOther extends DataObject implements TestOnly
{
    private static string $table_name = 'ShippingVatTestOther';
}
