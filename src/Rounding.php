<?php

namespace Elrayn\CoretaxFaktur;

final class Rounding
{
    // Pembulatan komersial, what the DJP template asks for: 3910.5 -> 3911.
    const HALF_UP = 'half_up';

    // Banker's rounding (PHP_ROUND_HALF_EVEN): 3910.5 -> 3910, 3911.5 -> 3912.
    // Here for apps that already produced fakturs this way and need to match.
    const HALF_EVEN = 'half_even';
}
