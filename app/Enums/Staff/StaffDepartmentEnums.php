<?php

namespace App\Enums\Staff;

enum StaffDepartmentEnums: string
{
    case Finance = 'finance';
    case HumanResources = 'human_resources';
    case IT = 'it';
    case Marketing = 'marketing';
    case Sales = 'sales';
    case RiskManagement = 'risk_management';
    case Compliance = 'compliance';
    case CustomerService = 'customer_service';
    case Operations = 'operations';
    case Audit = 'audit';
    case Legal = 'legal';
    case ProductDevelopment = 'product_development';
    case Treasury = 'treasury';
    case InvestmentBanking = 'investment_banking';
    case WealthManagement = 'wealth_management';
    case LoanDepartment = 'loan_department';
    case MortgageDepartment = 'mortgage_department';
    case CreditCardDepartment = 'credit_card_department';
    case Insurance = 'insurance';
    case BusinessAnalytics = 'business_analytics';

    /**
     * Get a user-friendly label for each department.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Finance => 'Finance',
            self::HumanResources => 'Human Resources',
            self::IT => 'Information Technology',
            self::Marketing => 'Marketing',
            self::Sales => 'Sales',
            self::RiskManagement => 'Risk Management',
            self::Compliance => 'Compliance',
            self::CustomerService => 'Customer Service',
            self::Operations => 'Operations',
            self::Audit => 'Audit',
            self::Legal => 'Legal',
            self::ProductDevelopment => 'Product Development',
            self::Treasury => 'Treasury',
            self::InvestmentBanking => 'Investment Banking',
            self::WealthManagement => 'Wealth Management',
            self::LoanDepartment => 'Loan Department',
            self::MortgageDepartment => 'Mortgage Department',
            self::CreditCardDepartment => 'Credit Card Department',
            self::Insurance => 'Insurance',
            self::BusinessAnalytics => 'Business Analytics',
        };
    }
}
