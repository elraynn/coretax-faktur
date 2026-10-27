<?php

namespace Elrayn\CoretaxFaktur\Tests;

use Elrayn\CoretaxFaktur\Buyer;
use Elrayn\CoretaxFaktur\Faktur;
use Elrayn\CoretaxFaktur\Item;
use Elrayn\CoretaxFaktur\Money;
use Elrayn\CoretaxFaktur\Rounding;
use Elrayn\CoretaxFaktur\Unit;
use PHPUnit\Framework\TestCase;

class CalculationTest extends TestCase
{
    protected function faktur(): Faktur
    {
        return Faktur::make('2025-04-08', Buyer::npwp('1090000000002365', 'PT Contoh', 'Jl. Contoh 1'));
    }

    protected function formatted(array $line): array
    {
        return array_map([Money::class, 'format'], $line);
    }

    public function testDjpTemplateExampleWithDppNilaiLainAndPpnbm(): void
    {
        // Row 1 of the DetailFaktur sheet in the DJP template v1.6.1.
        $faktur = $this->faktur()->add(Item::goods('Barang ABC', 10, 12000000, Unit::WET_TON)->stlgRate(10));

        $this->assertSame([
            'price' => '12000000',
            'qty' => '10',
            'discount' => '0',
            'taxBase' => '120000000',
            'otherTaxBase' => '110000000',
            'vat' => '13200000',
            'stlg' => '11000000',
        ], $this->formatted($faktur->lines()[0]));
    }

    public function testPriceIncludingVatWithPercentDiscount(): void
    {
        // A real line from an ERP export that was imported into Coretax:
        // Rp1,000,000 including PPN, qty 100, 3% discount.
        $faktur = $this->faktur()->priceIncludesVat()->add(Item::goods('Kartu Remi', 100, 1000000)->discountPercent(3));

        $this->assertSame([
            'price' => '900900.9',
            'qty' => '100',
            'discount' => '2702702.7',
            'taxBase' => '87387387.3',
            'otherTaxBase' => '80105105.03',
            'vat' => '9612613',
            'stlg' => '0',
        ], $this->formatted($faktur->lines()[0]));
    }

    public function testCascadingPercentDiscountsApplyToWhatIsLeft(): void
    {
        $faktur = $this->faktur()->add(Item::goods('Barang', 1, 1000000)->discountPercent(10, 5));

        // 1,000,000 - 10% = 900,000; - 5% = 855,000
        $this->assertSame('145000', Money::format($faktur->lines()[0]['discount']));
        $this->assertSame('855000', Money::format($faktur->lines()[0]['taxBase']));
    }

    public function testEmptyPercentDiscountsAreSkipped(): void
    {
        $faktur = $this->faktur()->add(Item::goods('Barang', 1, 1000000)->discountPercent(10, null, ''));

        $this->assertSame('100000', Money::format($faktur->lines()[0]['discount']));
    }

    public function testRoundingModeDecidesExactHalfRupiah(): void
    {
        // DPP 35,550 -> DPP Nilai Lain 32,587.5 -> PPN 3,910.5
        $faktur = $this->faktur()->add(Item::goods('Perangkap Tikus', 1, 35550));

        $this->assertSame('3911', Money::format($faktur->lines(Rounding::HALF_UP)[0]['vat']));
        $this->assertSame('3910', Money::format($faktur->lines(Rounding::HALF_EVEN)[0]['vat']));
    }

    public function testOtherTrxCodesUseFullDppByDefault(): void
    {
        $faktur = $this->faktur()->trxCode('01')->add(Item::goods('Barang', 2, 50000));
        $line = $faktur->lines()[0];

        $this->assertSame($line['taxBase'], $line['otherTaxBase']);
        $this->assertSame('12000', Money::format($line['vat']));
    }

    public function testNumericTrxCodeIsZeroPadded(): void
    {
        $this->assertSame('04', $this->faktur()->trxCode(4)->trxCodeValue());
    }

    public function testFixedAmountsAreUsedAsGiven(): void
    {
        $faktur = $this->faktur()->add(Item::goods('Barang', 1, 35550)->amounts(35550, 32587.5, 3910));

        $this->assertSame('3910', Money::format($faktur->lines()[0]['vat']));
    }
}
