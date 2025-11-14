import { formatDistanceToNow } from 'date-fns';
import { useMemo } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import type { RfqClarification } from '@/sdk';

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

const clarifySchema = z.object({
    body: z.string().min(1, 'Message cannot be empty.'),
});

export interface ClarificationThreadProps {
    clarifications: RfqClarification[];
    onAskQuestion: (body: string) => Promise<unknown>;
    onAnswerQuestion: (body: string) => Promise<unknown>;
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
        defaultValues: { body: '' },
    });

    const answerForm = useForm<z.infer<typeof clarifySchema>>({
        resolver: zodResolver(clarifySchema),
        defaultValues: { body: '' },
    });

    const handleQuestionSubmit = questionForm.handleSubmit(async (values) => {
        try {
            await onAskQuestion(values.body);
            questionForm.reset();
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
            await onAnswerQuestion(values.body);
            answerForm.reset();
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
                                                        <p className="mt-2 whitespace-pre-line text-sm">{item.body}</p>
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
                            {...questionForm.register('body')}
                            disabled={!canAskQuestion || isSubmittingQuestion}
                        />
                        {questionForm.formState.errors.body ? (
                            <p className="text-sm text-destructive">{questionForm.formState.errors.body.message}</p>
                        ) : null}
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
                            {...answerForm.register('body')}
                            disabled={!canAnswerQuestion || isSubmittingAnswer}
                        />
                        {answerForm.formState.errors.body ? (
                            <p className="text-sm text-destructive">{answerForm.formState.errors.body.message}</p>
                        ) : null}
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
