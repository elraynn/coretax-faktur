<?php

namespace Elrayn\CoretaxFaktur;

// Satuan ukur codes, from the REF-General sheet of the DJP template v1.6.1.
final class Unit
{
    const METRIK_TON = 'UM.0001';
    const WET_TON = 'UM.0002';
    const KILOGRAM = 'UM.0003';
    const GRAM = 'UM.0004';
    const KARAT = 'UM.0005';
    const KILOLITER = 'UM.0006';
    const LITER = 'UM.0007';
    const BARREL = 'UM.0008';
    const MMBTU = 'UM.0009';
    const AMPERE = 'UM.0010';
    const SENTIMETER_KUBIK = 'UM.0011';
    const METER_PERSEGI = 'UM.0012';
    const METER = 'UM.0013';
    const INCI = 'UM.0014';
    const SENTIMETER = 'UM.0015';
    const YARD = 'UM.0016';
    const LUSIN = 'UM.0017';
    const UNIT = 'UM.0018';
    const SET = 'UM.0019';
    const LEMBAR = 'UM.0020';
    const PIECE = 'UM.0021';
    const BOKS = 'UM.0022';
    const TAHUN = 'UM.0023';
    const BULAN = 'UM.0024';
    const MINGGU = 'UM.0025';
    const HARI = 'UM.0026';
    const JAM = 'UM.0027';
    const MENIT = 'UM.0028';
    const PERSEN = 'UM.0029';
    const KEGIATAN = 'UM.0030';
    const LAPORAN = 'UM.0031';
    const BAHAN = 'UM.0032';
    const LAINNYA = 'UM.0033';
    const METER_KUBIK = 'UM.0034';
    const SENTIMETER_PERSEGI = 'UM.0035';
    const DRUM = 'UM.0036';
    const KARTON = 'UM.0037';
    const KWH = 'UM.0038';
    const ROLL = 'UM.0039';

    public static function isValid(string $code): bool
    {
        return preg_match('/^UM\.00(0[1-9]|[12]\d|3\d)$/', $code) === 1;
    }
}
