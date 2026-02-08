export interface CompaniesHouseSearchAddress {
    address_line_1?: string | null;
    address_line_2?: string | null;
    locality?: string | null;
    region?: string | null;
    postal_code?: string | null;
    country?: string | null;
}

export interface CompaniesHouseSearchItem {
    company_name?: string | null;
    company_number?: string | null;
    company_status?: string | null;
    company_type?: string | null;
    date_of_creation?: string | null;
    address_snippet?: string | null;
    address?: CompaniesHouseSearchAddress | null;
}

export interface CompaniesHouseSearchResponse {
    items?: CompaniesHouseSearchItem[];
    total_results?: number | null;
    retrieved_at?: string | null;
}

export interface CompaniesHouseProfileResponse {
    profile?: {
        company_name?: string | null;
        company_number?: string | null;
        company_status?: string | null;
        type?: string | null;
        jurisdiction?: string | null;
        date_of_creation?: string | null;
        registered_office_address?: CompaniesHouseSearchAddress | null;
        retrieved_at?: string | null;
    } | null;
}
