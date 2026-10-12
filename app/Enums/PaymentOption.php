<?php

namespace App\Enums;

enum PaymentOption: string
{
    case BankTransfer = 'bank_transfer';
    case PayOnArrival = 'pay_on_arrival';
    case ViaOta = 'via_ota';
}
