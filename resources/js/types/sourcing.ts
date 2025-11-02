export interface Supplier {
    id: number;
    name: string;
    rating: number;
    capabilities: string[];
    materials: string[];
    locationRegion: string;
    minimumOrderQuantity: number;
    averageResponseHours: number;
}

export interface RFQ {
    id: number;
    rfqNumber: string;
    title: string;
    method: string;
    material: string;
    quantity: number;
    dueDate: string;
    status: string;
    companyName: string;
    openBidding: boolean;
}

export interface RFQQuote {
    id: number;
    supplierName: string;
    revision: number;
    totalPriceUsd: number;
    unitPriceUsd: number;
    leadTimeDays: number;
    status: string;
    submittedAt: string;
}

export interface Order {
    id: number;
    orderNumber: string;
    party: string;
    item: string;
    quantity: number;
    totalUsd: number;
    orderDate: string;
    status: string;
}
