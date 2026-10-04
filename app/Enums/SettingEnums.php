<?php

namespace App\Enums;

enum SettingEnums : string
{
    case IntegerType = 'integer';
    case DecimalType = 'decimal';
    case BooleanType = 'boolean';
    case StringType = 'string';

    case GeneralCategory = 'general';
    case BusinessLoanCategory = 'business_loan';
    case PersonalLoanCategory = 'personal_loan';

    case InterestRate = 'interest_rate';
    case MinLoanAmount = 'min_loan_amount';
    case MaxLoanAmount = 'max_loan_amount';
    case RepaymentTerm = 'repayment_term';
    case RepaymentFrequency = 'repayment_frequency';
    case ApplicationFee = 'application_fee';
    case OverdueDailyIntrestRate = 'overdue_daily_intrest_rate';
    case EligibilityCriteria = 'eligibility_criteria';

    case ContactEmail = 'contact_email';
    case MaintenanceMode = 'maintenance_mode';

    case PaymentStartTime = 'payment_start_time';
    case PaymentEndTime = 'payment_end_time';

    case RegistrationFee = 'registration_fee';

    public static function getAll(): array
    {
        return array_column(SettingEnums::cases(), 'value');
    }
}
