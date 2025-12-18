export function normalizeTaxCodeIds(values?: Array<string | number | null | undefined>): number[] {
    if (!values || values.length === 0) {
        return [];
    }

    return values
        .map((value) => {
            if (typeof value === 'number') {
                return Number.isFinite(value) ? value : null;
            }
            if (typeof value === 'string') {
                const trimmed = value.trim();
                if (!trimmed.length) {
                    return null;
                }
                const parsed = Number(trimmed);
                return Number.isFinite(parsed) ? parsed : null;
            }
            return null;
        })
        .filter((value): value is number => value !== null)
        .sort((a, b) => a - b);
}

export function haveSameTaxCodeIds(
    left?: Array<string | number | null | undefined>,
    right?: Array<string | number | null | undefined>,
): boolean {
    const normalizedLeft = normalizeTaxCodeIds(left);
    const normalizedRight = normalizeTaxCodeIds(right);

    if (normalizedLeft.length !== normalizedRight.length) {
        return false;
    }

    return normalizedLeft.every((value, index) => value === normalizedRight[index]);
}
