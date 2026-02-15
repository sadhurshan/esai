import type { CursorPaginationMeta } from '@/lib/pagination';

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
    documentId?: number | null;
    type: CompanyDocumentType;
    filename?: string | null;
    mime?: string | null;
    sizeBytes?: number | null;
    downloadUrl?: string | null;
    verifiedAt?: string | null;
    createdAt?: string | null;
    updatedAt?: string | null;
}

export interface CompanyDocumentCollection {
    items: CompanyDocument[];
    meta?: CursorPaginationMeta;
}

export type CompanyUserRole =
    | 'owner'
    | 'buyer_admin'
    | 'buyer_member'
    | 'buyer_requester'
    | 'supplier_admin'
    | 'supplier_estimator'
    | 'finance';

export type CompanyInvitationStatus =
    | 'pending'
    | 'accepted'
    | 'revoked'
    | 'expired';

export interface CompanyInvitation {
    id: number;
    email: string;
    role: CompanyUserRole;
    status: CompanyInvitationStatus;
    companyId: number;
    invitedBy?: number | null;
    expiresAt?: string | null;
    acceptedAt?: string | null;
    revokedAt?: string | null;
    message?: string | null;
    createdAt?: string | null;
}

export interface UserCompanySummary {
    id: number;
    name: string;
    status?: string | null;
    supplierStatus?: string | null;
    role?: string | null;
    isDefault: boolean;
    isActive: boolean;
}

export interface CompanyMember {
    id: number;
    name: string;
    email: string;
    role: CompanyUserRole;
    jobTitle?: string | null;
    phone?: string | null;
    avatarUrl?: string | null;
    lastLoginAt?: string | null;
    isActiveCompany: boolean;
    membership: {
        id: number | null;
        companyId: number | null;
        isDefault: boolean;
        lastUsedAt?: string | null;
        createdAt?: string | null;
        updatedAt?: string | null;
    };
    roleConflict: CompanyMemberRoleConflict;
}

export interface CompanyMemberRoleConflict {
    hasConflict: boolean;
    buyerSupplierConflict: boolean;
    totalCompanies: number;
    distinctRoles: CompanyUserRole[];
}

export type CompanyMemberCollectionMeta = CursorPaginationMeta;

export interface CompanyMemberCollection {
    items: CompanyMember[];
    meta?: CompanyMemberCollectionMeta;
}
