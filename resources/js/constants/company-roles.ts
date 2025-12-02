import type { CompanyUserRole } from '@/types/company';

export interface CompanyRoleOption {
    value: CompanyUserRole;
    label: string;
    description: string;
}

export const COMPANY_ROLE_VALUES = [
    'owner',
    'buyer_admin',
    'buyer_member',
    'buyer_requester',
    'supplier_admin',
    'supplier_estimator',
    'finance',
] as const satisfies readonly CompanyUserRole[];

export const COMPANY_ROLE_OPTIONS: CompanyRoleOption[] = [
    { value: 'owner', label: 'Owner', description: 'Full platform control across sourcing, orders, and billing.' },
    { value: 'buyer_admin', label: 'Buyer admin', description: 'Manage RFQs, suppliers, and all purchasing settings.' },
    { value: 'buyer_member', label: 'Buyer member', description: 'Collaborate on sourcing events without admin settings.' },
    { value: 'buyer_requester', label: 'Requester', description: 'Raise RFQs and monitor awards without purchasing authority.' },
    { value: 'supplier_admin', label: 'Supplier admin', description: 'Manage supplier profile, quotes, and receiving documents.' },
    { value: 'supplier_estimator', label: 'Estimator', description: 'Prepare and submit quotes without workspace administration.' },
    { value: 'finance', label: 'Finance', description: 'Access invoices, credits, payments, and billing workflows.' },
];

export const COMPANY_ROLE_LABELS = COMPANY_ROLE_OPTIONS.reduce<Record<CompanyUserRole, string>>((acc, option) => {
    acc[option.value] = option.label;
    return acc;
}, {} as Record<CompanyUserRole, string>);
