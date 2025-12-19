import { useId, useMemo, useState } from 'react';
import { ExternalLink, Filter, Search, Sparkles, Tag, X } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { errorToast } from '@/components/toasts';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
    semanticSearch,
    type SemanticSearchHit,
    type SemanticSearchPayload,
    type SemanticSearchResponse,
} from '@/services/ai';

const SOURCE_TYPE_OPTIONS: Array<{ value: string; label: string }> = [
    { value: '', label: 'All sources' },
    { value: 'document_control', label: 'Document Control Hub' },
    { value: 'maintenance_manual', label: 'Maintenance manuals' },
    { value: 'rfq', label: 'RFQ text' },
];

const DEFAULT_TOP_K = 8;

type DocumentResourcePayload = {
    id: number;
    filename?: string | null;
    download_url?: string | null;
};

interface CopilotSearchPanelProps {
    className?: string;
    defaultSourceType?: string;
}

export function CopilotSearchPanel({ className, defaultSourceType = '' }: CopilotSearchPanelProps) {
    const queryFieldId = useId();
    const tagFieldId = useId();

    const [query, setQuery] = useState('');
    const [sourceType, setSourceType] = useState(defaultSourceType);
    const [tags, setTags] = useState<string[]>([]);
    const [tagInput, setTagInput] = useState('');
    const [hits, setHits] = useState<SemanticSearchHit[]>([]);
    const [expandedHits, setExpandedHits] = useState<Set<string>>(new Set());
    const [isSearching, setIsSearching] = useState(false);
    const [hasSearched, setHasSearched] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [openingDocId, setOpeningDocId] = useState<string | null>(null);

    const tagInputPlaceholder = useMemo(() => {
        return tags.length === 0 ? 'Add tags to narrow by metadata (press Enter)' : 'Press Enter to add tag';
    }, [tags.length]);

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

    const handleResetFilters = () => {
        setSourceType(defaultSourceType);
        setTags([]);
        setTagInput('');
    };

    const handleSearch = async (event?: React.FormEvent) => {
        event?.preventDefault();

        if (!query.trim()) {
            errorToast('Enter a search query', 'Type a question or keyword to run semantic search.');
            return;
        }

        setIsSearching(true);
        setHasSearched(true);
        setErrorMessage(null);

        try {
            const payload: SemanticSearchPayload = {
                query: query.trim(),
                top_k: DEFAULT_TOP_K,
            };

            if (sourceType || tags.length > 0) {
                payload.filters = {
                    ...(sourceType ? { source_type: sourceType } : {}),
                    ...(tags.length > 0 ? { tags } : {}),
                };
            }

            const response = await semanticSearch<SemanticSearchResponse>(payload);
            const data = response.data ?? { hits: [] };
            const nextHits = Array.isArray(data.hits) ? data.hits : [];

            setHits(nextHits);
            setExpandedHits(new Set());
        } catch (error) {
            const message = error instanceof ApiError ? error.message : 'Semantic search failed. Please retry.';
            setErrorMessage(message);
            errorToast('Unable to complete search', message);
        } finally {
            setIsSearching(false);
        }
    };

    const toggleExpanded = (key: string) => {
        setExpandedHits((prev) => {
            const next = new Set(prev);
            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
    };

    const handleOpenDocument = async (docId: string) => {
        if (!docId) {
            errorToast('Missing document id', 'This search hit did not include a document id.');
            return;
        }

        const numericId = Number(docId);

        if (!Number.isFinite(numericId)) {
            errorToast('Invalid document id', 'Document ids must be numeric.');
            return;
        }

        setOpeningDocId(docId);

        try {
            // Use the existing DocumentController route to fetch a signed download URL when opening a source.
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
            const message = error instanceof ApiError ? error.message : 'Unable to open the selected document.';
            errorToast('Document not available', message);
        } finally {
            setOpeningDocId(null);
        }
    };

    const renderResults = () => {
        if (isSearching) {
            return <SearchSkeleton />;
        }

        if (!hasSearched) {
            return (
                <EmptyState
                    title="Search your document universe"
                    description="Ask a question or search by keyword. Copilot will scan Document Control Hub files, maintenance manuals, and RFQ text."
                    icon={<Sparkles className="size-6" />}
                    ctaLabel="Try a sample query"
                    ctaProps={{
                        onClick: () => setQuery('Show me torque specs for assembly CR-45'),
                    }}
                />
            );
        }

        if (hits.length === 0) {
            return (
                <EmptyState
                    title="No semantic matches"
                    description="Try a broader query, remove filters, or add additional tags to help Copilot pivot to the right documents."
                    icon={<Search className="size-6" />}
                    className="bg-transparent"
                />
            );
        }

        return (
            <div className="space-y-3">
                {hits.map((hit) => {
                    const key = `${hit.doc_id}:${hit.chunk_id ?? 'chunk'}`;
                    const isExpanded = expandedHits.has(key);
                    const metadataEntries = Object.entries(hit.metadata ?? {});
                    const formattedScore = formatScore(hit.score);
                    const title = hit.title?.trim() || `Document ${hit.doc_id}`;

                    return (
                        <div
                            key={key}
                            className="rounded-xl border bg-card/70 p-4 shadow-sm transition-all hover:border-primary/40"
                            role="button"
                            tabIndex={0}
                            aria-expanded={isExpanded}
                            onClick={() => toggleExpanded(key)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter' || event.key === ' ') {
                                    event.preventDefault();
                                    toggleExpanded(key);
                                }
                            }}
                        >
                            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div className="space-y-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline" className="text-xs uppercase tracking-wide">
                                            {formattedScore}
                                        </Badge>
                                        <p className="text-base font-semibold text-foreground">{title}</p>
                                    </div>
                                    <p className="text-sm text-muted-foreground">{hit.snippet}</p>
                                    <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                        <Badge variant="secondary">Doc #{hit.doc_id}</Badge>
                                        {hit.doc_version ? (
                                            <Badge variant="secondary">v{hit.doc_version}</Badge>
                                        ) : null}
                                        {hit.chunk_id ? (
                                            <Badge variant="secondary">Chunk {hit.chunk_id}</Badge>
                                        ) : null}
                                        {sourceLabel(hit) ? (
                                            <Badge variant="outline" className="uppercase">
                                                {sourceLabel(hit)}
                                            </Badge>
                                        ) : null}
                                    </div>
                                    <p className="text-xs font-medium text-primary">
                                        {isExpanded ? 'Hide metadata' : 'Click to view metadata & filters'}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="shrink-0"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        void handleOpenDocument(hit.doc_id);
                                    }}
                                    disabled={openingDocId === hit.doc_id}
                                >
                                    {openingDocId === hit.doc_id ? (
                                        <>
                                            <Spinner className="mr-2 size-4" /> Opening…
                                        </>
                                    ) : (
                                        <>
                                            <ExternalLink className="mr-2 size-4" /> Open document
                                        </>
                                    )}
                                </Button>
                            </div>
                            {isExpanded && metadataEntries.length > 0 ? (
                                <dl className="mt-4 grid gap-2 text-sm text-muted-foreground md:grid-cols-2">
                                    {metadataEntries.map(([key, value]) => (
                                        <div key={`${key}-${String(value)}`} className="rounded-lg border bg-background/80 p-3">
                                            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground/80">
                                                {formatMetadataKey(key)}
                                            </dt>
                                            <dd className="mt-1 text-sm text-foreground">
                                                {formatMetadataValue(value)}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            ) : null}
                        </div>
                    );
                })}
            </div>
        );
    };

    return (
        <Card className={cn('h-full', className)}>
            <CardHeader>
                <CardTitle>Copilot Search</CardTitle>
                <CardDescription>
                    Semantic search across Document Control Hub, maintenance manuals, and RFQ text. Review the cited
                    snippets before taking action.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <form className="space-y-4" onSubmit={handleSearch}>
                    <div className="flex flex-col gap-3 md:flex-row md:items-end">
                        <div className="flex-1">
                            <label htmlFor={queryFieldId} className="text-sm font-medium text-foreground">
                                Search query
                            </label>
                            <div className="relative mt-1">
                                <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id={queryFieldId}
                                    placeholder="e.g. torque spec for CR-45 assembly"
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    className="pl-10"
                                />
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" disabled={isSearching}>
                                {isSearching ? (
                                    <>
                                        <Spinner className="mr-2 size-4" /> Searching…
                                    </>
                                ) : (
                                    'Search'
                                )}
                            </Button>
                            <Button type="button" variant="outline" onClick={handleResetFilters} disabled={isSearching}>
                                Reset filters
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

                {renderResults()}

                <p className="text-xs text-muted-foreground">
                    AI suggestions list citations only. Always open the referenced source document to validate the
                    snippet before changing RFQs, maintenance plans, or orders.
                </p>
            </CardContent>
        </Card>
    );
}

function formatScore(score?: number | null): string {
    if (typeof score !== 'number' || Number.isNaN(score)) {
        return 'Relevance —';
    }

    const percentage = Math.max(0, Math.min(1, score)) * 100;

    return `Relevance ${percentage.toFixed(1)}%`;
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

function formatMetadataKey(key: string): string {
    return key
        .replace(/_/g, ' ')
        .split(' ')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function formatMetadataValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (Array.isArray(value)) {
        return value.map((entry) => formatMetadataValue(entry)).join(', ');
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function SearchSkeleton() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 3 }).map((_, index) => (
                <div key={index} className="rounded-xl border bg-card/40 p-4">
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="mt-2 h-5 w-2/3" />
                    <Skeleton className="mt-2 h-16 w-full" />
                </div>
            ))}
        </div>
    );
}
