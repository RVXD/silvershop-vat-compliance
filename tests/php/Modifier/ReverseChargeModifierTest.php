<?php

namespace SilverShop\VatCompliance\Tests\Modifier;

use SilverShop\Model\Modifiers\OrderModifier;
use SilverShop\Model\Order;
use SilverShop\Model\OrderItem;
use SilverShop\VatCompliance\Modifier\ReverseChargeModifier;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Covers the reverse-charge money-math: the embedded (VAT-inclusive) VAT removed from a reverse-charged order,
 * including the order-level discount apportionment (regression cover for a bug previously caught by hand).
 *
 * Uses lightweight stand-in buyable/item DataObjects rather than a real Product so the fixture needs no CMS
 * publishing, which keeps the money-math test runnable in a headless (module-only) test run.
 */
class ReverseChargeModifierTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TestTaxedBuyable::class,
        TestTaxedOrderItem::class,
        TestDeductableModifier::class,
    ];

    private function orderWithTaxedItem(float $gross, float $rate): Order
    {
        $buyable = TestTaxedBuyable::create(['Price' => $gross, 'Rate' => $rate]);
        $buyable->write();

        $order = Order::create();
        $order->write();

        $item = TestTaxedOrderItem::create(['Quantity' => 1, 'BuyableStubID' => $buyable->ID, 'OrderID' => $order->ID]);
        $item->write();

        return $order;
    }

    public function testNoDeductionWhenNotReverseCharged(): void
    {
        $order = $this->orderWithTaxedItem(121.00, 0.21);
        $modifier = ReverseChargeModifier::create(['OrderID' => $order->ID]);

        $this->assertEqualsWithDelta(0.0, (float) $modifier->value(0), 0.001, 'nothing removed unless flagged');
    }

    public function testRemovesEmbeddedVatForReverseChargedOrder(): void
    {
        // Gross 121 @ 21% inclusive → net 100, VAT 21.
        $order = $this->orderWithTaxedItem(121.00, 0.21);
        $order->ReverseCharge = true;
        $order->write();

        $modifier = ReverseChargeModifier::create(['OrderID' => $order->ID]);

        $this->assertEqualsWithDelta(21.0, (float) $modifier->value(0), 0.01, 'removes the VAT embedded in the item');
    }

    public function testApportionsOrderLevelDiscountBeforeExtractingVat(): void
    {
        // Gross 121 @ 21%, less a 12.10 order-level discount → taxable 108.90, net 90.00, VAT 18.90.
        $order = $this->orderWithTaxedItem(121.00, 0.21);
        $order->ReverseCharge = true;
        $order->write();

        $discount = TestDeductableModifier::create(['OrderID' => $order->ID, 'Amount' => 12.10]);
        $discount->write();
        $order->Modifiers()->add($discount);

        $modifier = ReverseChargeModifier::create(['OrderID' => $order->ID]);

        $this->assertEqualsWithDelta(18.9, (float) $modifier->value(0), 0.01, 'discount reduces taxable base first');
    }
}

/**
 * A minimal buyable: carries a VAT-inclusive selling price and a tax rate, so an order line can be
 * priced and taxed without a CMS-published Product.
 */
class TestTaxedBuyable extends DataObject implements TestOnly
{
    private static string $table_name = 'SilverShop_TestTaxedBuyable';

    private static array $db = [
        'Price' => 'Currency',
        'Rate' => 'Decimal(6,4)',
    ];

    public function sellingPrice(): float
    {
        return (float) $this->Price;
    }

    public function getTaxRate(): ?float
    {
        return (float) $this->Rate;
    }
}

/**
 * An order line whose buyable is the lightweight stub above (via the buyable_relationship override).
 */
class TestTaxedOrderItem extends OrderItem implements TestOnly
{
    private static string $table_name = 'SilverShop_TestTaxedOrderItem';

    private static array $has_one = [
        'BuyableStub' => TestTaxedBuyable::class,
    ];

    private static string $buyable_relationship = 'BuyableStub';
}

/**
 * A stand-in order-level discount: a Deductable modifier with a fixed stored Amount, so the
 * reverse-charge modifier's discount apportionment can be exercised without a real discount engine.
 */
class TestDeductableModifier extends OrderModifier implements TestOnly
{
    private static string $table_name = 'SilverShop_TestDeductableModifier';

    private static array $defaults = [
        'Type' => 'Deductable',
    ];
}
