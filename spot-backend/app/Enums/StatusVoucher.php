<?php

namespace App\Enums;

enum StatusVoucher: string
{
    case AVAILABLE = 'available';
    case REDEEMED = 'redeemed';
    case EXPIRED = 'expired';
}
