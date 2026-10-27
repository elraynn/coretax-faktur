<?php

namespace Elrayn\CoretaxFaktur;

use InvalidArgumentException;

// Amounts are kept as integer cents (sen) internally. Floats can't hold most
// decimal fractions exactly, and a PPN that's off by one rupiah is exactly the
// kind of thing that gets a faktur rejected.
final class Money
{
    /**
     * Accepts int, float or a numeric string ("87387387.3", "1000000") and
     * returns cents, rounded half-up at the second decimal. Thousand
     * separators are deliberately not accepted: "1.000" is ambiguous.
     *
     * @param int|float|string $value
     */
    public static function toCents($value): int
    {
        if (is_int($value)) {
            return $value * 100;
        }

        $string = trim((string) $value);

        if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $string, $m)) {
            throw new InvalidArgumentException("Not a plain decimal number: '{$string}'");
        }

        $fraction = str_pad(substr($m[3] ?? '', 0, 3), 3, '0');
        $cents = (int) $m[2] * 100 + intdiv((int) $fraction, 10) + ((int) $fraction[2] >= 5 ? 1 : 0);

        return $m[1] === '-' ? -$cents : $cents;
    }

    // Integer division with rounding, for non-negative numerators.
    public static function divide(int $numerator, int $denominator, string $rounding = Rounding::HALF_UP): int
    {
        $quotient = intdiv($numerator, $denominator);
        $twiceRemainder = 2 * ($numerator % $denominator);

        if ($twiceRemainder > $denominator) {
            return $quotient + 1;
        }

        if ($twiceRemainder === $denominator) {
            if ($rounding === Rounding::HALF_UP || $quotient % 2 === 1) {
                return $quotient + 1;
            }
        }

        return $quotient;
    }

    // Cents to the plain format Coretax expects: dot decimal, no thousand
    // separators, no trailing zeros ("87387387.3", "1000000").
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);
        $fraction = rtrim(str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT), '0');

        return $sign . intdiv($cents, 100) . ($fraction === '' ? '' : '.' . $fraction);
    }
}
