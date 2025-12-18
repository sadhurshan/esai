import type { GoodsReceiptNoteInspector, Quote, QuoteStatusEnum } from '@/sdk';

export interface QuoteComparisonScores {
    price: number;
    leadTime: number;
    risk: number;
    fit: number;
    composite: number;
    rank: number;
}

export interface QuoteComparisonRow {
    quoteId: string;
    rfqId: number;
    supplier?: GoodsReceiptNoteInspector;
    currency: string;
    totalPriceMinor?: number;
    leadTimeDays?: number;
    status?: QuoteStatusEnum;
    attachmentsCount?: number;
    submittedAt?: Date;
    scores: QuoteComparisonScores;
    quote: Quote;
}
