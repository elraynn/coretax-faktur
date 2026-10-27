<?php

namespace Elrayn\CoretaxFaktur;

// The column rules from the "Keterangan" sheet of the DJP template v1.6.1,
// plus a few checks for mistakes that show up in real ERP data. Errors are
// things Coretax is documented to reject; warnings are things worth a look
// that are known to get through in practice (e.g. PPN off by one rupiah from
// a different rounding mode).
//
// Messages are in Indonesian since they usually end up in front of whoever
// does the tax admin, not the developer.
class Validator
{
    // Kode transaksi where PPN must be exactly tarif x DPP Nilai Lain.
    const STRICT_VAT_CODES = ['01', '04', '09'];

    /**
     * @param Faktur[] $fakturs
     * @return Issue[]
     */
    public static function check(string $sellerTin, string $sellerIdtku, array $fakturs, string $rounding = Rounding::HALF_UP): array
    {
        $issues = [];

        if (!preg_match('/^\d{16}$/', $sellerTin)) {
            $issues[] = new Issue(Issue::ERROR, null, null, 'TIN', "NPWP penjual harus 16 digit, didapat '{$sellerTin}'");
        }
        if (!preg_match('/^\d{22}$/', $sellerIdtku)) {
            $issues[] = new Issue(Issue::ERROR, null, null, 'SellerIDTKU', "NITKU penjual harus 22 digit, didapat '{$sellerIdtku}'");
        }
        if ($fakturs === []) {
            $issues[] = new Issue(Issue::ERROR, null, null, 'ListOfTaxInvoice', 'Belum ada faktur');
        }

        foreach (array_values($fakturs) as $index => $faktur) {
            array_push($issues, ...self::checkFaktur($faktur, $index + 1, $rounding));
        }

        return $issues;
    }

    /** @return Issue[] */
    protected static function checkFaktur(Faktur $faktur, int $n, string $rounding): array
    {
        $issues = [];
        $error = function (string $field, string $message) use (&$issues, $n) {
            $issues[] = new Issue(Issue::ERROR, $n, null, $field, $message);
        };
        $code = $faktur->trxCodeValue();

        if (!preg_match('/^(0[1-9]|10)$/', $code)) {
            $error('TrxCode', "Kode transaksi harus 01-10, didapat '{$code}'");
        }

        if (in_array($code, ['07', '08'], true)) {
            if (!preg_match('/^TD\.005\d\d$/', $faktur->addInfoValue())) {
                $error('AddInfo', "Keterangan tambahan (TD.005xx) wajib untuk kode transaksi {$code}");
            }
            if (!preg_match('/^TD\.011\d\d$/', $faktur->facilityStampValue())) {
                $error('FacilityStamp', "Cap fasilitas (TD.011xx) wajib untuk kode transaksi {$code}");
            }
        }

        $period = $faktur->customDocMonthYear();
        if ($period !== '' && !preg_match('/^(0[1-9]|1[0-2])\d{4}$/', $period)) {
            $error('CustomDocMonthYear', "Periode dokumen pendukung harus MMYYYY, didapat '{$period}'");
        }

        $sellerIdtku = $faktur->sellerIdtkuValue();
        if ($sellerIdtku !== null && !preg_match('/^\d{22}$/', $sellerIdtku)) {
            $error('SellerIDTKU', "NITKU penjual harus 22 digit, didapat '{$sellerIdtku}'");
        }

        array_push($issues, ...self::checkBuyer($faktur->buyer(), $n));

        if ($faktur->items() === []) {
            $error('ListOfGoodService', 'Faktur tidak punya barang/jasa');
        }

        $items = $faktur->items();
        foreach ($faktur->lines($rounding) as $i => $line) {
            array_push($issues, ...self::checkItem($faktur, $items[$i], $line, $n, $i + 1, $rounding));
        }

        return $issues;
    }

