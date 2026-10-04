<?php

namespace App\Enums\Staff;

enum StaffPositionEnums: string
{
    case CEO = 'ceo';
    case CFO = 'cfo';
    case COO = 'coo';
    case CTO = 'cto';
    case CIO = 'cio';
    case GeneralManager = 'general_manager';
    case BranchManager = 'branch_manager';
    case DepartmentHead = 'department_head';
    case TeamLead = 'team_lead';
    case SeniorAnalyst = 'senior_analyst';
    case Analyst = 'analyst';
    case Associate = 'associate';
    case Officer = 'officer';
    case JuniorOfficer = 'junior_officer';
    case Clerk = 'clerk';
    case CustomerServiceRepresentative = 'customer_service_representative';
    case LoanOfficer = 'loan_officer';
    case ComplianceOfficer = 'compliance_officer';
    case RiskAnalyst = 'risk_analyst';
    case ITAdministrator = 'it_administrator';
    case SoftwareEngineer = 'software_engineer';
    case Auditor = 'auditor';
    case LegalCounsel = 'legal_counsel';
    case MarketingSpecialist = 'marketing_specialist';
    case SalesExecutive = 'sales_executive';
    case Intern = 'intern';

    /**
     * Get a user-friendly label for each position.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::CEO => 'Chief Executive Officer',
            self::CFO => 'Chief Financial Officer',
            self::COO => 'Chief Operating Officer',
            self::CTO => 'Chief Technology Officer',
            self::CIO => 'Chief Information Officer',
            self::GeneralManager => 'General Manager',
            self::BranchManager => 'Branch Manager',
            self::DepartmentHead => 'Department Head',
            self::TeamLead => 'Team Lead',
            self::SeniorAnalyst => 'Senior Analyst',
            self::Analyst => 'Analyst',
            self::Associate => 'Associate',
            self::Officer => 'Officer',
            self::JuniorOfficer => 'Junior Officer',
            self::Clerk => 'Clerk',
            self::CustomerServiceRepresentative => 'Customer Service Representative',
            self::LoanOfficer => 'Loan Officer',
            self::ComplianceOfficer => 'Compliance Officer',
            self::RiskAnalyst => 'Risk Analyst',
            self::ITAdministrator => 'IT Administrator',
            self::SoftwareEngineer => 'Software Engineer',
            self::Auditor => 'Auditor',
            self::LegalCounsel => 'Legal Counsel',
            self::MarketingSpecialist => 'Marketing Specialist',
            self::SalesExecutive => 'Sales Executive',
            self::Intern => 'Intern',
        };
    }
}
