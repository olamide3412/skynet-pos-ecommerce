<?php

namespace App\Enums;

enum PaymentTypeEnums : string
{
    case Instant = 'instant';
    case Installment = 'installment';
    case OnSiteCollection = 'on-site-collection';
}
