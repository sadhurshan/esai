import { useId, useMemo, useState } from 'react';
import { AlertCircle, Check, Copy, ExternalLink, Filter, HelpCircle, Sparkles, Tag, X } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { errorToast, successToast } from '@/components/toasts';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { ApiError, api } from '@/lib/api';
import { cn } from '@/lib/utils';
import {
    answerQuestion,
    type AnswerQuestionPayload,
    type AnswerQuestionResponse,
    type SemanticSearchHit,
} from '@/services/ai';

const SOURCE_TYPE_OPTIONS: Array<{ value: string; label: string }> = [
    { value: '', label: 'All sources' },
    { value: 'document_control', label: 'Document Control Hub' },
    { value: 'maintenance_manual', label: 'Maintenance manuals' },
    { value: 'rfq', label: 'RFQ text' },
];

const DEFAULT_TOP_K = 6;

type DocumentResourcePayload = {
    id: number;
    filename?: string | null;
    download_url?: string | null;
};

interface CopilotAnswerPanelProps {
    className?: string;
    defaultSourceType?: string;
}

export function CopilotAnswerPanel({ className, defaultSourceType = '' }: CopilotAnswerPanelProps) {
    const questionFieldId = useId();
    const tagFieldId = useId();

    const [question, setQuestion] = useState('');
    const [sourceType, setSourceType] = useState(defaultSourceType);
    const [tags, setTags] = useState<string[]>([]);
    const [tagInput, setTagInput] = useState('');
    const [isGenerating, setIsGenerating] = useState(false);
    const [answerMarkdown, setAnswerMarkdown] = useState<string | null>(null);
    const [citations, setCitations] = useState<SemanticSearchHit[]>([]);
    const [needsHumanReview, setNeedsHumanReview] = useState(false);
    const [warnings, setWarnings] = useState<string[]>([]);
    const [confidence, setConfidence] = useState<number | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [hasGenerated, setHasGenerated] = useState(false);
    const [openingDocId, setOpeningDocId] = useState<string | null>(null);
    const [copied, setCopied] = useState<'answer' | 'citations' | null>(null);

    const tagInputPlaceholder = useMemo(() => {
        return tags.length === 0 ? 'Add tags to limit context (press Enter)' : 'Press Enter to add tag';
    }, [tags.length]);

    const answerLines = useMemo(() => {
        if (!answerMarkdown) {
            return [] as string[];
        }

        return answerMarkdown
            .split(/\n+/)
            .map((line) => line.trim().replace(/^[-*]\s*/, ''))
            .filter((line) => line.length > 0);
    }, [answerMarkdown]);

    const handleAddTag = () => {
        const nextValue = tagInput.trim().toLowerCase();

        if (!nextValue || tags.includes(nextValue)) {
            setTagInput('');
            return;
        }

        setTags((prev) => [...prev, nextValue]);
        setTagInput('');
    };

    const handleRemoveTag = (tag: string) => {
        setTags((prev) => prev.filter((value) => value !== tag));
    };

    const handleReset = () => {
        setSourceType(defaultSourceType);
        setTags([]);
        setTagInput('');
        setAnswerMarkdown(null);
        setCitations([]);
        setNeedsHumanReview(false);
        setWarnings([]);
        setConfidence(null);
        setErrorMessage(null);
        setHasGenerated(false);
        setCopied(null);
    };

    const handleGenerate = async (event?: React.FormEvent) => {
        event?.preventDefault();

        if (!question.trim()) {
            errorToast('Enter a question', 'Type a question so Copilot can synthesize an answer.');
            return;
        }

        setIsGenerating(true);
        setErrorMessage(null);
        setHasGenerated(true);
        setCopied(null);

        try {
            const payload: AnswerQuestionPayload = {
                query: question.trim(),
                top_k: DEFAULT_TOP_K,
                allow_general: true,
            };

            if (sourceType || tags.length > 0) {
                payload.filters = {
                    ...(sourceType ? { source_type: sourceType } : {}),
                    ...(tags.length > 0 ? { tags } : {}),
                };
            }

            const response = await answerQuestion<AnswerQuestionResponse>(payload);
            const data = response.data ?? null;

            if (!data) {
                throw new ApiError('Copilot returned an empty response. Try again.');
            }

            const markdown = typeof data.answer_markdown === 'string' ? data.answer_markdown.trim() : null;
            const legacyAnswer = typeof data.answer === 'string' ? data.answer.trim() : null;
            const normalizedAnswer = markdown && markdown.length > 0 ? markdown : legacyAnswer;
            setAnswerMarkdown(normalizedAnswer && normalizedAnswer.length > 0 ? normalizedAnswer : null);
            setCitations(Array.isArray(data.citations) ? data.citations : []);
            setNeedsHumanReview(Boolean(data.needs_human_review));
            setWarnings(
                Array.isArray(data.warnings)
                    ? data.warnings
                          .map((warning) => (typeof warning === 'string' ? warning.trim() : ''))
                          .filter((warning) => warning.length > 0)
                    : [],
            );
            const rawConfidence = typeof data.confidence === 'number' ? data.confidence : null;
            setConfidence(rawConfidence === null ? null : Math.min(1, Math.max(0, rawConfidence)));
        } catch (error) {
            const message = error instanceof ApiError ? error.message : 'Unable to generate an answer right now.';
            setErrorMessage(message);
            errorToast('Copilot failed to respond', message);
        } finally {
            setIsGenerating(false);
        }
    };

    const handleOpenDocument = async (docId: string) => {
        if (!docId) {
            errorToast('Missing document id', 'This citation does not include a valid document id.');
            return;
        }

        const numericId = Number(docId);

        if (!Number.isFinite(numericId)) {
            errorToast('Invalid document id', 'Document ids must be numeric.');
            return;
        }

        setOpeningDocId(docId);

        try {
            // Axios response interceptor unwraps the payload, so assert the concrete document type for TS.
            const document = (await api.get<DocumentResourcePayload>(`/documents/${numericId}`)) as unknown as
                | DocumentResourcePayload
                | null;
            const downloadUrl = document?.download_url;

            if (!downloadUrl) {
                throw new ApiError('Document is missing a download URL.');
            }

            window.open(downloadUrl, '_blank', 'noopener');
        } catch (error) {
            const message = error instanceof ApiError ? error.message : 'Unable to open the cited document.';
            errorToast('Document not available', message);
        } finally {
            setOpeningDocId(null);
        }
    };

    const handleCopy = async (type: 'answer' | 'citations') => {
        const text =
            type === 'answer'
                ? answerMarkdown?.trim()
                : formatCitationsForCopy(citations);

        if (!text) {
            errorToast(
                'Nothing to copy',
                type === 'answer' ? 'Generate an answer first.' : 'No citations available to copy yet.',
            );
            return;
        }

        if (typeof navigator === 'undefined' || !navigator.clipboard) {
            errorToast('Clipboard unavailable', 'Your browser blocked clipboard access.');
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
            successToast(type === 'answer' ? 'Answer copied' : 'Citations copied');
            setCopied(type);
            window.setTimeout(() => {
                setCopied((current) => (current === type ? null : current));
            }, 2000);
        } catch (copyError) {
            errorToast('Copy failed', 'Your browser blocked clipboard access.');
        }
    };

    const renderAnswer = () => {
        if (isGenerating) {
            return <AnswerSkeleton />;
        }

        if (!hasGenerated) {
            return (
                <EmptyState
                    title="Need insight?"
                    description="Ask Copilot to summarize maintenance manuals, Document Control Hub files, or RFQ history."
                    icon={<HelpCircle className="size-6" />}
                    ctaLabel="Try a sample question"
                    ctaProps={{
                        onClick: () => setQuestion('What torque should I use on the CR-45 spindle assembly?'),
                    }}
                />
            );
        }

        if (!answerMarkdown) {
            return (
                <EmptyState
                    title="No answer returned"
                    description="Copilot could not find relevant snippets. Refine the question or broaden the filters."
                    icon={<AlertCircle className="size-6" />}
                    className="bg-transparent"
                />
            );
        }

        return (
            <div className="space-y-4">
                <div className="rounded-xl border bg-card/70 p-4 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-sm font-semibold text-muted-foreground">Answer + sources</p>
                            <div className="mt-2 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide">
                                {needsHumanReview ? (
                                    <Badge variant="destructive" className="bg-amber-100 text-amber-900 hover:bg-amber-100">
                                        Verify sources
                                    </Badge>
                                ) : null}
                                {confidence !== null ? (
                                    <Badge variant="outline">Confidence {(confidence * 100).toFixed(0)}%</Badge>
                                ) : null}
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                onClick={() => void handleCopy('answer')}
                                disabled={!answerMarkdown}
                                className="inline-flex items-center gap-1"
                            >
                                {copied === 'answer' ? (
                                    <>
                                        <Check className="size-3" /> Copied
                                    </>
                                ) : (
                                    <>
                                        <Copy className="size-3" /> Copy answer
                                    </>
                                )}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => void handleCopy('citations')}
                                disabled={citations.length === 0}
                                className="inline-flex items-center gap-1"
                            >
                                {copied === 'citations' ? (
                                    <>
                                        <Check className="size-3" /> Copied
                                    </>
                                ) : (
                                    <>
                                        <Copy className="size-3" /> Copy citations
                                    </>
                                )}
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4 text-sm leading-relaxed text-foreground">
                        {answerLines.length > 0 ? (
                            <ul className="list-disc space-y-2 pl-5">
                                {answerLines.map((line, index) => (
                                    <li key={`${line}-${index}`}>{line}</li>
                                ))}
                            </ul>
                        ) : (
                            <p className="whitespace-pre-wrap">{answerMarkdown}</p>
                        )}
                    </div>
                </div>

                {warnings.length > 0 ? (
                    <Alert variant="warning">
                        <AlertTitle>Manual review recommended</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc space-y-1 pl-5 text-sm">
                                {warnings.map((warning) => (
                                    <li key={warning}>{warning}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="space-y-2">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground/80">
                        Citations ({citations.length})
                    </p>
                    {citations.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Copilot did not return any supporting snippets. Try widening your filters.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {citations.map((citation) => {
                                const key = `${citation.doc_id}:${citation.chunk_id ?? 'chunk'}`;
                                const title = citation.title?.trim() || `Document ${citation.doc_id}`;

                                return (
                                    <div key={key} className="rounded-xl border bg-muted/20 p-4">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="text-sm font-semibold text-foreground">{title}</p>
                                            <Badge variant="outline" className="uppercase">
                                                {sourceLabel(citation) ?? 'Source'}
                                            </Badge>
                                            {citation.doc_version ? (
                                                <Badge variant="secondary">v{citation.doc_version}</Badge>
                                            ) : null}
                                            {citation.chunk_id ? (
                                                <Badge variant="secondary">Chunk {citation.chunk_id}</Badge>
                                            ) : null}
                                        </div>
                                        <p className="mt-2 text-sm text-muted-foreground">{citation.snippet}</p>
                                        <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                            <span>Doc #{citation.doc_id}</span>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="inline-flex items-center gap-1"
                                                onClick={() => void handleOpenDocument(citation.doc_id)}
                                                disabled={openingDocId === citation.doc_id}
                                            >
                                                {openingDocId === citation.doc_id ? (
                                                    <>
                                                        <Spinner className="size-3" /> Opening…
                                                    </>
                                                ) : (
                                                    <>
                                                        <ExternalLink className="size-3" /> Open document
                                                    </>
                                                )}
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        );
    };

    return (
        <Card className={cn('h-full', className)}>
            <CardHeader>
                <CardTitle>Ask Copilot</CardTitle>
                <CardDescription>
                    Generate an answer with citations pulled directly from indexed documents. Always validate the cited
                    sources before applying changes.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <form className="space-y-4" onSubmit={handleGenerate}>
                    <div className="flex flex-col gap-3 md:flex-row md:items-end">
                        <div className="flex-1">
                            <label htmlFor={questionFieldId} className="text-sm font-medium text-foreground">
                                Question
                            </label>
                            <div className="relative mt-1">
                                <Sparkles className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id={questionFieldId}
                                    placeholder="e.g. What torque spec applies to CR-45 spindle assembly?"
                                    value={question}
                                    onChange={(event) => setQuestion(event.target.value)}
                                    className="pl-10"
                                />
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" disabled={isGenerating}>
                                {isGenerating ? (
                                    <>
                                        <Spinner className="mr-2 size-4" /> Generating…
                                    </>
                                ) : (
                                    'Generate answer'
                                )}
                            </Button>
                            <Button type="button" variant="outline" onClick={handleReset} disabled={isGenerating}>
                                Reset
                            </Button>
                        </div>
                    </div>

                    <div className="rounded-xl border bg-muted/20 p-4">
                        <div className="flex items-center gap-2 text-sm font-semibold text-muted-foreground">
                            <Filter className="size-4" /> Filters
                        </div>
                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">Source type</label>
                                <Select value={sourceType} onValueChange={setSourceType}>
                                    <SelectTrigger aria-label="Document source">
                                        <SelectValue placeholder="All sources" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {SOURCE_TYPE_OPTIONS.map((option) => (
                                            <SelectItem key={option.value || 'all'} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-2">
                                    <label htmlFor={tagFieldId} className="text-sm font-medium text-foreground">
                                        Document tags
                                    </label>
                                    {tags.length > 0 ? (
                                        <button
                                            type="button"
                                            onClick={() => setTags([])}
                                            className="text-xs font-medium text-primary"
                                        >
                                            Clear tags
                                        </button>
                                    ) : null}
                                </div>
                                <div className="flex gap-2">
                                    <div className="relative flex-1">
                                        <Tag className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            id={tagFieldId}
                                            value={tagInput}
                                            onChange={(event) => setTagInput(event.target.value)}
                                            onKeyDown={(event) => {
                                                if (event.key === 'Enter') {
                                                    event.preventDefault();
                                                    handleAddTag();
                                                }
                                            }}
                                            placeholder={tagInputPlaceholder}
                                            className="pl-10"
                                        />
                                    </div>
                                    <Button type="button" variant="outline" onClick={handleAddTag}>
                                        Add
                                    </Button>
                                </div>
                                {tags.length > 0 ? (
                                    <div className="flex flex-wrap gap-2">
                                        {tags.map((tag) => (
                                            <Badge key={tag} variant="secondary" className="flex items-center gap-1">
                                                {tag}
                                                <button
                                                    type="button"
                                                    onClick={() => handleRemoveTag(tag)}
                                                    className="rounded-full p-0.5 text-muted-foreground hover:text-foreground"
                                                    aria-label={`Remove tag ${tag}`}
                                                >
                                                    <X className="size-3" />
                                                </button>
                                            </Badge>
                                        ))}
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </form>

                {errorMessage ? (
                    <Alert variant="destructive">
                        <AlertDescription>{errorMessage}</AlertDescription>
                    </Alert>
                ) : null}

                {renderAnswer()}

                <Alert>
                    <AlertDescription>
                        AI suggestions must be verified against source documents. Never apply changes automatically without
                        reviewing the cited evidence.
                    </AlertDescription>
                </Alert>
            </CardContent>
        </Card>
    );
}

function sourceLabel(hit: SemanticSearchHit): string | null {
    const raw = (hit.metadata?.source_type as string | undefined) ?? '';

    if (!raw) {
        return null;
    }

    switch (raw) {
        case 'document_control':
            return 'Document Control Hub';
        case 'maintenance_manual':
            return 'Maintenance manual';
        case 'rfq':
            return 'RFQ';
        default:
            return raw;
    }
}

function formatCitationsForCopy(citations: SemanticSearchHit[]): string | null {
    if (citations.length === 0) {
        return null;
    }

    const rows = citations.map((citation, index) => {
        const snippet = typeof citation.snippet === 'string' ? citation.snippet.replace(/\s+/g, ' ').trim() : '';
        const versionLabel = citation.doc_version ? `v${citation.doc_version}` : 'no version';
        const chunkLabel = citation.chunk_id ?? 'n/a';

        return `${index + 1}. Doc ${citation.doc_id} ${versionLabel} · Chunk ${chunkLabel} · ${snippet}`.trim();
    });

    return rows.join('\n');
}

function AnswerSkeleton() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 3 }).map((_, index) => (
                <div key={index} className="rounded-xl border bg-card/40 p-4">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="mt-2 h-4 w-3/4" />
                    <Skeleton className="mt-2 h-4 w-2/3" />
                    <Skeleton className="mt-2 h-4 w-1/2" />
                </div>
            ))}
        </div>
    );
}
