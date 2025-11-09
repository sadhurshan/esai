export type CompanyStatus = 'pending' | 'active' | 'rejected';

export interface Company {
    id: number;
    name: string;
    slug: string;
    status: CompanyStatus;
    registrationNo: string;
    taxId: string;
    country: string;
    emailDomain: string;
    primaryContactName: string;
    primaryContactEmail: string;
    primaryContactPhone: string;
    address?: string | null;
    phone?: string | null;
    website?: string | null;
    region?: string | null;
    rejectionReason?: string | null;
    ownerUserId?: number | null;
    createdAt?: string | null;
    updatedAt?: string | null;
    hasCompletedOnboarding: boolean;
}

export type CompanyDocumentType = 'registration' | 'tax' | 'esg' | 'other';

export interface CompanyDocument {
    id: number;
    companyId: number;
    type: CompanyDocumentType;
    path: string;
    verifiedAt?: string | null;
    createdAt?: string | null;
    updatedAt?: string | null;
}
