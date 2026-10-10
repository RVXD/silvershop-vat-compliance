<?php

declare(strict_types=1);

namespace SilverShop\VatCompliance\Report;

use SilverShop\Invoicing\ShopInvoiceLine;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Reports\Report;

/**
 * VAT collected grouped by tax rate for a period, read from the per-rate VAT breakdown that
 * silvershop/invoicing freezes onto issued invoices (its Kind='Tax' snapshot lines). Prices are
 * VAT-inclusive, so each tax line's Amount is the VAT for that rate; the taxable base (net) and the
 * gross are back-calculated from the rate (net = VAT / rate, gross = net + VAT). Deriving from the
 * frozen VAT keeps the figures consistent with the invoice — the Tax line already encodes the
 * discount apportionment and rounding snapshotTaxSummary() applied.
 *
 * Invoicing-guarded: the data lives on silvershop/invoicing's ShopInvoiceLine. Without invoicing the
 * report returns nothing (and _config.php hides it from the report list), so vat-compliance stays
 * installable standalone.
 */
class VatByRateReport extends Report
{
    public function title()
    {
        return _t(__CLASS__ . '.TITLE', 'VAT by rate');
    }

    public function description()
    {
        return _t(__CLASS__ . '.DESC', 'VAT collected per tax rate for a period, from issued invoices.');
    }

    public function group()
    {
        return _t('SilverShop\\Reports.GROUP', 'Shop');
    }

    public function sort()
    {
        return 330;
    }

    public function parameterFields()
    {
        return FieldList::create(
            DateField::create('StartDate', _t(__CLASS__ . '.Start', 'From (invoice date)')),
            DateField::create('EndDate', _t(__CLASS__ . '.End', 'To (invoice date)'))
        );
    }

    public function sourceRecords($params = null)
    {
        // Invoicing owns the invoice-line table; without it there is nothing to report.
        if (!class_exists(ShopInvoiceLine::class)) {
            return ArrayList::create();
        }

        $query = SQLSelect::create();
        $query->setSelect([
            'TaxRate' => '"il"."TaxRate"',
            'Vat' => 'SUM("il"."Amount")',
        ]);
        $query->setFrom('"SilverShop_InvoiceLine" AS "il"');
        $query->addInnerJoin('SilverShop_Invoice', '"i"."ID" = "il"."InvoiceID"', 'i');
        // Only the per-rate VAT summary lines, and only on issued (immutable) invoices.
        $query->addWhere(['"il"."Kind" = ?' => 'Tax']);
        $query->addWhere(['"i"."Status" = ?' => 'Issued']);
        $query->addWhere('"il"."TaxRate" > 0');

        if (!empty($params['StartDate'])) {
            $query->addWhere(['"i"."Issued" >= ?' => $params['StartDate'] . ' 00:00:00']);
        }
        if (!empty($params['EndDate'])) {
            $query->addWhere(['"i"."Issued" <= ?' => $params['EndDate'] . ' 23:59:59']);
        }

        $query->setGroupBy('"il"."TaxRate"');
        $query->setOrderBy('"il"."TaxRate"', 'ASC');

        $list = ArrayList::create();
        foreach ($query->execute() as $row) {
            $rate = (float) $row['TaxRate'];
            if ($rate <= 0) {
                continue;
            }
            $vat = (float) $row['Vat'];
            $net = $vat / $rate;   // VAT-inclusive pricing: net = VAT / rate
            $gross = $net + $vat;

            $pct = rtrim(rtrim(number_format($rate * 100, 2, '.', ''), '0'), '.');

            $list->push(ArrayData::create([
                'Rate' => $pct . '%',
                'Net' => number_format($net, 2),
                'Vat' => number_format($vat, 2),
                'Gross' => number_format($gross, 2),
            ]));
        }

        return $list;
    }

    public function columns()
    {
        return [
            'Rate' => _t(__CLASS__ . '.ColRate', 'Rate'),
            'Net' => _t(__CLASS__ . '.ColNet', 'Net'),
            'Vat' => _t(__CLASS__ . '.ColVat', 'VAT'),
            'Gross' => _t(__CLASS__ . '.ColGross', 'Gross'),
        ];
    }
}
