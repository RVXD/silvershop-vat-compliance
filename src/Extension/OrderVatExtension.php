<?php

namespace SilverShop\VatCompliance\Extension;

use SilverShop\VatCompliance\Vat\ReverseChargeRule;
use SilverShop\VatCompliance\Vat\VatNumber;
use SilverShop\VatCompliance\Vat\ViesValidator;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Stores the buyer's VAT number and its VIES result on the order, as an audit snapshot, and records whether
 * the order qualifies for EU reverse charge. This slice only captures + decides; applying the 0% to the
 * total is a later slice.
 *
 * @extends Extension<\SilverShop\Model\Order>
 * @property string $VATNumber
 * @property string $VATNumberStatus
 * @property string $VATNumberName
 * @property string $VATNumberRequestIdentifier
 * @property bool $ReverseCharge
 */
class OrderVatExtension extends Extension
{
    /** Optional explicit seller country (VAT prefix). Falls back to store-profile's StoreCountryCode. */
    private static ?string $seller_country = null;

    private static array $db = [
        'VATNumber' => 'Varchar(20)',
        'VATNumberStatus' => 'Varchar(12)',
        'VATNumberName' => 'Varchar(255)',
        'VATNumberRequestIdentifier' => 'Varchar(255)',
        'VATNumberCheckedAt' => 'Datetime',
        'ReverseCharge' => 'Boolean',
    ];

    /**
     * Validate a VAT number against VIES and store the result + reverse-charge decision on the order.
     * Empty input clears the stored VAT data. Does not write() — the caller persists the order.
     */
    public function applyVatNumber(string $raw): void
    {
        $owner = $this->getOwner();
        $raw = trim($raw);

        if ($raw === '') {
            $owner->VATNumber = '';
            $owner->VATNumberStatus = '';
            $owner->VATNumberName = '';
            $owner->VATNumberRequestIdentifier = '';
            $owner->VATNumberCheckedAt = null;
            $owner->ReverseCharge = false;
            return;
        }

        $validator = Injector::inst()->get(ViesValidator::class);
        $result = $validator->validate($raw);
        $vat = VatNumber::parse($raw);

        $owner->VATNumber = $vat ? $vat->full() : strtoupper($raw);
        $owner->VATNumberStatus = $result->status->value;
        $owner->VATNumberName = $result->name ?? '';
        $owner->VATNumberRequestIdentifier = $result->requestIdentifier ?? '';
        $owner->VATNumberCheckedAt = $result->checkedAt?->format('Y-m-d H:i:s');

        $sellerCountry = $this->sellerCountry();
        $owner->ReverseCharge = $vat !== null && $sellerCountry !== null
            && (new ReverseChargeRule())->applies($vat, $result, $sellerCountry);
    }

    /**
     * The seller's country (VAT prefix): the module's seller_country override, else store-profile's
     * StoreCountryCode on SiteConfig, else null (reverse charge can't be decided without it).
     */
    public function sellerCountry(): ?string
    {
        $override = (string) Config::inst()->get(static::class, 'seller_country');
        if ($override !== '') {
            return strtoupper($override);
        }

        $config = SiteConfig::current_site_config();
        $code = $config->hasField('StoreCountryCode') ? (string) $config->StoreCountryCode : '';

        return $code !== '' ? strtoupper($code) : null;
    }

    protected function updateCMSFields(FieldList $fields): void
    {
        $owner = $this->getOwner();
        $fields->removeByName([
            'VATNumber',
            'VATNumberStatus',
            'VATNumberName',
            'VATNumberRequestIdentifier',
            'VATNumberCheckedAt',
            'ReverseCharge',
        ]);

        if (!$owner->VATNumber) {
            return; // nothing captured — don't clutter the order admin
        }

        $fields->addFieldsToTab('Root.VAT', [
            ReadonlyField::create('VATNumber', _t(self::class . '.VATNumber', 'VAT number')),
            ReadonlyField::create('VATNumberStatus', _t(self::class . '.Status', 'VIES status')),
            ReadonlyField::create('VATNumberName', _t(self::class . '.Name', 'VIES-registered name')),
            ReadonlyField::create(
                'ReverseChargeNice',
                _t(self::class . '.ReverseCharge', 'Reverse charge'),
                $owner->ReverseCharge ? 'Yes' : 'No'
            ),
            ReadonlyField::create('VATNumberCheckedAt', _t(self::class . '.CheckedAt', 'Checked at')),
            ReadonlyField::create('VATNumberRequestIdentifier', _t(self::class . '.RequestId', 'VIES request ID')),
        ]);
    }
}
