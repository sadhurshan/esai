import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type {
    AiChatReviewChecklistItem,
    AiChatReviewPayload,
} from '@/types/ai-chat';

const STATUS_BADGE_CLASS: Record<AiChatReviewChecklistItem['status'], string> =
    {
        ok: 'border-emerald-300/30 bg-emerald-300/10 text-emerald-100',
        warning: 'border-amber-300/30 bg-amber-300/10 text-amber-100',
        risk: 'border-rose-300/30 bg-rose-300/10 text-rose-100',
    };

interface ReviewChecklistProps {
    review: AiChatReviewPayload;
}

export function ReviewChecklist({ review }: ReviewChecklistProps) {
    const checklist = review.checklist ?? [];
    const highlights = review.highlights ?? [];

    return (
        <div className="space-y-4 rounded-2xl border border-white/10 bg-slate-950/50 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p className="text-xs tracking-[0.2em] text-slate-400 uppercase">
                        {review.entity_type}
                    </p>
                    <h3 className="text-base font-semibold text-white">
                        {review.title ?? `Review ${review.entity_id}`}
                    </h3>
                    {review.summary ? (
                        <p className="text-sm text-slate-300">
                            {review.summary}
                        </p>
                    ) : null}
                </div>
                <Badge
                    variant="outline"
                    className="border-white/20 text-[11px] tracking-wide text-white/80 uppercase"
                >
                    #{review.entity_id}
                </Badge>
            </div>

            <ul className="space-y-3">
                {checklist.map((item, index) => (
                    <li
                        key={`${item.label}-${index}`}
                        className="rounded-xl border border-white/10 bg-white/5 p-3"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-sm font-medium text-white">
                                    {item.label}
                                </p>
                                <p className="text-xs text-slate-300">
                                    {item.detail}
                                </p>
                            </div>
                            <Badge
                                variant="outline"
                                className={cn(
                                    'text-xs capitalize',
                                    STATUS_BADGE_CLASS[item.status],
                                )}
                            >
                                {item.status}
                            </Badge>
                        </div>
                        {item.value !== undefined &&
                        item.value !== null &&
                        item.value !== '' ? (
                            <p className="mt-2 text-sm text-slate-200">
                                {String(item.value)}
                            </p>
                        ) : null}
                    </li>
                ))}
            </ul>

            {highlights.length > 0 ? (
                <div className="rounded-xl border border-dashed border-white/15 bg-slate-900/60 p-3 text-sm text-slate-300">
                    <p className="text-xs tracking-wide text-slate-400 uppercase">
                        Highlights
                    </p>
                    <ul className="mt-2 space-y-1">
                        {highlights.map((note, index) => (
                            <li key={`${note}-${index}`}>• {note}</li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </div>
    );
}
