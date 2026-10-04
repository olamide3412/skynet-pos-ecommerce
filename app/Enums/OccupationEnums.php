<?php

namespace App\Enums;

enum OccupationEnums : string
{
    case SelfEmployed = 'self employed';
    case Employed = 'employed';
    case Student  = 'student';
    case Unemployed = 'unemployed';
}
