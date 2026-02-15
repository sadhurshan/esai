export const RFQ_METHOD_VALUES = [
    'cnc',
    'sheet_metal',
    'injection_molding',
    '3d_printing',
    'casting',
    'other',
] as const;

export type RfqMethod = (typeof RFQ_METHOD_VALUES)[number];

export interface RfqMethodOption {
    value: RfqMethod;
    label: string;
    description: string;
}

export const RFQ_METHOD_OPTIONS: RfqMethodOption[] = [
    {
        value: 'cnc',
        label: 'CNC machining',
        description: 'Multi-axis milling, turning, EDM, Swiss',
    },
    {
        value: 'sheet_metal',
        label: 'Sheet metal',
        description: 'Laser cutting, bending, stamping, forming',
    },
    {
        value: 'injection_molding',
        label: 'Injection molding',
        description: 'Plastic molding, insert molding, tooling',
    },
    {
        value: '3d_printing',
        label: '3D printing',
        description: 'Metal/polymer additive manufacturing, SLA, SLS',
    },
    {
        value: 'casting',
        label: 'Casting',
        description: 'Sand, investment, die casting, foundry work',
    },
    {
        value: 'other',
        label: 'Other / custom',
        description: 'Hybrid or unspecified processes',
    },
];

const RFQ_METHOD_LABEL_MAP = RFQ_METHOD_OPTIONS.reduce<Record<string, string>>(
    (map, option) => {
        map[option.value] = option.label;
        return map;
    },
    {},
);

export function isRfqMethod(value: unknown): value is RfqMethod {
    return (
        typeof value === 'string' &&
        (RFQ_METHOD_VALUES as readonly string[]).includes(value)
    );
}

export function getRfqMethodLabel(value?: string | null): string {
    if (!value) {
        return 'Unspecified';
    }

    return RFQ_METHOD_LABEL_MAP[value] ?? value;
}
