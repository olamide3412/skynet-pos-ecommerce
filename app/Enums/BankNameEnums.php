<?php

namespace App\Enums;

enum BankNameEnums: string
{
    case ACCESS_BANK = 'Access Bank';
    case CITI_BANK = 'Citi Bank';
    case DIAMOND_BANK = 'Diamond Bank';
    case ECOBANK = 'Ecobank';
    case FCMB = 'First City Monument Bank (FCMB)';
    case FIDELITY_BANK = 'Fidelity Bank';
    case FIRST_BANK = 'First Bank of Nigeria';
    case GTB = 'Guaranty Trust Bank (GTB)';
    case HERITAGE_BANK = 'Heritage Bank';
    case JAIZ_BANK = 'Jaiz Bank';
    case KEYSTONE_BANK = 'Keystone Bank';
    case POLARIS_BANK = 'Polaris Bank';
    case PROVIDUS_BANK = 'Providus Bank';
    case STANBIC_IBTC = 'Stanbic IBTC Bank';
    case STANDARD_CHARTERED = 'Standard Chartered Bank';
    case STERLING_BANK = 'Sterling Bank';
    case UNION_BANK = 'Union Bank of Nigeria';
    case UBA = 'United Bank for Africa (UBA)';
    case UNITY_BANK = 'Unity Bank';
    case WEMA_BANK = 'Wema Bank';
    case ZENITH_BANK = 'Zenith Bank';

    /**
     * Get all bank names as an array
     *
     * @return array
     */
    public static function getAll(): array
    {
        return array_column(BankNameEnums::cases(), 'value');
    }
}
