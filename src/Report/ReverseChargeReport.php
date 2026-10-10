<?php

declare(strict_types=1);

namespace SilverShop\VatCompliance\Report;

use SilverShop\Model\Order;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Reports\Report;

/**
 * Orders flagged for EU reverse charge (B2B intra-community supply) for a period, from the VAT snapshot
 * OrderVatExtension stores on the order: the ReverseCharge flag and the buyer's VIES-validated VAT
 * number. Placed orders only (carts excluded), newest first.
 */
class ReverseChargeReport extends Report
{
    public function title()
    {
        return _t(__CLASS__ . '.TITLE', 'Reverse-charged orders');
    }

    public function description()
    {
        return _t(__CLASS__ . '.DESC', 'B2B intra-EU orders with VAT reverse-charged, for a period.');
    }

    public function group()
    {
        return _t('SilverShop\\Reports.GROUP', 'Shop');
    }

    public function sort()
    {
        return 331;
    }

    public function parameterFields()
    {
        return FieldList::create(
            DateField::create('StartDate', _t(__CLASS__ . '.Start', 'From (order date)')),
            DateField::create('EndDate', _t(__CLASS__ . '.End', 'To (order date)'))
        );
    }

    public function sourceRecords($params = null)
    {
        $statuses = array_values((array) Order::config()->get('placed_status'));
        if (empty($statuses)) {
            return ArrayList::create();
        }

        $query = SQLSelect::create();
        $query->setSelect([
            'Reference' => '"o"."Reference"',
            'ID' => '"o"."ID"',
            'FirstName' => '"o"."FirstName"',
            'Surname' => '"o"."Surname"',
            'VATNumber' => '"o"."VATNumber"',
            'Total' => '"o"."Total"',
        ]);
        $query->setFrom('"SilverShop_Order" AS "o"');
        $query->addWhere('"o"."ReverseCharge" = 1');

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $query->addWhere(['"o"."Status" IN (' . $placeholders . ')' => $statuses]);

        if (!empty($params['StartDate'])) {
            $query->addWhere(['"o"."Placed" >= ?' => $params['StartDate'] . ' 00:00:00']);
        }
        if (!empty($params['EndDate'])) {
            $query->addWhere(['"o"."Placed" <= ?' => $params['EndDate'] . ' 23:59:59']);
        }

        $query->setOrderBy('"o"."Placed"', 'DESC');

        $list = ArrayList::create();
        foreach ($query->execute() as $row) {
            $reference = (string) ($row['Reference'] ?? '');
            $name = trim(((string) ($row['FirstName'] ?? '')) . ' ' . ((string) ($row['Surname'] ?? '')));

            $list->push(ArrayData::create([
                'Order' => $reference !== '' ? $reference : ('#' . $row['ID']),
                'Customer' => $name,
                'VATNumber' => (string) ($row['VATNumber'] ?? ''),
                'Total' => number_format((float) $row['Total'], 2),
            ]));
        }

        return $list;
    }

    public function columns()
    {
        return [
            'Order' => _t(__CLASS__ . '.ColOrder', 'Order'),
            'Customer' => _t(__CLASS__ . '.ColCustomer', 'Customer'),
            'VATNumber' => _t(__CLASS__ . '.ColVatNumber', 'VAT number'),
            'Total' => _t(__CLASS__ . '.ColTotal', 'Total'),
        ];
    }
}
