import { formatDistanceToNow } from 'date-fns';
import { useMemo, useState, type ChangeEvent } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import type { ClarificationSubmissionPayload } from '@/hooks/api/rfqs/use-rfq-clarifications';
import type { RfqClarification } from '@/sdk';
import { Paperclip, X } from 'lucide-react';

function normalizeClarificationTimestamp(value: RfqClarification['createdAt']): number {
    if (value instanceof Date) {
        return value.getTime();
    }
    if (!value) {
        return 0;
    }
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? 0 : parsed.getTime();
}

function resolveClarificationDate(value: RfqClarification['createdAt']): Date | null {
    if (value instanceof Date) {
        return value;
    }
    if (!value) {
        return null;
    }
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

interface ClarificationGroup {
    key: string;
    label: string;
    clarifications: RfqClarification[];
}

function resolveClarificationGroup(item: RfqClarification): { key: string; label: string } {
    const author = item.author as Record<string, unknown> | undefined;
    const supplierIdCandidate = author?.supplierId ?? author?.supplier_id;
    const supplierId = typeof supplierIdCandidate === 'string' ? supplierIdCandidate : undefined;
    const authorName = typeof author?.name === 'string' ? author.name : undefined;
    const companyName =
        author && typeof author === 'object' && 'company' in author && author.company && typeof author.company === 'object'
            ? (author.company as { name?: string }).name
            : undefined;

    const fallbackLabel = item.type === 'question' ? 'Supplier' : 'Internal team';
    const label = authorName ?? companyName ?? (supplierId ? `Supplier ${supplierId}` : fallbackLabel);
    const fallbackKey = item.type === 'question' ? `thread-${item.id}` : 'internal';
    const key = supplierId ?? authorName ?? companyName ?? fallbackKey;

    return { key, label };
}

type ClarificationAttachment = NonNullable<RfqClarification['attachments']>[number];

interface NormalizedClarificationAttachment {
    id: string;
    filename: string;
    url?: string;
    sizeLabel?: string;
}

function resolveClarificationBody(item: RfqClarification): string {
    if (typeof item.body === 'string' && item.body.length > 0) {
        return item.body;
    }

    const fallback = (item as unknown as Record<string, unknown>).message;

    return typeof fallback === 'string' ? fallback : '';
}

function normalizeClarificationAttachment(attachment: ClarificationAttachment, index: number): NormalizedClarificationAttachment {
    const record = attachment as Record<string, unknown>;
    const idCandidate = record.documentId ?? record.document_id ?? record.id ?? `attachment-${index}`;
    const filenameCandidate = record.filename;
    const urlCandidate = record.downloadUrl ?? record.download_url ?? record.url;
    const sizeCandidate = record.sizeBytes ?? record.size_bytes;

    return {
        id: typeof idCandidate === 'string' ? idCandidate : String(idCandidate ?? `attachment-${index}`),
        filename:
            typeof filenameCandidate === 'string' && filenameCandidate.length > 0
                ? filenameCandidate
                : `Attachment ${index + 1}`,
        url: typeof urlCandidate === 'string' ? urlCandidate : undefined,
        sizeLabel: typeof sizeCandidate === 'number' ? formatFileSize(sizeCandidate) : undefined,
    };
}

function formatFileSize(size: number): string {
    if (!Number.isFinite(size) || size <= 0) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let nextSize = size;
    let unitIndex = 0;

    while (nextSize >= 1024 && unitIndex < units.length - 1) {
        nextSize /= 1024;
        unitIndex += 1;
    }

    return `${nextSize.toFixed(nextSize >= 10 ? 0 : 1)} ${units[unitIndex]}`;
}

function renderClarificationAttachments(attachments?: RfqClarification['attachments']) {
    if (!attachments || attachments.length === 0) {
        return null;
    }

    return (
        <ul className="mt-3 space-y-2">
            {attachments.map((attachment, index) => {
                const normalized = normalizeClarificationAttachment(attachment, index);

                return (
                    <li key={normalized.id} className="flex items-center justify-between gap-3 rounded-md border border-dashed p-2 text-sm">
                        <div className="flex items-center gap-2">
                            <Paperclip className="h-4 w-4 text-muted-foreground" />
                            {normalized.url ? (
                                <a
                                    href={normalized.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary hover:underline"
                                >
                                    {normalized.filename}
                                </a>
                            ) : (
                                <span>{normalized.filename}</span>
                            )}
                        </div>
                        {normalized.sizeLabel ? (
                            <span className="text-xs text-muted-foreground">{normalized.sizeLabel}</span>
                        ) : null}
                    </li>
                );
            })}
        </ul>
    );
}

const clarifySchema = z.object({
    message: z.string().min(1, 'Message cannot be empty.'),
});

export interface ClarificationThreadProps {
    clarifications: RfqClarification[];
    onAskQuestion: (payload: ClarificationSubmissionPayload) => Promise<unknown>;
    onAnswerQuestion: (payload: ClarificationSubmissionPayload) => Promise<unknown>;
    isSubmittingQuestion?: boolean;
    isSubmittingAnswer?: boolean;
    canAskQuestion?: boolean;
    canAnswerQuestion?: boolean;
}

export function ClarificationThread({
    clarifications,
    onAskQuestion,
    onAnswerQuestion,
    isSubmittingQuestion = false,
    isSubmittingAnswer = false,
    canAskQuestion = true,
    canAnswerQuestion = true,
}: ClarificationThreadProps) {
    const groupedClarifications = useMemo(() => {
        const sorted = [...clarifications].sort((a, b) => {
            const left = normalizeClarificationTimestamp(a.createdAt);
            const right = normalizeClarificationTimestamp(b.createdAt);
            return left - right;
        });

        const groups = new Map<string, ClarificationGroup>();
        let lastSupplierGroupKey: string | null = null;

        sorted.forEach((item) => {
            const { key, label } = resolveClarificationGroup(item);
            let targetKey = key;
            let targetLabel = label;

            // TODO: clarify with spec once the clarifications endpoint exposes explicit thread identifiers for answers.
            if (item.type === 'answer' && (!groups.has(targetKey) || targetKey === 'internal') && lastSupplierGroupKey && groups.has(lastSupplierGroupKey)) {
                targetKey = lastSupplierGroupKey;
                targetLabel = groups.get(lastSupplierGroupKey)!.label;
            }

            if (!groups.has(targetKey)) {
                groups.set(targetKey, {
                    key: targetKey,
                    label: targetLabel,
                    clarifications: [],
                });
            }

            groups.get(targetKey)!.clarifications.push(item);

            if (item.type !== 'answer') {
                lastSupplierGroupKey = targetKey;
            }
        });

        return Array.from(groups.values());
    }, [clarifications]);

    const questionForm = useForm<z.infer<typeof clarifySchema>>({
        resolver: zodResolver(clarifySchema),
        defaultValues: { message: '' },
    });

    const answerForm = useForm<z.infer<typeof clarifySchema>>({
        resolver: zodResolver(clarifySchema),
        defaultValues: { message: '' },
    });

    const [questionAttachments, setQuestionAttachments] = useState<File[]>([]);
    const [answerAttachments, setAnswerAttachments] = useState<File[]>([]);

    const handleQuestionFilesChange = (event: ChangeEvent<HTMLInputElement>) => {
        const files = event.target.files;

        if (!files) {
            return;
        }

        setQuestionAttachments((current) => [...current, ...Array.from(files)]);
        event.target.value = '';
    };

    const handleAnswerFilesChange = (event: ChangeEvent<HTMLInputElement>) => {
        const files = event.target.files;

        if (!files) {
            return;
        }

        setAnswerAttachments((current) => [...current, ...Array.from(files)]);
        event.target.value = '';
    };

    const handleRemoveQuestionAttachment = (index: number) => {
        setQuestionAttachments((current) => current.filter((_, idx) => idx !== index));
    };

    const handleRemoveAnswerAttachment = (index: number) => {
        setAnswerAttachments((current) => current.filter((_, idx) => idx !== index));
    };

    const handleQuestionSubmit = questionForm.handleSubmit(async (values) => {
        try {
            await onAskQuestion({
                message: values.message,
                attachments: questionAttachments,
            });
            questionForm.reset();
            setQuestionAttachments([]);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to submit clarification question.';
            publishToast({
                variant: 'destructive',
                title: 'Question failed',
                description: message,
            });
        }
    });

    const handleAnswerSubmit = answerForm.handleSubmit(async (values) => {
        try {
            await onAnswerQuestion({
                message: values.message,
                attachments: answerAttachments,
            });
            answerForm.reset();
            setAnswerAttachments([]);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to submit clarification answer.';
            publishToast({
                variant: 'destructive',
                title: 'Answer failed',
                description: message,
            });
        }
    });

    return (
        <div className="grid gap-4">
            <Card>
                <CardHeader>
                    <CardTitle>Clarification history</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4">
                    {groupedClarifications.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No clarifications yet. Be the first to ask a question.</p>
                    ) : (
                        <div className="space-y-4">
                            {groupedClarifications.map((group) => {
                                return (
                                    <section key={group.key} className="space-y-3 rounded-lg border p-4">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-sm font-semibold text-foreground">{group.label}</p>
                                            <span className="text-xs uppercase tracking-wide text-muted-foreground">
                                                {group.clarifications.length} message{group.clarifications.length === 1 ? '' : 's'}
                                            </span>
                                        </div>
                                        <div className="space-y-3">
                                            {group.clarifications.map((item) => {
                                                const createdAtDate = resolveClarificationDate(item.createdAt);
                                                return (
                                                    <article key={item.id} className="rounded-md border border-dashed p-3">
                                                        <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                                            <span className="uppercase tracking-wide">{item.type}</span>
                                                            <span>
                                                                {createdAtDate
                                                                    ? formatDistanceToNow(createdAtDate, { addSuffix: true })
                                                                    : 'Timestamp unavailable'}
                                                            </span>
                                                        </div>
                                                        <p className="mt-2 whitespace-pre-line text-sm">{resolveClarificationBody(item)}</p>
                                                        {renderClarificationAttachments(item.attachments)}
                                                        {item.author && typeof item.author === 'object' ? (
                                                            <p className="mt-2 text-xs text-muted-foreground">
                                                                {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                                                                {(item.author as any)?.name ?? 'Unknown author'}
                                                            </p>
                                                        ) : null}
                                                    </article>
                                                );
                                            })}
                                        </div>
                                    </section>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Ask a supplier question</CardTitle>
                </CardHeader>
                <CardContent>
                    <form className="grid gap-3" onSubmit={handleQuestionSubmit}>
                        <Textarea
                            placeholder="Clarify scope, timelines, or documentation expectations."
                            rows={4}
                            {...questionForm.register('message')}
                            disabled={!canAskQuestion || isSubmittingQuestion}
                        />
                        {questionForm.formState.errors.message ? (
                            <p className="text-sm text-destructive">{questionForm.formState.errors.message.message}</p>
                        ) : null}
                        <div className="grid gap-2">
                            <label className="text-sm font-medium text-foreground">Attachments</label>
                            <Input
                                type="file"
                                multiple
                                accept=".pdf,.png,.jpg,.jpeg"
                                onChange={handleQuestionFilesChange}
                                disabled={!canAskQuestion || isSubmittingQuestion}
                            />
                            <p className="text-xs text-muted-foreground">
                                Upload PDF or image files up to 10 MB. Attachments are virus-scanned before suppliers can access them.
                            </p>
                            {questionAttachments.length > 0 ? (
                                <ul className="space-y-1 text-sm">
                                    {questionAttachments.map((file, index) => (
                                        <li key={`${file.name}-${index}`} className="flex items-center justify-between gap-2 rounded-md border border-dashed px-2 py-1">
                                            <span className="truncate">
                                                {file.name} • {formatFileSize(file.size)}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleRemoveQuestionAttachment(index)}
                                                aria-label={`Remove ${file.name}`}
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                        </div>
                        <Button
                            type="submit"
                            disabled={isSubmittingQuestion || !canAskQuestion}
                            className="justify-self-start"
                        >
                            Post question
                        </Button>
                        {!canAskQuestion ? (
                            <p className="text-xs text-muted-foreground">Upgrade plan access to post new questions.</p>
                        ) : null}
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Respond to a supplier</CardTitle>
                </CardHeader>
                <CardContent>
                    <form className="grid gap-3" onSubmit={handleAnswerSubmit}>
                        <Textarea
                            placeholder="Provide additional details or attach a clarification notice."
                            rows={4}
                            {...answerForm.register('message')}
                            disabled={!canAnswerQuestion || isSubmittingAnswer}
                        />
                        {answerForm.formState.errors.message ? (
                            <p className="text-sm text-destructive">{answerForm.formState.errors.message.message}</p>
                        ) : null}
                        <div className="grid gap-2">
                            <label className="text-sm font-medium text-foreground">Attachments</label>
                            <Input
                                type="file"
                                multiple
                                accept=".pdf,.png,.jpg,.jpeg"
                                onChange={handleAnswerFilesChange}
                                disabled={!canAnswerQuestion || isSubmittingAnswer}
                            />
                            <p className="text-xs text-muted-foreground">
                                Attach amended drawings or documents. Files are scanned automatically before recipients are notified.
                            </p>
                            {answerAttachments.length > 0 ? (
                                <ul className="space-y-1 text-sm">
                                    {answerAttachments.map((file, index) => (
                                        <li key={`${file.name}-${index}`} className="flex items-center justify-between gap-2 rounded-md border border-dashed px-2 py-1">
                                            <span className="truncate">
                                                {file.name} • {formatFileSize(file.size)}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleRemoveAnswerAttachment(index)}
                                                aria-label={`Remove ${file.name}`}
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                        </div>
                        <Button
                            type="submit"
                            disabled={isSubmittingAnswer || !canAnswerQuestion}
                            className="justify-self-start"
                        >
                            Post answer
                        </Button>
                        {!canAnswerQuestion ? (
                            <p className="text-xs text-muted-foreground">Upgrade plan access to answer supplier questions.</p>
                        ) : null}
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
