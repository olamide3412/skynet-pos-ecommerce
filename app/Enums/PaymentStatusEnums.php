<?php

namespace App\Enums;

enum PaymentStatusEnums  : string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
