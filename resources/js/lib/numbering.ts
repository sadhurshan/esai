import type { NumberingRule } from '@/types/settings';

const MIN_SEQUENCE_LENGTH = 3;
const MAX_SEQUENCE_LENGTH = 10;

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

export function formatNumberingSample(rule?: NumberingRule | null, fallback = 'PFX-0001'): string {
    if (!rule) {
        return fallback;
    }

    if (rule.sample && rule.sample.trim().length > 0) {
        return rule.sample;
    }

    const nextValue = Number.isFinite(rule.next) ? Math.max(1, Math.trunc(rule.next)) : 1;
    const paddedLength = clamp(rule.sequenceLength ?? MIN_SEQUENCE_LENGTH, MIN_SEQUENCE_LENGTH, MAX_SEQUENCE_LENGTH);
    const padded = String(nextValue).padStart(paddedLength, '0');
    return `${rule.prefix ?? ''}${padded}`;
}
