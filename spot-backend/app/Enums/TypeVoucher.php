<?php declare(strict_types=1);

namespace App\Enums;

enum TypeVoucher: string
{
    case SPOT = 'spot';
    case FUTURE = 'future';
}
