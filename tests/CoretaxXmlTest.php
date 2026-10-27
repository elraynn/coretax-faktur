<?php

namespace Elrayn\CoretaxFaktur\Tests;

use Elrayn\CoretaxFaktur\Buyer;
use Elrayn\CoretaxFaktur\CoretaxXml;
use Elrayn\CoretaxFaktur\Faktur;
use Elrayn\CoretaxFaktur\Item;
use Elrayn\CoretaxFaktur\Unit;
use PHPUnit\Framework\TestCase;

class CoretaxXmlTest extends TestCase
{
    public function testOutputMatchesTheDjpConverterLayout(): void
    {
        $xml = CoretaxXml::make('1090000000002325')
            ->add(
                Faktur::make('01/10/2026', Buyer::npwp('1090000000002365', 'CV. CONTOH JAYA', 'Jl. Contoh Raya Rt 01 Rw 02  '))
                    ->priceIncludesVat()
                    ->add(Item::goods('Kartu Remi (1 krt = 2 grs)', 100, 1000000)->discountPercent(3)),
                Faktur::make('2026-10-01', Buyer::detect('000', null, 'TOKO CONTOH', 'Jl. Contoh No. 2'))
                    ->reference('INV/2026/10/0002')
                    ->add(Item::goods('Sabun & Sampo', 2, 15000, Unit::LUSIN))
            )
            ->toString();

        $expected = "\xEF\xBB\xBF" . <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<TaxInvoiceBulk xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <TIN>1090000000002325</TIN>
  <ListOfTaxInvoice>
    <TaxInvoice>
      <TaxInvoiceDate>2026-10-01</TaxInvoiceDate>
      <TaxInvoiceOpt>Normal</TaxInvoiceOpt>
      <TrxCode>04</TrxCode>
      <AddInfo/>
      <CustomDoc/>
      <CustomDocMonthYear/>
      <RefDesc/>
      <FacilityStamp/>
      <SellerIDTKU>1090000000002325000000</SellerIDTKU>
      <BuyerTin>1090000000002365</BuyerTin>
      <BuyerDocument>TIN</BuyerDocument>
      <BuyerCountry>IDN</BuyerCountry>
      <BuyerDocumentNumber>-</BuyerDocumentNumber>
      <BuyerName>CV. CONTOH JAYA</BuyerName>
      <BuyerAdress>Jl. Contoh Raya Rt 01 Rw 02</BuyerAdress>
      <BuyerEmail/>
      <BuyerIDTKU>1090000000002365000000</BuyerIDTKU>
      <ListOfGoodService>
        <GoodService>
          <Opt>A</Opt>
          <Code>000000</Code>
          <Name>Kartu Remi (1 krt = 2 grs)</Name>
          <Unit>UM.0021</Unit>
          <Price>900900.9</Price>
          <Qty>100</Qty>
          <TotalDiscount>2702702.7</TotalDiscount>
          <TaxBase>87387387.3</TaxBase>
          <OtherTaxBase>80105105.03</OtherTaxBase>
          <VATRate>12</VATRate>
          <VAT>9612613</VAT>
          <STLGRate>0</STLGRate>
          <STLG>0</STLG>
        </GoodService>
      </ListOfGoodService>
    </TaxInvoice>
    <TaxInvoice>
      <TaxInvoiceDate>2026-10-01</TaxInvoiceDate>
      <TaxInvoiceOpt>Normal</TaxInvoiceOpt>
      <TrxCode>04</TrxCode>
      <AddInfo/>
      <CustomDoc/>
      <CustomDocMonthYear/>
      <RefDesc>INV/2026/10/0002</RefDesc>
      <FacilityStamp/>
      <SellerIDTKU>1090000000002325000000</SellerIDTKU>
      <BuyerTin>0000000000000000</BuyerTin>
      <BuyerDocument>Other ID</BuyerDocument>
      <BuyerCountry>IDN</BuyerCountry>
      <BuyerDocumentNumber>OtherID</BuyerDocumentNumber>
      <BuyerName>TOKO CONTOH</BuyerName>
      <BuyerAdress>Jl. Contoh No. 2</BuyerAdress>
      <BuyerEmail/>
      <BuyerIDTKU>000000</BuyerIDTKU>
      <ListOfGoodService>
        <GoodService>
          <Opt>A</Opt>
          <Code>000000</Code>
          <Name>Sabun &amp; Sampo</Name>
          <Unit>UM.0017</Unit>
          <Price>15000</Price>
          <Qty>2</Qty>
          <TotalDiscount>0</TotalDiscount>
          <TaxBase>30000</TaxBase>
          <OtherTaxBase>27500</OtherTaxBase>
          <VATRate>12</VATRate>
          <VAT>3300</VAT>
          <STLGRate>0</STLGRate>
          <STLG>0</STLG>
        </GoodService>
      </ListOfGoodService>
    </TaxInvoice>
  </ListOfTaxInvoice>
</TaxInvoiceBulk>

XML;

        $this->assertSame($expected, $xml);
    }

    public function testBomCanBeLeftOut(): void
    {
        $xml = CoretaxXml::make('1090000000002325')
            ->withoutBom()
            ->add(Faktur::make('2026-10-01', Buyer::other('X', 'Toko', 'Jl. A'))->add(Item::goods('Barang', 1, 1000)))
            ->toString();

        $this->assertStringStartsWith('<?xml', $xml);
    }
}