    /** @return Issue[] */
    protected static function checkBuyer(Buyer $buyer, int $n): array
    {
        $issues = [];
        $error = function (string $field, string $message) use (&$issues, $n) {
            $issues[] = new Issue(Issue::ERROR, $n, null, $field, $message);
        };

        switch ($buyer->document()) {
            case Buyer::TIN:
                if (!preg_match('/^\d{16}$/', $buyer->tin()) || trim($buyer->tin(), '0') === '') {
                    $error('BuyerTin', "NPWP pembeli '{$buyer->tin()}' tidak valid. Kalau pembeli tidak punya NPWP, pakai Buyer::nik(), Buyer::other() atau Buyer::detect()");
                }
                if (!preg_match('/^\d{22}$/', $buyer->idtku())) {
                    $error('BuyerIDTKU', "NITKU pembeli harus 22 digit, didapat '{$buyer->idtku()}'");
                } elseif (strpos($buyer->idtku(), $buyer->tin()) !== 0) {
                    $issues[] = new Issue(Issue::WARNING, $n, null, 'BuyerIDTKU', "NITKU pembeli '{$buyer->idtku()}' tidak diawali NPWP-nya '{$buyer->tin()}'");
                }
                break;

            case Buyer::NATIONAL_ID:
                if (!preg_match('/^\d{16}$/', $buyer->documentNumberValue()) || trim($buyer->documentNumberValue(), '0') === '') {
                    $error('BuyerDocumentNumber', "NIK pembeli harus 16 digit, didapat '{$buyer->documentNumberValue()}'");
                }
                break;

            case Buyer::PASSPORT:
                if (!preg_match('/^[A-Z]{3}$/', $buyer->country())) {
                    $error('BuyerCountry', "Kode negara harus 3 huruf (ISO 3166 alpha-3, mis. SGP), didapat '{$buyer->country()}'");
                }
                // fall through: a passport needs its number like any other document

            default:
                if ($buyer->documentNumberValue() === '') {
                    $error('BuyerDocumentNumber', 'Nomor dokumen pembeli wajib diisi');
                }
        }

        if ($buyer->name() === '') {
            $error('BuyerName', 'Nama pembeli wajib diisi');
        }
        if ($buyer->address() === '') {
            $error('BuyerAdress', 'Alamat pembeli wajib diisi');
        }
        if ($buyer->emailValue() !== '' && filter_var($buyer->emailValue(), FILTER_VALIDATE_EMAIL) === false) {
            $issues[] = new Issue(Issue::WARNING, $n, null, 'BuyerEmail', "Email pembeli '{$buyer->emailValue()}' sepertinya tidak valid");
        }

        return $issues;
    }

    /** @return Issue[] */
    protected static function checkItem(Faktur $faktur, Item $item, array $line, int $n, int $i, string $rounding): array
    {
        $issues = [];
        $add = function (string $level, string $field, string $message) use (&$issues, $n, $i) {
            $issues[] = new Issue($level, $n, $i, $field, $message);
        };

        if ($item->name() === '') {
            $add(Issue::ERROR, 'Name', 'Nama barang/jasa wajib diisi');
        }
        if (!Unit::isValid($item->unit())) {
            $add(Issue::ERROR, 'Unit', "Satuan ukur '{$item->unit()}' tidak ada di referensi (UM.0001-UM.0039)");
        }
        if (!preg_match('/^\d{6}$/', $item->codeValue())) {
            $add(Issue::ERROR, 'Code', "Kode barang/jasa harus 6 digit, didapat '{$item->codeValue()}'");
        }
        if ($line['qty'] <= 0) {
            $add(Issue::ERROR, 'Qty', 'Jumlah harus lebih dari 0');
        }
        if ($line['price'] < 0) {
            $add(Issue::ERROR, 'Price', 'Harga satuan tidak boleh negatif');
        }

        $gross = Money::divide($line['price'] * $line['qty'], 100, $rounding);
        if ($line['discount'] > $gross) {
            $add(Issue::ERROR, 'TotalDiscount', 'Diskon (' . Money::format($line['discount']) . ') melebihi harga x jumlah (' . Money::format($gross) . ')');
        }

        if ($item->fixedAmounts() === null) {
            return $issues;
        }

        // Amounts supplied by the caller: check they add up. Allow one rupiah
        // either way for rounding done at a different step.
        if (abs($gross - $line['discount'] - $line['taxBase']) > 100) {
            $add(Issue::WARNING, 'TaxBase', 'DPP ' . Money::format($line['taxBase']) . ' tidak sama dengan harga x jumlah - diskon (' . Money::format($gross - $line['discount']) . ')');
        }

        [$numerator, $denominator] = $faktur->otherTaxBaseRatioValue();
        $expectedOther = Money::divide($line['taxBase'] * $numerator, $denominator, $rounding);
        if (abs($expectedOther - $line['otherTaxBase']) > 100) {
            $add(Issue::WARNING, 'OtherTaxBase', 'DPP Nilai Lain ' . Money::format($line['otherTaxBase']) . " tidak sama dengan DPP x {$numerator}/{$denominator} (" . Money::format($expectedOther) . ')');
        }

        $expectedVat = Money::divide($line['otherTaxBase'] * $faktur->vatRateValue(), 10000, $rounding) * 100;
        $diff = abs($expectedVat - $line['vat']);
        if ($diff > 100 && in_array($faktur->trxCodeValue(), self::STRICT_VAT_CODES, true)) {
            $add(Issue::ERROR, 'VAT', 'PPN ' . Money::format($line['vat']) . " harus {$faktur->vatRateValue()}% x DPP Nilai Lain (" . Money::format($expectedVat) . ") untuk kode transaksi {$faktur->trxCodeValue()}");
        } elseif ($diff > 0 && $diff <= 100) {
            $add(Issue::WARNING, 'VAT', 'PPN ' . Money::format($line['vat']) . ' selisih pembulatan dari ' . Money::format($expectedVat));
        }

        $expectedStlg = Money::divide($line['otherTaxBase'] * $item->stlgRateValue(), 10000, $rounding) * 100;
        if ($expectedStlg !== $line['stlg']) {
            $add(Issue::WARNING, 'STLG', 'PPnBM ' . Money::format($line['stlg']) . " tidak sama dengan tarif {$item->stlgRateValue()}% x DPP Nilai Lain (" . Money::format($expectedStlg) . ')');
        }

        return $issues;
    }
}
