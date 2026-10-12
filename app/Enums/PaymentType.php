<?php

namespace App\Enums;

enum PaymentType: string
{
    case Deposit = 'deposit';
    case Settlement = 'settlement';
    case Refund = 'refund';
}
