export interface CompanyAddress {
    attention?: string | null;
    line1: string;
    line2?: string | null;
    city?: string | null;
    state?: string | null;
    postalCode?: string | null;
    country: string;
}

export interface CompanySettings {
    legalName: string;
    displayName: string;
    taxId?: string | null;
    registrationNumber?: string | null;
    emails: string[];
    phones: string[];
    billTo: CompanyAddress;
    shipFrom: CompanyAddress;
    logoUrl?: string | null;
    markUrl?: string | null;
}

export interface CurrencyPreferences {
    primary: string;
    displayFx: boolean;
}

export interface UomMappings {
    baseUom: string;
    maps: Record<string, string>;
}

export interface LocalizationSettings {
    timezone: string;
    locale: string;
    dateFormat: string;
    numberFormat: string;
    currency: CurrencyPreferences;
    uom: UomMappings;
}

export type NumberResetPolicy = 'never' | 'yearly';

export interface NumberingRule {
    prefix: string;
    sequenceLength: number;
    next: number;
    reset: NumberResetPolicy;
    sample?: string | null;
}

export interface NumberingSettings {
    rfq: NumberingRule;
    quote: NumberingRule;
    po: NumberingRule;
    invoice: NumberingRule;
    grn: NumberingRule;
    credit: NumberingRule;
}

export interface CompanyAiSettings {
    llmAnswersEnabled: boolean;
    llmProvider: 'dummy' | 'openai';
}
