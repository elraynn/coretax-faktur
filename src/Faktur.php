<?php

namespace Elrayn\CoretaxFaktur;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class Faktur
{
    protected DateTimeImmutable $date;
    protected Buyer $buyer;
    protected string $trxCode = '04';
    protected int $vatRate = 12;
    protected ?array $otherTaxBaseRatio = null;
    protected bool $priceIncludesVat = false;
    protected string $addInfo = '';
    protected string $facilityStamp = '';
    protected string $customDoc = '';
    protected string $customDocMonthYear = '';
    protected string $reference = '';
    protected ?string $sellerIdtku = null;
    /** @var Item[] */
    protected array $items = [];

    /**
     * @param DateTimeInterface|string $date "Y-m-d", "Y-m-d H:i:s" or "d/m/Y".
     *        Nothing else is guessed at: "03/04/2025" read as March 4th
     *        instead of April 3rd is how fakturs end up in the wrong masa pajak.
     */
    public function __construct($date, Buyer $buyer)
    {
        $this->date = self::parseDate($date);
        $this->buyer = $buyer;
    }

    /**
     * @param DateTimeInterface|string $date
     */
    public static function make($date, Buyer $buyer): self
    {
        return new self($date, $buyer);
    }

    /**
     * Kode transaksi, "01" to "10". Defaults to "04" (DPP Nilai Lain), the
     * usual code for ordinary sales since PPN went to 12% with DPP 11/12.
     *
     * @param int|string $code
     */
    public function trxCode($code): self
    {
        $this->trxCode = str_pad(trim((string) $code), 2, '0', STR_PAD_LEFT);
        return $this;
    }

    public function vatRate(int $rate): self
    {
        $this->vatRate = $rate;
        return $this;
    }

    // DPP Nilai Lain = DPP * numerator / denominator. Defaults to 11/12 for
    // kode transaksi 04 and 1/1 (same as DPP) for everything else.
    public function otherTaxBaseRatio(int $numerator, int $denominator): self
    {
        $this->otherTaxBaseRatio = [$numerator, $denominator];
        return $this;
    }

    // Item prices (and fixed discounts) include PPN; the library takes it out
    // before computing DPP, e.g. 1,110,000 -> 1,000,000 at 12% x 11/12.
    public function priceIncludesVat(bool $includes = true): self
    {
        $this->priceIncludesVat = $includes;
        return $this;
    }

    // Keterangan tambahan (TD.005xx), required for kode transaksi 07 and 08.
    public function addInfo(string $code): self
    {
        $this->addInfo = trim($code);
        return $this;
    }

    // Cap fasilitas (TD.011xx), required for kode transaksi 07 and 08.
    public function facilityStamp(string $code): self
    {
        $this->facilityStamp = trim($code);
        return $this;
    }

    /**
     * Dokumen pendukung, e.g. the nomor aju of a BC 4.0 SPPB, and its period.
     *
     * @param DateTimeInterface|string|null $period "MMYYYY" or the document date
     */
    public function customDoc(string $number, $period = null): self
    {
        $this->customDoc = trim($number);
        $this->customDocMonthYear = $period instanceof DateTimeInterface ? $period->format('mY') : trim((string) $period);
        return $this;
    }

    // Free text (RefDesc). Handy for your own invoice number.
    public function reference(string $reference): self
    {
        $this->reference = trim($reference);
        return $this;
    }

    // Only needed when this faktur is issued by a branch (cabang) whose NITKU
    // differs from the one given to CoretaxXml.
    public function sellerIdtku(string $nitku): self
    {
        $this->sellerIdtku = preg_replace('/\D/', '', $nitku);
        return $this;
    }

    public function add(Item ...$items): self
    {
        foreach ($items as $item) {
            $this->items[] = $item;
        }
        return $this;
    }

    /**
     * Each item with its amounts worked out, all in cents (qty in
     * hundredths): price, qty, discount, taxBase, otherTaxBase, vat, stlg.
     */
    public function lines(string $rounding = Rounding::HALF_UP): array
    {
        return array_map(function (Item $item) use ($rounding) {
            return $this->line($item, $rounding);
        }, $this->items);
    }

    protected function line(Item $item, string $rounding): array
    {
        [$numerator, $denominator] = $this->otherTaxBaseRatioValue();
        $price = $item->price();
        $discount = $item->discountValue();

        if ($this->priceIncludesVat) {
            $divisor = 100 * $denominator + $this->vatRate * $numerator;
            $price = Money::divide($price * 100 * $denominator, $divisor, $rounding);
            $discount = Money::divide($discount * 100 * $denominator, $divisor, $rounding);
        }

        $gross = Money::divide($price * $item->qty(), 100, $rounding);

        $remaining = $gross;
        foreach ($item->discountPercents() as $percent) {
            $remaining -= Money::divide($remaining * $percent, 10000, $rounding);
        }
        $discount += $gross - $remaining;

        $line = ['price' => $price, 'qty' => $item->qty(), 'discount' => $discount];

        if ($item->fixedAmounts() !== null) {
            return $line + $item->fixedAmounts();
        }

        $taxBase = $gross - $discount;
        $otherTaxBase = Money::divide($taxBase * $numerator, $denominator, $rounding);

        // PPN and PPnBM are whole rupiah: cents * rate / 100 gives cents,
        // the extra / 100 rounds that to rupiah.
        return $line + [
            'taxBase' => $taxBase,
            'otherTaxBase' => $otherTaxBase,
            'vat' => Money::divide($otherTaxBase * $this->vatRate, 10000, $rounding) * 100,
            'stlg' => Money::divide($otherTaxBase * $item->stlgRateValue(), 10000, $rounding) * 100,
        ];
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    public function buyer(): Buyer
    {
        return $this->buyer;
    }

    public function trxCodeValue(): string
    {
        return $this->trxCode;
    }

    public function vatRateValue(): int
    {
        return $this->vatRate;
    }

    public function otherTaxBaseRatioValue(): array
    {
        return $this->otherTaxBaseRatio ?? ($this->trxCode === '04' ? [11, 12] : [1, 1]);
    }

    public function addInfoValue(): string
    {
        return $this->addInfo;
    }

    public function facilityStampValue(): string
    {
        return $this->facilityStamp;
    }

    public function customDocValue(): string
    {
        return $this->customDoc;
    }

    public function customDocMonthYear(): string
    {
        return $this->customDocMonthYear;
    }

    public function referenceValue(): string
    {
        return $this->reference;
    }

    public function sellerIdtkuValue(): ?string
    {
        return $this->sellerIdtku;
    }

    /** @return Item[] */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @param DateTimeInterface|string $date
     */
    protected static function parseDate($date): DateTimeImmutable
    {
        if ($date instanceof DateTimeInterface) {
            return new DateTimeImmutable($date->format('Y-m-d'));
        }

        foreach (['!Y-m-d', '!Y-m-d H:i:s', '!d/m/Y'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, trim($date));
            $errors = DateTimeImmutable::getLastErrors();

            // createFromFormat happily turns 31/02/2025 into March 3rd and
            // only reports it as a warning.
            if ($parsed !== false && ($errors === false || $errors['warning_count'] === 0)) {
                return $parsed;
            }
        }

        throw new InvalidArgumentException("Unrecognized faktur date '{$date}', use Y-m-d or d/m/Y");
    }
}
