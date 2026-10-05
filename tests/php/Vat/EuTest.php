<?php

namespace SilverShop\VatCompliance\Tests\Vat;

use PHPUnit\Framework\TestCase;
use SilverShop\VatCompliance\Vat\Eu;

class EuTest extends TestCase
{
    public function testMemberStatesUseVatPrefixes(): void
    {
        $this->assertTrue(Eu::isMember('NL'));
        $this->assertTrue(Eu::isMember('DE'));
        $this->assertTrue(Eu::isMember('EL'), 'Greece is EL in VAT context');
        $this->assertTrue(Eu::isMember('XI'), 'Northern Ireland (goods)');
    }

    public function testNonMembers(): void
    {
        $this->assertFalse(Eu::isMember('GR'), 'GR is the ISO code, not the VAT prefix');
        $this->assertFalse(Eu::isMember('GB'), 'Great Britain is outside the EU VAT area');
        $this->assertFalse(Eu::isMember('US'));
        $this->assertFalse(Eu::isMember(''));
    }

    public function testCaseAndWhitespaceInsensitive(): void
    {
        $this->assertTrue(Eu::isMember('nl'));
        $this->assertTrue(Eu::isMember(' Fr '));
    }
}
