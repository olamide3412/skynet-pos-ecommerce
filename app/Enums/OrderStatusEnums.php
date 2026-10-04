<?php

namespace App\Enums;

enum OrderStatusEnums  : string
{

    case Pending = 'pending';
    case Processing = 'processing';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Paid = 'paid';
    case Partial = 'partial';
    case Completed = 'completed';


}
