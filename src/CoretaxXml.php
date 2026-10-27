<?php

namespace Elrayn\CoretaxFaktur;

use DOMDocument;
use DOMElement;

// Builds the TaxInvoiceBulk XML that Coretax imports for Faktur Pajak
// Keluaran. Element names, order and formatting follow what DJP's own
// Converter e-Faktur Coretax (v1.6) produces, including its spelling of
// "BuyerAdress".
class CoretaxXml
{
    protected string $sellerTin;
    protected string $sellerIdtku;
    /** @var Faktur[] */
    protected array $fakturs = [];
    protected string $rounding = Rounding::HALF_UP;
    protected bool $bom = true;

    // $sellerIdtku defaults to the NPWP + "000000" (head office).
    public function __construct(string $sellerNpwp, ?string $sellerIdtku = null)
    {
        $this->sellerTin = Buyer::normalizeNpwp($sellerNpwp);
        $this->sellerIdtku = $sellerIdtku !== null ? preg_replace('/\D/', '', $sellerIdtku) : $this->sellerTin . '000000';
    }

    public static function make(string $sellerNpwp, ?string $sellerIdtku = null): self
    {
        return new self($sellerNpwp, $sellerIdtku);
    }

    public function add(Faktur ...$fakturs): self
    {
        foreach ($fakturs as $faktur) {
            $this->fakturs[] = $faktur;
        }
        return $this;
    }

    public function rounding(string $rounding): self
    {
        $this->rounding = $rounding;
        return $this;
    }

    // The DJP converter writes a UTF-8 BOM; this leaves it out.
    public function withoutBom(): self
    {
        $this->bom = false;
        return $this;
    }

    /** @return Issue[] */
    public function validate(): array
    {
        return Validator::check($this->sellerTin, $this->sellerIdtku, $this->fakturs, $this->rounding);
    }

    /** @return Issue[] */
    public function errors(): array
    {
        return array_values(array_filter($this->validate(), function (Issue $issue) {
            return $issue->isError();
        }));
    }

    /**
     * @throws ValidationException when validate() finds errors. Warnings
     *         don't block; check validate() yourself if you want to show them.
     */
    public function toString(): string
    {
        $errors = $this->errors();
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('TaxInvoiceBulk');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $doc->appendChild($root);

        $this->append($root, 'TIN', $this->sellerTin);
        $list = $this->append($root, 'ListOfTaxInvoice');

        foreach ($this->fakturs as $faktur) {
            $this->appendFaktur($list, $faktur);
        }

        return ($this->bom ? "\xEF\xBB\xBF" : '') . $doc->saveXML();
    }

    public function save(string $path): void
    {
        file_put_contents($path, $this->toString());
    }

    // Builds the XML before sending any header, so a validation error
    // doesn't leave the browser with a half-sent download.
    public function download(string $filename = 'coretax_faktur_keluaran.xml'): void
    {
        $xml = $this->toString();

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($xml));
        echo $xml;
    }

    protected function appendFaktur(DOMElement $list, Faktur $faktur): void
    {
        $buyer = $faktur->buyer();
        $node = $this->append($list, 'TaxInvoice');

        $this->append($node, 'TaxInvoiceDate', $faktur->date()->format('Y-m-d'));
        $this->append($node, 'TaxInvoiceOpt', 'Normal');
        $this->append($node, 'TrxCode', $faktur->trxCodeValue());
        $this->append($node, 'AddInfo', $faktur->addInfoValue());
        $this->append($node, 'CustomDoc', $faktur->customDocValue());
        $this->append($node, 'CustomDocMonthYear', $faktur->customDocMonthYear());
        $this->append($node, 'RefDesc', $faktur->referenceValue());
        $this->append($node, 'FacilityStamp', $faktur->facilityStampValue());
        $this->append($node, 'SellerIDTKU', $faktur->sellerIdtkuValue() ?? $this->sellerIdtku);
        $this->append($node, 'BuyerTin', $buyer->tin());
        $this->append($node, 'BuyerDocument', $buyer->document());
        $this->append($node, 'BuyerCountry', $buyer->country());
        $this->append($node, 'BuyerDocumentNumber', $buyer->documentNumberValue());
        $this->append($node, 'BuyerName', $buyer->name());
        $this->append($node, 'BuyerAdress', $buyer->address());
        $this->append($node, 'BuyerEmail', $buyer->emailValue());
        $this->append($node, 'BuyerIDTKU', $buyer->idtku());

        $goods = $this->append($node, 'ListOfGoodService');
        $items = $faktur->items();

        foreach ($faktur->lines($this->rounding) as $i => $line) {
            $item = $items[$i];
            $good = $this->append($goods, 'GoodService');

            $this->append($good, 'Opt', $item->opt());
            $this->append($good, 'Code', $item->codeValue());
            $this->append($good, 'Name', $item->name());
            $this->append($good, 'Unit', $item->unit());
            $this->append($good, 'Price', Money::format($line['price']));
            $this->append($good, 'Qty', Money::format($line['qty']));
            $this->append($good, 'TotalDiscount', Money::format($line['discount']));
            $this->append($good, 'TaxBase', Money::format($line['taxBase']));
            $this->append($good, 'OtherTaxBase', Money::format($line['otherTaxBase']));
            $this->append($good, 'VATRate', (string) $faktur->vatRateValue());
            $this->append($good, 'VAT', Money::format($line['vat']));
            $this->append($good, 'STLGRate', (string) $item->stlgRateValue());
            $this->append($good, 'STLG', Money::format($line['stlg']));
        }
    }

    // Empty values come out as <Tag/>, like the DJP converter writes them.
    protected function append(DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $element = $parent->ownerDocument->createElement($name);
        if ($value !== null && $value !== '') {
            $element->appendChild($parent->ownerDocument->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }
}
