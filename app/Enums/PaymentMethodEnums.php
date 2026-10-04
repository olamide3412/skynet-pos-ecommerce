<?php

namespace App\Enums;

enum PaymentMethodEnums : string
{
    case Cash = 'cash';
    case BankTransfer = 'bank-transfer';
    case Card = 'card';
    case Flutterwave = 'flutterwave';
}
