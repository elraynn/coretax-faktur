<?php

namespace Elrayn\CoretaxFaktur;

// The pembeli block of a faktur. Coretax wants different filler values per
// ID type (16 zeros for BuyerTin, "000000" for BuyerIDTKU, ...), the named
// constructors take care of that so callers only pass what they actually have.
class Buyer
{
    const TIN = 'TIN';
    const NATIONAL_ID = 'National ID';
    const PASSPORT = 'Passport';
    const OTHER_ID = 'Other ID';

    protected string $document;
    protected string $tin;
    protected string $documentNumber;
    protected string $idtku;
    protected string $name;
    protected string $address;
    protected string $country = 'IDN';
    protected string $email = '';

    protected function __construct(string $document, string $tin, string $documentNumber, string $idtku, string $name, string $address)
    {
        $this->document = $document;
        $this->tin = $tin;
        $this->documentNumber = $documentNumber;
        $this->idtku = $idtku;
        $this->name = trim($name);
        $this->address = trim(preg_replace('/\s+/', ' ', $address));
    }

    /**
     * NPWP in any common formatting ("01.234.567.8-901.000", 15 or 16 digits).
     * A 15-digit NPWP gets the leading 0 Coretax expects. NITKU defaults to
     * the NPWP + "000000", i.e. the head office (pusat).
     */
    public static function npwp(string $npwp, string $name, string $address, ?string $nitku = null): self
    {
        $npwp = self::normalizeNpwp($npwp);

        return new self(self::TIN, $npwp, '-', $nitku !== null ? self::digits($nitku) : $npwp . '000000', $name, $address);
    }

    public static function nik(string $nik, string $name, string $address): self
    {
        return new self(self::NATIONAL_ID, str_repeat('0', 16), self::digits($nik), '000000', $name, $address);
    }

    public static function passport(string $number, string $country, string $name, string $address): self
    {
        $buyer = new self(self::PASSPORT, str_repeat('0', 16), trim($number), '000000', $name, $address);
        $buyer->country = strtoupper(trim($country));

        return $buyer;
    }

    public static function other(string $documentNumber, string $name, string $address): self
    {
        return new self(self::OTHER_ID, str_repeat('0', 16), trim($documentNumber), '000000', $name, $address);
    }

    /**
     * For ERP customer tables where NPWP and NIK are optional columns: uses
     * the NPWP if it looks real, else the NIK, else falls back to Other ID.
     * Empty values, "-" and all-zero placeholders all count as missing, so a
     * customer stored with NPWP "000000000000000" doesn't end up as a TIN
     * buyer with a bogus NPWP.
     */
    public static function detect(?string $npwp, ?string $nik, string $name, string $address, string $otherDocumentNumber = 'OtherID'): self
    {
        if (self::looksReal($npwp, [15, 16])) {
            return self::npwp($npwp, $name, $address);
        }

        if (self::looksReal($nik, [16])) {
            return self::nik($nik, $name, $address);
        }

        return self::other($otherDocumentNumber, $name, $address);
    }

    public function email(string $email): self
    {
        $this->email = trim($email);
        return $this;
    }

    // Some apps put the NPWP in BuyerDocumentNumber instead of the "-" the
    // DJP template asks for. Both are seen in the wild.
    public function documentNumber(string $documentNumber): self
    {
        $this->documentNumber = trim($documentNumber);
        return $this;
    }

    public function document(): string
    {
        return $this->document;
    }

    public function tin(): string
    {
        return $this->tin;
    }

    public function documentNumberValue(): string
    {
        return $this->documentNumber;
    }

    public function idtku(): string
    {
        return $this->idtku;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function address(): string
    {
        return $this->address;
    }

    public function country(): string
    {
        return $this->country;
    }

    public function emailValue(): string
    {
        return $this->email;
    }

    public static function normalizeNpwp(string $npwp): string
    {
        $npwp = self::digits($npwp);

        return strlen($npwp) === 15 ? '0' . $npwp : $npwp;
    }

    protected static function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    protected static function looksReal(?string $value, array $lengths): bool
    {
        $digits = self::digits((string) $value);

        return in_array(strlen($digits), $lengths, true) && trim($digits, '0') !== '';
    }
}
