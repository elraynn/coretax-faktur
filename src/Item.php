<?php

namespace Elrayn\CoretaxFaktur;

// One line of a faktur (GoodService). By default the tax amounts are computed
// from price, qty and discount; call amounts() to pass your own instead.
class Item
{
    protected string $opt;
    protected string $name;
    protected int $qty;
    protected int $price;
    protected string $unit;
    protected string $code = '000000';
    protected int $discount = 0;
    protected array $discountPercents = [];
    protected int $stlgRate = 0;
    protected ?array $amounts = null;

    /**
     * @param int|float|string $qty
     * @param int|float|string $price
     */
    protected function __construct(string $opt, string $name, $qty, $price, string $unit)
    {
        $this->opt = $opt;
        $this->name = trim($name);
        $this->qty = Money::toCents($qty);
        $this->price = Money::toCents($price);
        $this->unit = $unit;
    }

    /**
     * @param int|float|string $qty
     * @param int|float|string $price
     */
    public static function goods(string $name, $qty, $price, string $unit = Unit::PIECE): self
    {
        return new self('A', $name, $qty, $price, $unit);
    }

    /**
     * @param int|float|string $qty
     * @param int|float|string $price
     */
    public static function service(string $name, $qty, $price, string $unit = Unit::LAINNYA): self
    {
        return new self('B', $name, $qty, $price, $unit);
    }

    // Kode barang/jasa. "000000" (the default) is accepted for goods.
    public function code(string $code): self
    {
        $this->code = trim($code);
        return $this;
    }

    /**
     * Fixed discount for the whole line, in the same basis as the price
     * (so including PPN if the faktur uses priceIncludesVat()).
     *
     * @param int|float|string $amount
     */
    public function discount($amount): self
    {
        $this->discount = Money::toCents($amount);
        return $this;
    }

    /**
     * Cascading percent discounts, the disc1/disc2/disc3 columns many ERPs
     * have: each one applies to what's left after the previous. Applied
     * before any fixed discount(). Null and empty values are skipped, so
     * nullable disc columns can be passed straight through.
     *
     * @param int|float|string|null ...$percents
     */
    public function discountPercent(...$percents): self
    {
        $percents = array_filter($percents, function ($percent) {
            return $percent !== null && trim((string) $percent) !== '';
        });
        $this->discountPercents = array_values(array_map([Money::class, 'toCents'], $percents));
        return $this;
    }

    // Tarif PPnBM in percent, 0 (the default) when there is none.
    public function stlgRate(int $rate): self
    {
        $this->stlgRate = $rate;
        return $this;
    }

    /**
     * Use amounts your app already computed instead of letting the library
     * do the math. They are still checked by the validator.
     *
     * @param int|float|string $taxBase      DPP
     * @param int|float|string $otherTaxBase DPP Nilai Lain
     * @param int|float|string $vat          PPN
     * @param int|float|string $stlg         PPnBM
     */
    public function amounts($taxBase, $otherTaxBase, $vat, $stlg = 0): self
    {
        $this->amounts = [
            'taxBase' => Money::toCents($taxBase),
            'otherTaxBase' => Money::toCents($otherTaxBase),
            'vat' => Money::toCents($vat),
            'stlg' => Money::toCents($stlg),
        ];
        return $this;
    }

    public function opt(): string
    {
        return $this->opt;
    }

    public function name(): string
    {
        return $this->name;
    }

    // Qty in hundredths (Coretax allows 2 decimals).
    public function qty(): int
    {
        return $this->qty;
    }

    public function price(): int
    {
        return $this->price;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function codeValue(): string
    {
        return $this->code;
    }

    public function discountValue(): int
    {
        return $this->discount;
    }

    public function discountPercents(): array
    {
        return $this->discountPercents;
    }

    public function stlgRateValue(): int
    {
        return $this->stlgRate;
    }

    public function fixedAmounts(): ?array
    {
        return $this->amounts;
    }
}
