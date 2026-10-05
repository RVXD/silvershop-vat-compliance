<?php

namespace SilverShop\VatCompliance\Tests\Vat;

use PHPUnit\Framework\TestCase;
use SilverShop\VatCompliance\Vat\ReverseChargeRule;
use SilverShop\VatCompliance\Vat\VatNumber;
use SilverShop\VatCompliance\Vat\ViesResult;
use SilverShop\VatCompliance\Vat\ViesStatus;

class ReverseChargeRuleTest extends TestCase
{
    private ReverseChargeRule $rule;

    protected function setUp(): void
    {
        $this->rule = new ReverseChargeRule();
    }

    private function viesResult(ViesStatus $status, string $full): ViesResult
    {
        return new ViesResult($status, $full);
    }

    public function testValidForeignEuNumberReverseCharges(): void
    {
        $buyer = VatNumber::parse('DE123456789');
        $this->assertTrue(
            $this->rule->applies($buyer, $this->viesResult(ViesStatus::Valid, 'DE123456789'), 'NL')
        );
    }

    public function testDomesticB2bIsNormalVat(): void
    {
        $buyer = VatNumber::parse('NL801234567B01');
        $this->assertFalse(
            $this->rule->applies($buyer, $this->viesResult(ViesStatus::Valid, 'NL801234567B01'), 'NL'),
            'same member state = domestic, no reverse charge'
        );
    }

    public function testInvalidNumberNeverReverseCharges(): void
    {
        $buyer = VatNumber::parse('DE000');
        $this->assertFalse(
            $this->rule->applies($buyer, $this->viesResult(ViesStatus::Invalid, 'DE000'), 'NL')
        );
    }

    public function testUnavailableNeverReverseCharges(): void
    {
        $buyer = VatNumber::parse('DE123456789');
        $this->assertFalse(
            $this->rule->applies($buyer, $this->viesResult(ViesStatus::Unavailable, 'DE123456789'), 'NL'),
            'an unconfirmed number must not grant the 0% benefit'
        );
    }

    public function testNonEuBuyerDoesNotReverseCharge(): void
    {
        $buyer = VatNumber::parse('CH123456');
        $this->assertFalse(
            $this->rule->applies($buyer, $this->viesResult(ViesStatus::Valid, 'CH123456'), 'NL')
        );
    }

    public function testNonEuSellerDoesNotReverseCharge(): void
    {
        $buyer = VatNumber::parse('DE123456789');
        $this->assertFalse(
            $this->rule->applies($buyer, $this->viesResult(ViesStatus::Valid, 'DE123456789'), 'US')
        );
    }
}
