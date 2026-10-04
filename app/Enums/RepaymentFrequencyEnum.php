<?php

namespace App\Enums;

enum RepaymentFrequencyEnum : string
{
    case OneMonth = 1;
    case TwoMonths = 2;
    case ThreeMonths = 3;
    case FourMonths = 4;
    case FiveMonths = 5;
    case SixMonths = 6;
    case SevenMonths = 7;
    case EightMonths = 8;
    case NineMonths = 9;
    case TenMonths = 10;
    case ElevenMonths = 11;
    case TwelveMonths = 12;

    /**
     * Get the label for the frequency.
     */
    public function label(): string
    {
        return $this->value . ' month' . ($this->value > 1 ? 's' : '');
    }
}
