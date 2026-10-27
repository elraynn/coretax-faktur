<?php

namespace Elrayn\CoretaxFaktur\Tests;

use Elrayn\CoretaxFaktur\Buyer;
use PHPUnit\Framework\TestCase;

class BuyerTest extends TestCase
{
    public function testFormattedFifteenDigitNpwpIsNormalized(): void
    {
        $buyer = Buyer::npwp('01.234.567.8-901.000', 'PT Contoh', 'Jl. Contoh 1');

        $this->assertSame(Buyer::TIN, $buyer->document());
        $this->assertSame('0012345678901000', $buyer->tin());
        $this->assertSame('0012345678901000000000', $buyer->idtku());
        $this->assertSame('-', $buyer->documentNumberValue());
    }

    public function testBranchNitkuCanBeGiven(): void
    {
        $buyer = Buyer::npwp('0012345678901000', 'PT Contoh', 'Jl. Contoh 1', '0012345678901000000001');

        $this->assertSame('0012345678901000000001', $buyer->idtku());
    }

    public function testNonTinBuyersGetDjpFillerValues(): void
    {
        $buyer = Buyer::nik('3174061502560010', 'Budi', 'Jl. Contoh 1');

        $this->assertSame(Buyer::NATIONAL_ID, $buyer->document());
        $this->assertSame('0000000000000000', $buyer->tin());
        $this->assertSame('3174061502560010', $buyer->documentNumberValue());
        $this->assertSame('000000', $buyer->idtku());
    }

    public function testDetectTreatsZeroPlaceholdersAsMissing(): void
    {
        // Both seen in a real ERP export, sent as TIN buyers.
        $this->assertSame(Buyer::OTHER_ID, Buyer::detect('000000000000000', null, 'Toko A', 'Jl. A')->document());
        $this->assertSame(Buyer::OTHER_ID, Buyer::detect('000', '-', 'Toko B', 'Jl. B')->document());
    }

    public function testDetectPrefersNpwpThenNik(): void
    {
        $this->assertSame(Buyer::TIN, Buyer::detect('1090000000002365', '3174061502560010', 'A', 'Jl. A')->document());
        $this->assertSame(Buyer::NATIONAL_ID, Buyer::detect('-', '3174061502560010', 'A', 'Jl. A')->document());
    }

    public function testAddressWhitespaceIsCollapsed(): void
    {
        $buyer = Buyer::other('OtherID', 'Toko', "Jl. Contoh No: 2\n Bogor  ");

        $this->assertSame('Jl. Contoh No: 2 Bogor', $buyer->address());
    }
}
