export const RFQ_STATUS_BADGE_MAP: Record<string, string> = {
    awaiting: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-100',
    open: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-100',
    closed: 'bg-slate-200 text-slate-800 dark:bg-slate-600/40 dark:text-slate-100',
    awarded: 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-100',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-100',
    draft: 'bg-neutral-200 text-neutral-800 dark:bg-neutral-700/80 dark:text-neutral-100',
    submitted: 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-100',
};

export const ORDER_STATUS_BADGE_MAP: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-100',
    confirmed: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-100',
    in_production: 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-100',
    in_transit: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-100',
    delivered: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-100',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-100',
    requested: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-100',
    received: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-100',
};
