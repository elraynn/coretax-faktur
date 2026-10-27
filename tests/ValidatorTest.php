<?php

namespace Elrayn\CoretaxFaktur\Tests;

use Elrayn\CoretaxFaktur\Buyer;
use Elrayn\CoretaxFaktur\CoretaxXml;
use Elrayn\CoretaxFaktur\Faktur;
use Elrayn\CoretaxFaktur\Issue;
use Elrayn\CoretaxFaktur\Item;
use Elrayn\CoretaxFaktur\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    protected function xml(Faktur $faktur): CoretaxXml
    {
        return CoretaxXml::make('1090000000002325')->add($faktur);
    }

    protected function valid(?Buyer $buyer = null): Faktur
    {
        return Faktur::make('2025-10-01', $buyer ?? Buyer::npwp('1090000000002365', 'CV Contoh', 'Jl. Contoh 1'))
            ->add(Item::goods('Barang', 1, 10000));
    }

    protected function fields(array $issues, string $level): array
    {
        return array_values(array_map(function (Issue $issue) {
            return $issue->field;
        }, array_filter($issues, function (Issue $issue) use ($level) {
            return $issue->level === $level;
        })));
    }

    public function testValidFakturHasNoIssues(): void
    {
        $this->assertSame([], $this->xml($this->valid())->validate());
    }

    public function testTinBuyerWithZeroNpwpIsAnError(): void
    {
        $issues = $this->xml($this->valid(Buyer::npwp('000000000000000', 'Toko', 'Jl. A')))->validate();

        $this->assertSame(['BuyerTin'], $this->fields($issues, Issue::ERROR));
    }

    public function testShortNitkuIsAnError(): void
    {
        $issues = $this->xml($this->valid(Buyer::npwp('1090000000002365', 'Toko', 'Jl. A', '109000000000'))) ->validate();

        $this->assertSame(['BuyerIDTKU'], $this->fields($issues, Issue::ERROR));
    }

    public function testFacilityCodesNeedAddInfoAndStamp(): void
    {
        $issues = $this->xml($this->valid()->trxCode('07'))->validate();
        $this->assertSame(['AddInfo', 'FacilityStamp'], $this->fields($issues, Issue::ERROR));

        $issues = $this->xml($this->valid()->trxCode('07')->addInfo('TD.00502')->facilityStamp('TD.01102'))->validate();
        $this->assertSame([], $issues);
    }

    public function testUnknownUnitAndCodeAreErrors(): void
    {
        $faktur = Faktur::make('2025-10-01', Buyer::other('OtherID', 'Toko', 'Jl. A'))
            ->add(Item::goods('Barang', 1, 10000, 'PCS')->code('123'));

        $this->assertSame(['Unit', 'Code'], $this->fields($this->xml($faktur)->validate(), Issue::ERROR));
    }

    public function testDiscountAboveGrossIsAnError(): void
    {
        $faktur = Faktur::make('2025-10-01', Buyer::other('OtherID', 'Toko', 'Jl. A'))
            ->add(Item::goods('Barang', 1, 10000)->discount(20000));

        $this->assertSame(['TotalDiscount'], $this->fields($this->xml($faktur)->validate(), Issue::ERROR));
    }

    public function testOneRupiahVatDifferenceIsOnlyAWarning(): void
    {
        // Banker's rounding gives 3910, half-up 3911. Fakturs like this have
        // been imported without complaint, so it doesn't block.
        $faktur = $this->valid()->add(Item::goods('Barang', 1, 35550)->amounts(35550, 32587.5, 3910));
        $issues = $this->xml($faktur)->validate();

        $this->assertSame([], $this->fields($issues, Issue::ERROR));
        $this->assertSame(['VAT'], $this->fields($issues, Issue::WARNING));
    }

    public function testWrongVatIsAnErrorOnlyForStrictTrxCodes(): void
    {
        $item = function () {
            return Item::goods('Barang', 1, 100000)->amounts(100000, 91666.67, 5000);
        };

        $this->assertSame(['VAT'], $this->fields($this->xml(Faktur::make('2025-10-01', Buyer::other('X', 'Toko', 'Jl. A'))->add($item()))->validate(), Issue::ERROR));

        $faktur = Faktur::make('2025-10-01', Buyer::other('X', 'Toko', 'Jl. A'))->trxCode('10')->otherTaxBaseRatio(11, 12)->add($item());
        $this->assertSame([], $this->fields($this->xml($faktur)->validate(), Issue::ERROR));
    }

    public function testToStringRefusesInvalidXml(): void
    {
        $this->expectException(ValidationException::class);

        $this->xml($this->valid(Buyer::npwp('000', 'Toko', 'Jl. A')))->toString();
    }

    public function testImpossibleDateIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Faktur::make('31/02/2025', Buyer::other('X', 'Toko', 'Jl. A'));
    }

    public function testAmbiguousDateFormatIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Faktur::make('02-03-2025', Buyer::other('X', 'Toko', 'Jl. A'));
    }
}
