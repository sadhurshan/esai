export type RiskGrade = 'low' | 'medium' | 'high';

export interface SupplierRiskScore {
    supplierId: number;
    supplierName: string | null;
    riskGrade: RiskGrade | null;
    overallScore: number | null;
    onTimeDeliveryRate: number | null;
    defectRate: number | null;
    priceVolatility: number | null;
    leadTimeVolatility: number | null;
    responsivenessRate: number | null;
    badges: string[];
    meta: {
        periodKey?: string | null;
        periodStart?: string | null;
        periodEnd?: string | null;
        [key: string]: unknown;
    } | null;
    createdAt: string | null;
    updatedAt: string | null;
}
