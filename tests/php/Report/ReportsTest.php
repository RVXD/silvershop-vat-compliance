<?php

declare(strict_types=1);

namespace SilverShop\VatCompliance\Tests\Report;

use SilverShop\Model\Order;
use SilverShop\VatCompliance\Report\ReverseChargeReport;
use SilverShop\VatCompliance\Report\VatByRateReport;
use SilverStripe\Dev\SapphireTest;

/**
 * Each VAT report instantiates, has a title, and its sourceRecords() runs cleanly on an empty DB —
 * which exercises the hand-written SQLSelect against the test database (SQLite in CI).
 *
 * VatByRateReport only returns data when silvershop/invoicing is installed; on an empty DB it returns
 * an empty list either way (guard when absent, zero rows when present), so the assertion holds in both
 * the standalone module build and the site install (where invoicing is present).
 */
class ReportsTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testAllReportsRunCleanly(): void
    {
        $classes = [
            VatByRateReport::class,
            ReverseChargeReport::class,
        ];

        foreach ($classes as $class) {
            $report = new $class();
            $this->assertNotEmpty((string) $report->title(), "{$class} has a title");
            $this->assertCount(0, $report->sourceRecords(), "{$class} runs cleanly with no data");
        }
    }

    /**
     * ReverseChargeReport lists placed orders flagged ReverseCharge (the field OrderVatExtension adds)
     * and leaves ordinary orders out. Reads only SilverShop_Order, so it needs no invoicing.
     */
    public function testReverseChargeReportListsFlaggedOrders(): void
    {
        // Placed, reverse-charged B2B order (a first write with a placed status skips the status
        // transition side-effects and auto-generates a Reference).
        $flagged = Order::create();
        $flagged->Status = 'Processing';
        $flagged->Placed = '2026-06-15 10:00:00';
        $flagged->FirstName = 'Acme';
        $flagged->Surname = 'BV';
        $flagged->VATNumber = 'NL123456789B01';
        $flagged->ReverseCharge = true;
        $flagged->Total = 100.00;
        $flagged->write();

        // Ordinary (domestic) placed order — must not appear.
        $plain = Order::create();
        $plain->Status = 'Processing';
        $plain->Placed = '2026-06-15 11:00:00';
        $plain->ReverseCharge = false;
        $plain->Total = 50.00;
        $plain->write();

        $records = (new ReverseChargeReport())->sourceRecords();
        $this->assertCount(1, $records, 'Only the reverse-charged order is listed');
        $this->assertContains('NL123456789B01', $records->column('VATNumber'), 'Buyer VAT number is reported');
    }
}
