<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case CardOrQris = 'card_or_qris';
    case Ota = 'ota';
}
