<?php

namespace SilverShop\VatCompliance\Checkout;

use SilverShop\Checkout\Component\CheckoutComponent;
use SilverShop\Model\Order;
use SilverShop\VatCompliance\Vat\VatNumber;
use SilverShop\VatCompliance\Vat\ViesStatus;
use SilverShop\VatCompliance\Vat\ViesValidator;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;

/**
 * Optional checkout field for the buyer's EU VAT number. On submit it hands the value to
 * Order::applyVatNumber() (from OrderVatExtension), which validates against VIES and records the
 * reverse-charge decision. Validation blocks only on a malformed number or one VIES actively rejects;
 * a VIES outage (Unavailable) is allowed through (soft-fail — no reverse charge granted).
 */
class VatNumberCheckout extends CheckoutComponent
{
    public function getFormFields(Order $order): FieldList
    {
        return FieldList::create(
            TextField::create('VATNumber', _t(self::class . '.VATNumber', 'VAT number (EU businesses)'))
                ->setDescription(_t(
                    self::class . '.Help',
                    'EU businesses: enter your VAT number to have VAT reverse-charged where applicable. '
                    . 'Leave blank otherwise.'
                ))
        );
    }

    public function getData(Order $order): array
    {
        return ['VATNumber' => $order->VATNumber];
    }

    public function validateData(Order $order, array $data): bool
    {
        $raw = trim((string) ($data['VATNumber'] ?? ''));
        if ($raw === '') {
            return true; // optional
        }

        if (VatNumber::parse($raw) === null) {
            $this->error(_t(self::class . '.Malformed', 'That does not look like a valid VAT number.'));
        }

        $result = Injector::inst()->get(ViesValidator::class)->validate($raw);
        if ($result->status === ViesStatus::Invalid) {
            $this->error(_t(
                self::class . '.NotRecognised',
                'VIES did not recognise this VAT number. Leave it blank if you are not a business.'
            ));
        }

        // Valid or Unavailable both pass; Unavailable simply won't grant a reverse charge.
        return true;
    }

    public function setData(Order $order, array $data): Order
    {
        $order->applyVatNumber((string) ($data['VATNumber'] ?? ''));
        $order->write();
        return $order;
    }

    /**
     * @throws ValidationException
     */
    private function error(string $message): void
    {
        $result = ValidationResult::create();
        $result->addError($message, 'VATNumber');
        throw ValidationException::create($result);
    }
}
