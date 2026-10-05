<?php

namespace SilverShop\VatCompliance\Tests\Vat;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SilverShop\VatCompliance\Vat\VatNumber;

class VatNumberTest extends TestCase
{
    public function testParsesAndNormalises(): void
    {
        $vat = VatNumber::parse('nl 8012.34.567 b01');
        $this->assertNotNull($vat);
        $this->assertSame('NL', $vat->country());
        $this->assertSame('801234567B01', $vat->number());
        $this->assertSame('NL801234567B01', $vat->full());
        $this->assertTrue($vat->isEu());
    }

    public function testNonEuPrefixParsesButIsNotEu(): void
    {
        $vat = VatNumber::parse('CH123456');
        $this->assertNotNull($vat);
        $this->assertSame('CH', $vat->country());
        $this->assertFalse($vat->isEu());
    }

    #[DataProvider('unparseable')]
    public function testRejectsUnparseable(string $raw): void
    {
        $this->assertNull(VatNumber::parse($raw));
    }

    public static function unparseable(): array
    {
        return [
            'empty' => [''],
            'too short' => ['NL'],
            'no national part' => ['NL '],
            'digits only, no country prefix' => ['12345678'],
            'one letter prefix' => ['N123456'],
        ];
    }
}
