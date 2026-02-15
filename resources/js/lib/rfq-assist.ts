export type RfqAssistSource = 'title' | 'attachment';

export type RfqAssistSuggestion<T = string | number> = {
    value: T;
    source: RfqAssistSource;
    note: string;
};

export type RfqAssistSuggestions = {
    method?: RfqAssistSuggestion<string>;
    material?: RfqAssistSuggestion<string>;
    finish?: RfqAssistSuggestion<string>;
    quantity?: RfqAssistSuggestion<number>;
    leadTimeDays?: RfqAssistSuggestion<number>;
};

const PROCESS_KEYWORDS: Record<string, string[]> = {
    'CNC machining': ['cnc', 'machining', 'milled', 'turned', 'lathe'],
    'Injection molding': ['injection', 'molding', 'mould'],
    '3D printing': ['3d', 'print', 'additive'],
    'Sheet metal': ['sheet', 'laser', 'waterjet', 'bending'],
    Casting: ['casting', 'cast'],
    Forging: ['forging'],
    Extrusion: ['extrusion'],
    Welding: ['weld', 'welding'],
};

const MATERIAL_KEYWORDS: Record<string, string[]> = {
    Aluminum: ['aluminum', 'aluminium', 'al'],
    Steel: ['steel', 'carbon steel'],
    'Stainless steel': ['stainless', 'ss'],
    Brass: ['brass'],
    Copper: ['copper'],
    Titanium: ['titanium', 'ti'],
    ABS: ['abs'],
    Nylon: ['nylon', 'pa6', 'pa12'],
    Delrin: ['delrin', 'acetal'],
    Polycarbonate: ['polycarbonate', 'pc'],
    PVC: ['pvc'],
    Rubber: ['rubber', 'epdm'],
};

const FINISH_KEYWORDS: Record<string, string[]> = {
    Anodized: ['anodize', 'anodized', 'anodising'],
    'Powder coat': ['powder', 'powder coat', 'powdercoat'],
    Painted: ['paint', 'painted'],
    Plated: ['plate', 'plated', 'zinc', 'nickel'],
    Passivated: ['passivate', 'passivated'],
    'Bead blasted': ['bead', 'blast', 'blasted'],
};

const QUANTITY_REGEXES: RegExp[] = [
    /(?:qty|quantity|pcs|pc|units|ea|x)\s*[-:x]?\s*([0-9]{1,6})/i,
    /([0-9]{1,6})\s*(?:pcs|pc|units|ea)/i,
];

const LEAD_TIME_REGEXES: RegExp[] = [
    /(?:lead\s*time|lt)\s*[:-]?\s*([0-9]{1,3})\s*(days?|d|weeks?|w|wk)/i,
    /([0-9]{1,3})\s*(days?|d|weeks?|w|wk)\s*(?:lead\s*time|lt)/i,
];

const normalizeText = (value: string): string => value.toLowerCase();

const findKeywordSuggestion = (
    text: string,
    keywords: Record<string, string[]>,
): { value: string; note: string } | null => {
    const normalized = normalizeText(text);

    for (const [value, entries] of Object.entries(keywords)) {
        for (const entry of entries) {
            if (normalized.includes(entry)) {
                return { value, note: `Matched "${entry}"` };
            }
        }
    }

    return null;
};

const parseQuantity = (text: string): number | null => {
    for (const regex of QUANTITY_REGEXES) {
        const match = text.match(regex);
        if (match && match[1]) {
            const qty = Number(match[1]);
            if (!Number.isNaN(qty) && qty > 0) {
                return qty;
            }
        }
    }

    return null;
};

const parseLeadTimeDays = (text: string): number | null => {
    for (const regex of LEAD_TIME_REGEXES) {
        const match = text.match(regex);
        if (!match || !match[1] || !match[2]) {
            continue;
        }

        const value = Number(match[1]);
        if (Number.isNaN(value) || value <= 0) {
            continue;
        }

        const unit = match[2].toLowerCase();
        const multiplier = unit.startsWith('w') ? 7 : 1;
        return value * multiplier;
    }

    return null;
};

const scanAttachments = (
    attachments: File[],
    finder: (text: string) => { value: string; note: string } | null,
): RfqAssistSuggestion<string> | undefined => {
    for (const attachment of attachments) {
        const match = finder(attachment.name);
        if (match) {
            return {
                value: match.value,
                source: 'attachment',
                note: `${match.note} in ${attachment.name}`,
            };
        }
    }

    return undefined;
};

const scanAttachmentNumbers = (
    attachments: File[],
    parser: (text: string) => number | null,
    label: string,
): RfqAssistSuggestion<number> | undefined => {
    for (const attachment of attachments) {
        const value = parser(attachment.name);
        if (value) {
            return {
                value,
                source: 'attachment',
                note: `Parsed ${label} from ${attachment.name}`,
            };
        }
    }

    return undefined;
};

export const buildRfqAssistSuggestions = (
    title: string,
    attachments: File[],
): RfqAssistSuggestions => {
    const suggestions: RfqAssistSuggestions = {};

    const titleMethod = findKeywordSuggestion(title, PROCESS_KEYWORDS);
    if (titleMethod) {
        suggestions.method = {
            value: titleMethod.value,
            source: 'title',
            note: titleMethod.note,
        };
    } else {
        suggestions.method = scanAttachments(attachments, (text) =>
            findKeywordSuggestion(text, PROCESS_KEYWORDS),
        );
    }

    const titleMaterial = findKeywordSuggestion(title, MATERIAL_KEYWORDS);
    if (titleMaterial) {
        suggestions.material = {
            value: titleMaterial.value,
            source: 'title',
            note: titleMaterial.note,
        };
    } else {
        suggestions.material = scanAttachments(attachments, (text) =>
            findKeywordSuggestion(text, MATERIAL_KEYWORDS),
        );
    }

    const titleFinish = findKeywordSuggestion(title, FINISH_KEYWORDS);
    if (titleFinish) {
        suggestions.finish = {
            value: titleFinish.value,
            source: 'title',
            note: titleFinish.note,
        };
    } else {
        suggestions.finish = scanAttachments(attachments, (text) =>
            findKeywordSuggestion(text, FINISH_KEYWORDS),
        );
    }

    const titleQuantity = parseQuantity(title);
    if (titleQuantity) {
        suggestions.quantity = {
            value: titleQuantity,
            source: 'title',
            note: 'Parsed quantity from title',
        };
    } else {
        suggestions.quantity = scanAttachmentNumbers(
            attachments,
            parseQuantity,
            'quantity',
        );
    }

    const titleLeadTime = parseLeadTimeDays(title);
    if (titleLeadTime) {
        suggestions.leadTimeDays = {
            value: titleLeadTime,
            source: 'title',
            note: 'Parsed lead time from title',
        };
    } else {
        suggestions.leadTimeDays = scanAttachmentNumbers(
            attachments,
            parseLeadTimeDays,
            'lead time',
        );
    }

    return suggestions;
};

export const buildRfqAssistSignature = (
    title: string,
    attachments: File[],
): string => {
    const attachmentSignature = attachments
        .map((file) => `${file.name}:${file.size}:${file.lastModified}`)
        .join('|');

    return `${title.trim().toLowerCase()}::${attachmentSignature}`;
};
