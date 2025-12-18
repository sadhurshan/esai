import type { Rfq } from '@/sdk';

type RfqLike = Pick<Rfq, 'status' | 'deadlineAt'> | { status?: string | null; deadlineAt?: Date | string | null } | null | undefined;

export function getRfqDeadlineDate(rfq: RfqLike): Date | null {
    if (!rfq || !rfq.deadlineAt) {
        return null;
    }

    if (rfq.deadlineAt instanceof Date) {
        return Number.isNaN(rfq.deadlineAt.getTime()) ? null : rfq.deadlineAt;
    }

    const candidate = new Date(rfq.deadlineAt);
    return Number.isNaN(candidate.getTime()) ? null : candidate;
}

export function isResponseWindowClosed(rfq: RfqLike): boolean {
    if (!rfq) {
        return false;
    }

    const status = typeof rfq.status === 'string' ? rfq.status.toLowerCase() : null;
    const closedByStatus = status !== null && status !== 'open';
    const deadline = getRfqDeadlineDate(rfq);
    const deadlinePassed = deadline ? deadline.getTime() < Date.now() : false;

    return closedByStatus || deadlinePassed;
}
