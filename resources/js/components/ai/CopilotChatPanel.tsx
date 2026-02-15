import {
    AlertTriangle,
    Bot,
    ChevronDown,
    GitBranch,
    Loader2,
    Send,
    Sparkles,
    User,
} from 'lucide-react';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type FormEvent,
    type KeyboardEvent,
    type ReactNode,
} from 'react';

import { AnalyticsCard } from '@/components/ai/AnalyticsCard';
import { ReviewChecklist } from '@/components/ai/ReviewChecklist';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import { useAiChatMessages } from '@/hooks/api/ai/use-ai-chat-messages';
import { useAiChatSend } from '@/hooks/api/ai/use-ai-chat-send';
import {
    useAiChatThreads,
    useCreateAiChatThread,
} from '@/hooks/api/ai/use-ai-chat-threads';
import {
    useAiDraftApprove,
    useAiDraftReject,
} from '@/hooks/api/ai/use-ai-draft-approval';
import {
    useStartAiWorkflow,
    type StartAiWorkflowVariables,
} from '@/hooks/api/ai/use-start-ai-workflow';
import { cn } from '@/lib/utils';
import type {
    AiAnalyticsChartDatum,
    AiAnalyticsCitation,
    AiAnalyticsMetric,
} from '@/types/ai-analytics';
import type {
    AiActionDraft,
    AiActionDraftStatus,
    AiChatAssistantResponse,
    AiChatClarificationPrompt,
    AiChatDraftSnapshot,
    AiChatEntityPickerCandidate,
    AiChatEntityPickerPrompt,
    AiChatGuidedResolution,
    AiChatMessage,
    AiChatMessageContextPayload,
    AiChatResponseType,
    AiChatUnsafeActionPrompt,
    AiChatWorkflowSuggestion,
} from '@/types/ai-chat';

interface CopilotChatPanelProps {
    className?: string;
}

const RESPONSE_LABELS: Record<string, string> = {
    answer: 'Grounded answer',
    draft_action: 'Draft ready',
    unsafe_action_confirmation: 'Confirm action',
    workflow_suggestion: 'Workflow suggestion',
    guided_resolution: 'Guided resolution',
    clarification: 'Clarification needed',
    entity_picker: 'Select a record',
    tool_request: 'Workspace lookup needed',
    error: 'Unable to respond',
    review_rfq: 'RFQ review',
    review_quote: 'Quote review',
    review_po: 'PO review',
    review_invoice: 'Invoice review',
};

const FALLBACK_THREAD_TITLE = 'New conversation';

const ANALYTICS_RESPONSE_TYPES = new Set<AiChatResponseType>([
    'analytics',
    'forecast_spend',
    'forecast_supplier_performance',
    'forecast_inventory',
]);

const KNOWN_ANALYTICS_METRICS: ReadonlyArray<AiAnalyticsMetric> = [
    'cycle_time',
    'otif',
    'response_rate',
    'spend',
    'forecast_accuracy',
    'forecast_spend',
    'forecast_supplier_performance',
    'forecast_inventory',
];

const TOOL_TO_ANALYTICS_METRIC: Record<string, AiAnalyticsMetric> = {
    tool_forecast_spend: 'forecast_spend',
    tool_forecast_supplier_performance: 'forecast_supplier_performance',
    tool_forecast_inventory: 'forecast_inventory',
};

const HELP_LOCALE_STORAGE_KEY = 'copilot.help.locale';
const HELP_LANGUAGE_OPTIONS: Array<{ value: string; label: string }> = [
    { value: 'en', label: 'English' },
    { value: 'es', label: 'Spanish' },
];

const GUIDE_LANGUAGE_LABELS: Record<string, string> = {
    en: 'English',
    es: 'Spanish',
};

type DraftActionState = {
    approvingId: number | null;
    rejectingId: number | null;
    isApprovePending: boolean;
    isRejectPending: boolean;
};

type AnalyticsCardViewModel = {
    metric: AiAnalyticsMetric | null;
    title: string;
    chartData: AiAnalyticsChartDatum[];
    summary?: string | null;
    citations?: AiAnalyticsCitation[];
    valueFormatter?: (value: number) => string;
};

export function CopilotChatPanel({ className }: CopilotChatPanelProps) {
    const [composerValue, setComposerValue] = useState('');
    const [activeThreadId, setActiveThreadId] = useState<number | null>(null);
    const [helpLocale, setHelpLocale] = useState<string>(() => {
        if (typeof window === 'undefined') {
            return 'en';
        }

        const stored = window.localStorage.getItem(HELP_LOCALE_STORAGE_KEY);

        return stored && stored.trim() !== '' ? stored : 'en';
    });
    const bootstrapAttempted = useRef(false);
    const scrollRef = useRef<HTMLDivElement>(null);

    const threadsQuery = useAiChatThreads();
    const createThread = useCreateAiChatThread({
        onSuccess: (thread) => {
            setActiveThreadId(thread.id);
            bootstrapAttempted.current = true;
        },
    });
    const resolvedThreadId =
        activeThreadId ?? threadsQuery.data?.items?.[0]?.id ?? null;
    // Pull the latest 60 messages for the selected thread once a thread id exists so the panel
    // always renders a bounded, tenant-scoped transcript.
    const messagesQuery = useAiChatMessages(resolvedThreadId, {
        limit: 60,
        enabled: Boolean(resolvedThreadId),
    });
    const sendMutation = useAiChatSend(resolvedThreadId);
    const isServiceUnavailable = threadsQuery.isError || messagesQuery.isError;
    const isSendLocked =
        sendMutation.isPending ||
        sendMutation.isStreaming ||
        isServiceUnavailable;
    const approveDraft = useAiDraftApprove(resolvedThreadId);
    const rejectDraft = useAiDraftReject(resolvedThreadId);
    const startWorkflow = useStartAiWorkflow(resolvedThreadId);

    const messages = useMemo(
        () => messagesQuery.data?.messages ?? [],
        [messagesQuery.data?.messages],
    );

    const visibleMessages = useMemo(() => {
        return messages.filter((message) => {
            // Tool role payloads and assistant tool_request stubs are kept in the timeline for auditing
            // but hidden from the bubble list so end users only see authored turns.
            if (message.role === 'tool') {
                return false;
            }

            if (message.role === 'assistant') {
                const response = extractAssistantResponse(message);
                return response?.type !== 'tool_request';
            }

            return true;
        });
    }, [messages]);

    useEffect(() => {
        if (bootstrapAttempted.current) {
            return;
        }

        if (
            threadsQuery.isLoading ||
            threadsQuery.isFetching ||
            createThread.isPending ||
            threadsQuery.isError
        ) {
            return;
        }

        if ((threadsQuery.data?.items?.length ?? 0) > 0) {
            return;
        }

        bootstrapAttempted.current = true;
        createThread.mutate(undefined, {
            onError: (error) => {
                publishToast({
                    variant: 'destructive',
                    title: 'Unable to start Copilot chat',
                    description:
                        error instanceof Error
                            ? error.message
                            : 'Please try again.',
                });
            },
        });
    }, [
        threadsQuery.isLoading,
        threadsQuery.isFetching,
        threadsQuery.isError,
        threadsQuery.data,
        createThread,
    ]);

    useEffect(() => {
        const node = scrollRef.current;
        if (!node) {
            return;
        }

        node.scrollTop = node.scrollHeight;
    }, [messages, isSendLocked]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        window.localStorage.setItem(HELP_LOCALE_STORAGE_KEY, helpLocale);
    }, [helpLocale]);

    const lastAssistantResponse = useMemo(() => {
        const assistant = [...messages].reverse().find((message) => {
            if (message.role !== 'assistant') {
                return false;
            }
            const response = extractAssistantResponse(message);
            return response?.type !== 'tool_request';
        });
        return assistant ? extractAssistantResponse(assistant) : null;
    }, [messages]);

    const quickReplies = lastAssistantResponse?.suggested_quick_replies ?? [];
    const needsHumanReview = Boolean(lastAssistantResponse?.needs_human_review);
    const reviewWarnings = lastAssistantResponse?.warnings ?? [];
    const approvingDraftId = approveDraft.isPending
        ? (approveDraft.variables?.draftId ?? null)
        : null;
    const rejectingDraftId = rejectDraft.isPending
        ? (rejectDraft.variables?.draftId ?? null)
        : null;
    const draftActionState = {
        approvingId: approvingDraftId,
        rejectingId: rejectingDraftId,
        isApprovePending: approveDraft.isPending,
        isRejectPending: rejectDraft.isPending,
    };

    const isThreadLoading = threadsQuery.isLoading || createThread.isPending;
    const isMessageLoading =
        Boolean(resolvedThreadId) && messagesQuery.isLoading;
    const showSkeleton = isThreadLoading || isMessageLoading;
    const showEmptyState =
        !showSkeleton && visibleMessages.length === 0 && !isServiceUnavailable;
    const activeThread = messagesQuery.data;
    const threadTitle = activeThread?.title ?? FALLBACK_THREAD_TITLE;
    const serviceErrorMessage = isServiceUnavailable
        ? threadsQuery.error instanceof Error
            ? threadsQuery.error.message
            : messagesQuery.error instanceof Error
              ? messagesQuery.error.message
              : 'The AI service is unavailable right now.'
        : null;

    const handleSend = async (preset?: string) => {
        if (!resolvedThreadId || isSendLocked) {
            return;
        }

        const rawValue = preset ?? composerValue;
        const trimmed = rawValue.trim();

        if (!trimmed) {
            return;
        }

        const previousDraft = composerValue;

        if (!preset) {
            setComposerValue('');
        }

        const contextPayload: AiChatMessageContextPayload = {
            locale: helpLocale,
        };

        try {
            await sendMutation.mutateAsync({
                message: trimmed,
                context: contextPayload,
            });
        } catch (error) {
            if (!preset) {
                setComposerValue(previousDraft);
            }

            publishToast({
                variant: 'destructive',
                title: 'Unable to send message',
                description:
                    error instanceof Error
                        ? error.message
                        : 'Please try again.',
            });
        }
    };

    const handleGuidedResolutionLocaleChange = async (
        resolution: AiChatGuidedResolution,
        locale: string,
    ) => {
        if (!resolvedThreadId || isSendLocked) {
            return;
        }

        const normalizedLocale = (locale ?? '').trim().toLowerCase() || 'en';
        const readableLocale =
            formatGuideLocale(normalizedLocale) ||
            normalizedLocale.toUpperCase();
        const guideTitle = resolution.title?.trim() || 'workspace guide';
        const followUpPrompt = `Show me the "${guideTitle}" guide in ${readableLocale}.`;

        setHelpLocale(normalizedLocale);

        try {
            await sendMutation.mutateAsync({
                message: followUpPrompt,
                context: {
                    locale: normalizedLocale,
                },
            });
        } catch (error) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to switch guide language',
                description:
                    error instanceof Error
                        ? error.message
                        : 'Please try again.',
            });
        }
    };
    const handleClarificationResponse = async (
        clarification: AiChatClarificationPrompt,
        answer: string,
    ): Promise<boolean> => {
        if (!resolvedThreadId || isSendLocked) {
            return false;
        }

        const trimmed = answer.trim();

        if (trimmed === '') {
            return false;
        }

        const clarificationId = clarification?.id;

        if (!clarificationId) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to send clarification',
                description:
                    'Clarification metadata is missing. Ask Copilot to repeat the question.',
            });

            return false;
        }

        try {
            await sendMutation.mutateAsync({
                message: trimmed,
                context: {
                    locale: helpLocale,
                    clarification: { id: clarificationId },
                },
            });

            return true;
        } catch (error) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to send clarification',
                description:
                    error instanceof Error
                        ? error.message
                        : 'Please try again.',
            });

            return false;
        }
    };

    const handleEntityPickerSelection = async (
        prompt: AiChatEntityPickerPrompt,
        candidate: AiChatEntityPickerCandidate,
    ): Promise<boolean> => {
        if (!resolvedThreadId || isSendLocked) {
            return false;
        }

        const pickerId = prompt?.id?.trim();
        const candidateId = candidate?.candidate_id?.trim();

        if (!pickerId || !candidateId) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to select record',
                description:
                    'Selection metadata is missing. Ask Copilot to re-run the search.',
            });

            return false;
        }

        const entityLabel = prompt?.entity_type
            ? humanizeKey(prompt.entity_type)
            : null;
        const selectionSummary = candidate?.label?.trim() || 'this record';
        const followUpPrompt = [
            'Use',
            selectionSummary,
            entityLabel ? `for ${entityLabel}` : null,
        ]
            .filter(Boolean)
            .join(' ')
            .concat('.');

        try {
            await sendMutation.mutateAsync({
                message: followUpPrompt,
                context: {
                    locale: helpLocale,
                    entity_picker: {
                        id: pickerId,
                        candidate_id: candidateId,
                    },
                },
            });

            return true;
        } catch (error) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to select record',
                description:
                    error instanceof Error
                        ? error.message
                        : 'Please try again.',
            });

            return false;
        }
    };

    const handleApproveDraftAction = async (
        draft: AiChatDraftSnapshot,
    ): Promise<boolean> => {
        if (!draft.draft_id) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to approve draft',
                description:
                    'Draft metadata is missing. Ask Copilot to regenerate it and try again.',
            });

            return false;
        }

        try {
            const result = await approveDraft.mutateAsync({
                draftId: draft.draft_id,
            });
            const entityDescription = describeDraftEntity(result.draft);

            publishToast({
                title: 'Draft approved',
                description: entityDescription
                    ? `Created ${entityDescription}.`
                    : 'Copilot saved your approval.',
            });

            return true;
        } catch (error) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to approve draft',
                description:
                    error instanceof Error
                        ? error.message
                        : 'Please try again.',
            });

            return false;
        }
    };

    const handleRejectDraftAction = async (
        draft: AiChatDraftSnapshot,
        reason: string,
    ): Promise<boolean> => {
        if (!draft.draft_id) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to reject draft',
                description:
                    'Draft metadata is missing. Ask Copilot to regenerate it and try again.',
            });

            return false;
        }

        const trimmed = reason.trim();

        if (trimmed === '') {
            publishToast({
                variant: 'destructive',
                title: 'Add a rejection reason',
                description:
                    'Include a short note so Copilot can learn from the feedback.',
            });

            return false;
        }

        try {
            await rejectDraft.mutateAsync({
                draftId: draft.draft_id,
                reason: trimmed,
            });

            publishToast({
                title: 'Draft rejected',
                description: 'Copilot recorded your feedback.',
            });

            return true;
        } catch (error) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to reject draft',
                description:
                    error instanceof Error
                        ? error.message
                        : 'Please try again.',
            });

            return false;
        }
    };

    const handleStartWorkflowSuggestion = async (
        workflow: AiChatWorkflowSuggestion,
    ): Promise<string | null> => {
        if (!workflow?.workflow_type) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to start workflow',
                description:
                    'Workflow metadata is missing. Ask Copilot to regenerate it and try again.',
            });

            return null;
        }

        if (!resolvedThreadId) {
            publishToast({
                variant: 'destructive',
                title: 'Chat thread not ready',
                description:
                    'Wait for Copilot to finish loading this thread, then try again.',
            });

            return null;
        }

        const payload = buildWorkflowStartVariables(workflow);

        try {
            const result = await startWorkflow.mutateAsync(payload);

            publishToast({
                title: 'Workflow started',
                description: `Workflow #${result.workflow_id} is queued for review.`,
            });

            return result.workflow_id;
        } catch (error) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to start workflow',
                description:
                    error instanceof Error
                        ? error.message
                        : 'Please try again.',
            });

            return null;
        }
    };

    const handleComposerKeyDown = (
        event: KeyboardEvent<HTMLTextAreaElement>,
    ) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            handleSend();
        }
    };

    return (
        <div
            data-testid="copilot-chat-panel"
            className={cn(
                'flex h-full flex-col rounded-[32px] border border-white/10 bg-slate-950/95 text-slate-50 shadow-[0_25px_80px_rgba(15,23,42,0.55)]',
                className,
            )}
        >
            <header className="border-b border-white/5 px-6 py-4">
                <div className="flex items-center gap-3">
                    <span className="inline-flex size-10 items-center justify-center rounded-2xl bg-primary/80 text-white">
                        <Sparkles className="size-5" />
                    </span>
                    <div>
                        <p className="text-sm tracking-[0.2em] text-slate-400 uppercase">
                            Procurement Copilot
                        </p>
                        <div className="flex items-center gap-2">
                            <h2 className="text-lg font-semibold text-white">
                                {threadTitle}
                            </h2>
                            {activeThread?.status ? (
                                <Badge
                                    variant="outline"
                                    className="border-white/10 bg-white/5 text-xs tracking-wide text-white/70 uppercase"
                                >
                                    {activeThread.status}
                                </Badge>
                            ) : null}
                        </div>
                    </div>
                </div>
                <p className="mt-2 text-sm text-slate-400">
                    {activeThread?.thread_summary ??
                        'Copilot keeps RFQs, supplier responses, and inventory context in a single timeline. Nothing is sent until you approve it.'}
                </p>
            </header>

            {isServiceUnavailable ? (
                <div className="px-5">
                    <Alert variant="destructive">
                        <AlertTitle>Copilot unavailable</AlertTitle>
                        <AlertDescription>
                            {serviceErrorMessage ??
                                'The AI service is unavailable right now.'}
                        </AlertDescription>
                    </Alert>
                </div>
            ) : null}

            <div
                ref={scrollRef}
                className="flex-1 space-y-6 overflow-y-auto px-5 py-6"
            >
                {showSkeleton ? (
                    <PanelSkeleton />
                ) : showEmptyState ? (
                    <EmptyConversationState />
                ) : (
                    visibleMessages.map((message) => (
                        <ChatMessage
                            key={`${message.id}-${message.role}`}
                            message={message}
                            onApproveDraft={handleApproveDraftAction}
                            onRejectDraft={handleRejectDraftAction}
                            draftActionState={draftActionState}
                            onStartWorkflow={handleStartWorkflowSuggestion}
                            isWorkflowStarting={startWorkflow.isPending}
                            onGuidedResolutionLocaleChange={
                                handleGuidedResolutionLocaleChange
                            }
                            isLocaleSwitchPending={isSendLocked}
                            onClarificationResponse={
                                handleClarificationResponse
                            }
                            clarificationPending={isSendLocked}
                            onEntityPickerSelection={
                                handleEntityPickerSelection
                            }
                            entityPickerPending={isSendLocked}
                        />
                    ))
                )}
                {sendMutation.isPending || sendMutation.isStreaming ? (
                    <ThinkingMessage />
                ) : null}
            </div>

            {needsHumanReview ? (
                <NeedsReviewBanner warnings={reviewWarnings} />
            ) : null}

            {quickReplies.length > 0 ? (
                <QuickReplyRail
                    replies={quickReplies}
                    disabled={isSendLocked}
                    onSelect={handleSend}
                />
            ) : null}

            <footer className="border-t border-white/5 p-5">
                <div className="rounded-2xl border border-white/10 bg-slate-900/60 p-4 shadow-inner">
                    <Textarea
                        value={composerValue}
                        onChange={(event) =>
                            setComposerValue(event.target.value)
                        }
                        onKeyDown={handleComposerKeyDown}
                        disabled={
                            !resolvedThreadId ||
                            isThreadLoading ||
                            isServiceUnavailable
                        }
                        placeholder="Ask Copilot to summarize, draft, or compare. Shift + Enter for a new line."
                        className="min-h-[96px] resize-none border-0 bg-transparent text-base text-white placeholder:text-slate-500 focus-visible:ring-0 disabled:cursor-not-allowed"
                    />
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-col gap-1 text-xs text-slate-500">
                            <p>
                                Copilot cites every answer with doc ids or
                                workspace tools.
                            </p>
                            <HelpLanguageSelector
                                value={helpLocale}
                                onChange={setHelpLocale}
                            />
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            disabled={
                                !resolvedThreadId ||
                                isThreadLoading ||
                                isSendLocked
                            }
                            onClick={() => handleSend()}
                            className="gap-2"
                        >
                            <Send className="size-4" /> Send
                        </Button>
                    </div>
                </div>
            </footer>
        </div>
    );
}

type ChatMessageProps = {
    message: AiChatMessage;
    onApproveDraft?: (draft: AiChatDraftSnapshot) => Promise<boolean>;
    onRejectDraft?: (
        draft: AiChatDraftSnapshot,
        reason: string,
    ) => Promise<boolean>;
    draftActionState?: DraftActionState;
    onStartWorkflow?: (
        workflow: AiChatWorkflowSuggestion,
    ) => Promise<string | null>;
    isWorkflowStarting?: boolean;
    onGuidedResolutionLocaleChange?: (
        resolution: AiChatGuidedResolution,
        locale: string,
    ) => void;
    isLocaleSwitchPending?: boolean;
    onClarificationResponse?: (
        clarification: AiChatClarificationPrompt,
        answer: string,
    ) => Promise<boolean>;
    clarificationPending?: boolean;
    onEntityPickerSelection?: (
        prompt: AiChatEntityPickerPrompt,
        candidate: AiChatEntityPickerCandidate,
    ) => Promise<boolean>;
    entityPickerPending?: boolean;
};

function ChatMessage({
    message,
    onApproveDraft,
    onRejectDraft,
    draftActionState,
    onStartWorkflow,
    isWorkflowStarting,
    onGuidedResolutionLocaleChange,
    isLocaleSwitchPending,
    onClarificationResponse,
    clarificationPending,
    onEntityPickerSelection,
    entityPickerPending,
}: ChatMessageProps) {
    if (message.role === 'user') {
        return (
            <div className="flex items-start justify-end gap-3">
                <div className="hidden text-white sm:block">
                    <UserAvatar />
                </div>
                <div className="max-w-[80%] rounded-2xl bg-white px-4 py-3 text-primary shadow-lg">
                    <MarkdownText markdown={message.content_text ?? ''} />
                    <p className="mt-2 text-right text-xs text-primary">
                        {formatDisplayTimestamp(message.created_at)}
                    </p>
                </div>
            </div>
        );
    }

    if (message.role === 'assistant') {
        return (
            <AssistantBubble
                message={message}
                onApproveDraft={onApproveDraft}
                onRejectDraft={onRejectDraft}
                draftActionState={draftActionState}
                onStartWorkflow={onStartWorkflow}
                isWorkflowStarting={isWorkflowStarting}
                onGuidedResolutionLocaleChange={onGuidedResolutionLocaleChange}
                isLocaleSwitchPending={isLocaleSwitchPending}
                onClarificationResponse={onClarificationResponse}
                clarificationPending={clarificationPending}
                onEntityPickerSelection={onEntityPickerSelection}
                entityPickerPending={entityPickerPending}
            />
        );
    }

    return <SystemBubble message={message} />;
}

function AssistantBubble({
    message,
    onApproveDraft,
    onRejectDraft,
    draftActionState,
    onStartWorkflow,
    isWorkflowStarting,
    onGuidedResolutionLocaleChange,
    isLocaleSwitchPending,
    onClarificationResponse,
    clarificationPending,
    onEntityPickerSelection,
    entityPickerPending,
}: {
    message: AiChatMessage;
    onApproveDraft?: (draft: AiChatDraftSnapshot) => Promise<boolean>;
    onRejectDraft?: (
        draft: AiChatDraftSnapshot,
        reason: string,
    ) => Promise<boolean>;
    draftActionState?: DraftActionState;
    onStartWorkflow?: (
        workflow: AiChatWorkflowSuggestion,
    ) => Promise<string | null>;
    isWorkflowStarting?: boolean;
    onGuidedResolutionLocaleChange?: (
        resolution: AiChatGuidedResolution,
        locale: string,
    ) => void;
    isLocaleSwitchPending?: boolean;
    onClarificationResponse?: (
        clarification: AiChatClarificationPrompt,
        answer: string,
    ) => Promise<boolean>;
    clarificationPending?: boolean;
    onEntityPickerSelection?: (
        prompt: AiChatEntityPickerPrompt,
        candidate: AiChatEntityPickerCandidate,
    ) => Promise<boolean>;
    entityPickerPending?: boolean;
}) {
    // Assistant turns carry an AiChatAssistantResponse envelope so we can decide whether to
    // render grounded answers, draft approvals, workflow suggestions, or tool loops per type.
    const response = extractAssistantResponse(message);
    const label = response
        ? (RESPONSE_LABELS[response.type] ?? 'Assistant')
        : 'Assistant';
    const markdown =
        response?.assistant_message_markdown ?? message.content_text ?? '';

    return (
        <div className="flex items-start gap-3">
            <AssistantAvatar />
            <div className="w-full max-w-[82%] rounded-2xl border border-white/5 bg-white/5 p-4 shadow-xl backdrop-blur">
                <div className="flex flex-wrap items-center gap-3">
                    <Badge
                        variant="secondary"
                        className="bg-white/10 text-xs font-semibold tracking-wide text-white uppercase"
                    >
                        {label}
                    </Badge>
                    {/* {response?.needs_human_review ? (
                        <Badge variant="destructive" className="bg-amber-200/40 text-amber-900">
                            Needs review
                        </Badge>
                    ) : null}
                    {typeof response?.confidence === 'number' ? (
                        <Badge variant="outline" className="border-white/20 text-white/80">
                            Confidence {(response.confidence * 100).toFixed(0)}%
                        </Badge>
                    ) : null} */}
                </div>

                <div className="mt-3 leading-relaxed text-slate-100">
                    <MarkdownText markdown={markdown} />
                </div>

                <AssistantDetails
                    response={response}
                    onApproveDraft={onApproveDraft}
                    onRejectDraft={onRejectDraft}
                    draftActionState={draftActionState}
                    onStartWorkflow={onStartWorkflow}
                    isWorkflowStarting={isWorkflowStarting}
                    onGuidedResolutionLocaleChange={
                        onGuidedResolutionLocaleChange
                    }
                    disableLocaleSwitch={isLocaleSwitchPending}
                    onClarificationResponse={onClarificationResponse}
                    clarificationPending={clarificationPending}
                    onEntityPickerSelection={onEntityPickerSelection}
                    entityPickerPending={entityPickerPending}
                />

                <p className="mt-4 text-xs tracking-wide text-slate-400 uppercase">
                    {formatDisplayTimestamp(message.created_at)}
                </p>
            </div>
        </div>
    );
}
function SystemBubble({ message }: { message: AiChatMessage }) {
    if (!message.content_text) {
        return null;
    }

    return (
        <div className="flex items-start gap-3">
            <AssistantAvatar />
            <div className="w-full max-w-[82%] rounded-2xl border border-white/10 bg-slate-900/70 p-4 text-sm text-slate-100">
                <MarkdownText markdown={message.content_text} />
                <p className="mt-4 text-xs tracking-wide text-slate-500 uppercase">
                    {formatDisplayTimestamp(message.created_at)}
                </p>
            </div>
        </div>
    );
}

function AssistantDetails({
    response,
    onApproveDraft,
    onRejectDraft,
    draftActionState,
    onStartWorkflow,
    isWorkflowStarting,
    onGuidedResolutionLocaleChange,
    disableLocaleSwitch,
    onClarificationResponse,
    clarificationPending,
    onEntityPickerSelection,
    entityPickerPending,
}: {
    response: AiChatAssistantResponse | null;
    onApproveDraft?: (draft: AiChatDraftSnapshot) => Promise<boolean>;
    onRejectDraft?: (
        draft: AiChatDraftSnapshot,
        reason: string,
    ) => Promise<boolean>;
    draftActionState?: DraftActionState;
    onStartWorkflow?: (
        workflow: AiChatWorkflowSuggestion,
    ) => Promise<string | null>;
    isWorkflowStarting?: boolean;
    onGuidedResolutionLocaleChange?: (
        resolution: AiChatGuidedResolution,
        locale: string,
    ) => void;
    disableLocaleSwitch?: boolean;
    onClarificationResponse?: (
        clarification: AiChatClarificationPrompt,
        answer: string,
    ) => Promise<boolean>;
    clarificationPending?: boolean;
    onEntityPickerSelection?: (
        prompt: AiChatEntityPickerPrompt,
        candidate: AiChatEntityPickerCandidate,
    ) => Promise<boolean>;
    entityPickerPending?: boolean;
}) {
    const warnings = response?.warnings ?? [];
    const isUnsafeDraft = response?.type === 'unsafe_action_confirmation';
    const unsafePrompt = isUnsafeDraft ? response?.unsafe_action : null;
    const hasDraft =
        Boolean(response?.draft) &&
        (response?.type === 'draft_action' || isUnsafeDraft);
    const hasWorkflow =
        response?.type === 'workflow_suggestion' && Boolean(response.workflow);
    const guidedResolution =
        response?.type === 'guided_resolution'
            ? response.guided_resolution
            : null;
    const reviewPayload = response?.review ?? null;
    const analyticsCards = extractAnalyticsCards(response);

    const clarificationPrompt =
        response?.type === 'clarification' ? response.clarification : null;
    const entityPickerPrompt =
        response?.type === 'entity_picker' ? response.entity_picker : null;

    if (
        !warnings.length &&
        !hasDraft &&
        !hasWorkflow &&
        !guidedResolution &&
        !reviewPayload &&
        analyticsCards.length === 0 &&
        !clarificationPrompt &&
        !entityPickerPrompt &&
        !unsafePrompt
    ) {
        return null;
    }

    return (
        <div className="mt-4 space-y-4 text-slate-100">
            {entityPickerPrompt ? (
                <EntityPickerPrompt
                    prompt={entityPickerPrompt}
                    onSelect={onEntityPickerSelection}
                    disabled={entityPickerPending}
                />
            ) : null}
            {clarificationPrompt ? (
                <ClarificationPromptForm
                    prompt={clarificationPrompt}
                    onSubmit={onClarificationResponse}
                    disabled={clarificationPending}
                />
            ) : null}
            {analyticsCards.map((card, index) => (
                <AnalyticsCard
                    key={`${card.metric ?? 'analytics'}-${card.title}-${index}`}
                    title={card.title}
                    chartData={card.chartData}
                    summary={card.summary}
                    citations={card.citations}
                    valueFormatter={card.valueFormatter}
                />
            ))}
            {/* {warnings.length > 0 ? (
                <Alert variant="default" className="bg-amber-100/10 text-amber-50">
                    <AlertTitle className="flex items-center gap-2 text-amber-100">
                        <AlertTriangle className="size-4" /> Check before sharing
                    </AlertTitle>
                    <AlertDescription>
                        <ul className="list-disc space-y-1 pl-5 text-sm text-amber-50/90">
                            {warnings.map((warning) => (
                                <li key={warning}>{warning}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            ) : null} */}

            {reviewPayload ? <ReviewChecklist review={reviewPayload} /> : null}

            {hasDraft && response?.draft ? (
                isUnsafeDraft && unsafePrompt ? (
                    <UnsafeActionConfirmationCard
                        prompt={unsafePrompt}
                        draft={response.draft}
                        onApprove={onApproveDraft}
                        isConfirming={Boolean(
                            draftActionState?.isApprovePending &&
                            draftActionState?.approvingId ===
                                response.draft.draft_id,
                        )}
                    />
                ) : null
            ) : null}
            {hasDraft && response?.draft ? (
                <DraftPreview
                    draft={response.draft}
                    onApprove={onApproveDraft}
                    onReject={onRejectDraft}
                    isApproving={Boolean(
                        draftActionState?.isApprovePending &&
                        draftActionState?.approvingId ===
                            response.draft.draft_id,
                    )}
                    isRejecting={Boolean(
                        draftActionState?.isRejectPending &&
                        draftActionState?.rejectingId ===
                            response.draft.draft_id,
                    )}
                    requireExternalApproval={isUnsafeDraft}
                />
            ) : null}

            {hasWorkflow && response?.workflow ? (
                <WorkflowPreview
                    workflow={response.workflow}
                    onStartWorkflow={onStartWorkflow}
                    isStarting={Boolean(isWorkflowStarting)}
                />
            ) : null}

            {guidedResolution ? (
                <GuidedResolutionCard
                    resolution={guidedResolution}
                    onChangeLocale={
                        onGuidedResolutionLocaleChange
                            ? (nextLocale) =>
                                  onGuidedResolutionLocaleChange(
                                      guidedResolution,
                                      nextLocale,
                                  )
                            : undefined
                    }
                    disableLocaleSwitch={disableLocaleSwitch}
                />
            ) : null}
        </div>
    );
}

function EntityPickerPrompt({
    prompt,
    onSelect,
    disabled = false,
}: {
    prompt: AiChatEntityPickerPrompt;
    onSelect?: (
        prompt: AiChatEntityPickerPrompt,
        candidate: AiChatEntityPickerCandidate,
    ) => Promise<boolean>;
    disabled?: boolean;
}) {
    const [pendingId, setPendingId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const entityLabel = prompt.entity_type
        ? humanizeKey(prompt.entity_type)
        : 'record';
    const heading =
        prompt.title?.trim() || `Pick the ${entityLabel} Copilot should use`;
    const description =
        prompt.description?.trim() ??
        'Multiple records matched your request. Choose the right one so Copilot continues.';

    const handleSelect = async (candidate: AiChatEntityPickerCandidate) => {
        if (!onSelect || disabled || pendingId === candidate.candidate_id) {
            return;
        }

        setError(null);
        setPendingId(candidate.candidate_id);

        try {
            const success = await onSelect(prompt, candidate);

            if (!success) {
                setError(
                    'Copilot could not continue. Try again or ask for another search.',
                );
            }
        } catch {
            setError(
                'Copilot could not continue. Try again or ask for another search.',
            );
        } finally {
            setPendingId(null);
        }
    };

    const candidates = prompt.candidates ?? [];

    return (
        <div
            className="space-y-4 rounded-3xl border border-cyan-400/30 bg-gradient-to-br from-slate-950/80 to-slate-900/40 p-5"
            data-testid="entity-picker-form"
        >
            <div className="flex flex-wrap items-start gap-3">
                <div className="rounded-2xl bg-cyan-500/10 p-3 text-cyan-200">
                    <GitBranch className="size-5" />
                </div>
                <div className="flex-1">
                    <p className="text-xs tracking-[0.2em] text-cyan-200 uppercase">
                        Multiple matches found
                    </p>
                    <h3 className="mt-1 text-lg font-semibold text-white">
                        {heading}
                    </h3>
                    <p className="mt-1 text-sm text-slate-200">{description}</p>
                </div>
            </div>
            <div className="flex flex-wrap items-center gap-3 text-xs tracking-wide text-cyan-100/80 uppercase">
                <Badge
                    variant="outline"
                    className="border-cyan-200/40 text-cyan-50"
                >
                    {entityLabel}
                </Badge>
                <span className="text-cyan-100/70">
                    Query ·{' '}
                    <span className="font-semibold text-white">
                        {prompt.query}
                    </span>
                </span>
            </div>
            {candidates.length > 0 ? (
                <div className="grid gap-3 md:grid-cols-2" role="list">
                    {candidates.map((candidate) => {
                        const pending = pendingId === candidate.candidate_id;
                        const statusLabel = candidate.status
                            ? humanizeKey(candidate.status)
                            : null;

                        return (
                            <div
                                key={candidate.candidate_id}
                                role="listitem"
                                className="space-y-3 rounded-2xl border border-white/10 bg-white/5 p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-semibold text-white">
                                            {candidate.label}
                                        </p>
                                        {candidate.description ? (
                                            <p className="text-xs text-slate-300">
                                                {candidate.description}
                                            </p>
                                        ) : null}
                                    </div>
                                    {statusLabel ? (
                                        <Badge
                                            variant="outline"
                                            className="border-cyan-200/40 text-[10px] tracking-wide text-cyan-100 uppercase"
                                        >
                                            {statusLabel}
                                        </Badge>
                                    ) : null}
                                </div>
                                {candidate.meta && candidate.meta.length > 0 ? (
                                    <ul className="space-y-1 text-xs text-slate-300">
                                        {candidate.meta.map(
                                            (metaLine, index) => (
                                                <li
                                                    key={`${candidate.candidate_id}-meta-${index}`}
                                                    className="flex items-start gap-2"
                                                >
                                                    <span
                                                        className="mt-[6px] h-1.5 w-1.5 rounded-full bg-cyan-300/80"
                                                        aria-hidden="true"
                                                    />
                                                    <span>{metaLine}</span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                ) : null}
                                <Button
                                    type="button"
                                    size="sm"
                                    className="w-full gap-2 bg-slate-900/70"
                                    disabled={disabled || pending}
                                    onClick={() => void handleSelect(candidate)}
                                >
                                    {pending ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : null}
                                    Use this record
                                </Button>
                            </div>
                        );
                    })}
                </div>
            ) : (
                <p className="text-sm text-rose-200">
                    Copilot could not load options from the workspace search.
                    Ask it to try again.
                </p>
            )}
            {error ? <p className="text-xs text-rose-300">{error}</p> : null}
        </div>
    );
}

function ClarificationPromptForm({
    prompt,
    onSubmit,
    disabled = false,
}: {
    prompt: AiChatClarificationPrompt;
    onSubmit?: (
        clarification: AiChatClarificationPrompt,
        answer: string,
    ) => Promise<boolean>;
    disabled?: boolean;
}) {
    const [answer, setAnswer] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const busy = disabled || isSubmitting;

    const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!onSubmit || busy) {
            return;
        }

        const trimmed = answer.trim();

        if (trimmed === '') {
            setError('Enter an answer so Copilot can continue.');
            return;
        }

        setError(null);
        setIsSubmitting(true);

        try {
            const success = await onSubmit(prompt, trimmed);

            if (success) {
                setAnswer('');
            } else {
                setError('Unable to send answer. Try again.');
            }
        } catch {
            setError('Unable to send answer. Try again.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form
            onSubmit={handleSubmit}
            className="space-y-3 rounded-2xl border border-indigo-400/30 bg-indigo-950/30 p-4"
            data-testid="clarification-form"
        >
            <div className="flex items-center gap-2 text-xs tracking-wide text-indigo-200 uppercase">
                <Sparkles className="size-4" /> Copilot needs one more detail
            </div>
            <p className="text-sm text-slate-100">{prompt.question}</p>
            {prompt.missing_args?.length ? (
                <div className="flex flex-wrap gap-2">
                    {prompt.missing_args.map((arg) => (
                        <Badge
                            key={arg}
                            variant="outline"
                            className="border-indigo-300/40 text-[11px] tracking-wide text-indigo-100 uppercase"
                        >
                            {arg}
                        </Badge>
                    ))}
                </div>
            ) : null}
            <Textarea
                value={answer}
                onChange={(event) => {
                    setAnswer(event.target.value);
                    if (error) {
                        setError(null);
                    }
                }}
                rows={2}
                disabled={busy}
                placeholder="Type your answer"
                aria-label="Clarification answer"
                className="min-h-[64px] bg-slate-950/50 text-slate-100"
            />
            {error ? <p className="text-xs text-rose-300">{error}</p> : null}
            <div className="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
                <span>
                    Copilot will continue building the draft once this is
                    answered.
                </span>
                <Button
                    type="submit"
                    size="sm"
                    disabled={busy}
                    className="gap-2"
                >
                    {busy ? <Loader2 className="size-4 animate-spin" /> : null}
                    Submit answer
                </Button>
            </div>
        </form>
    );
}

function NeedsReviewBanner({ warnings }: { warnings: string[] }) {
    const hasWarnings = warnings.length > 0;

    return (
        <div className="hidden border-t border-white/5 bg-amber-950/30 px-5 py-4">
            <Alert
                variant="destructive"
                className="border-amber-200/50 bg-transparent text-amber-100"
            >
                <AlertTitle className="flex items-center gap-2 font-semibold text-amber-100">
                    <AlertTriangle className="size-4" /> Manual review required
                </AlertTitle>
                <AlertDescription className="mt-1 text-sm text-amber-50/90">
                    {hasWarnings ? (
                        <ul className="list-disc space-y-1 pl-5">
                            {warnings.map((warning) => (
                                <li key={warning}>{warning}</li>
                            ))}
                        </ul>
                    ) : (
                        <span>
                            Copilot flagged the latest response for human review
                            before sharing.
                        </span>
                    )}
                </AlertDescription>
            </Alert>
        </div>
    );
}

function DraftPreview({
    draft,
    onApprove,
    onReject,
    isApproving = false,
    isRejecting = false,
    requireExternalApproval = false,
}: {
    draft: AiChatDraftSnapshot;
    onApprove?: (draft: AiChatDraftSnapshot) => Promise<boolean>;
    onReject?: (draft: AiChatDraftSnapshot, reason: string) => Promise<boolean>;
    isApproving?: boolean;
    isRejecting?: boolean;
    requireExternalApproval?: boolean;
}) {
    const [open, setOpen] = useState(() => draft.status !== 'approved');
    const [rejectMode, setRejectMode] = useState(false);
    const [rejectReason, setRejectReason] = useState('');
    const payloadEntries = Object.entries(draft.payload ?? {});
    const invoiceDraftPayload =
        draft.action_type === 'invoice_draft' && isRecord(draft.payload)
            ? draft.payload
            : null;
    const invoicePaymentPayload =
        draft.action_type === 'approve_invoice' && isRecord(draft.payload)
            ? draft.payload
            : null;
    const receiptDraftPayload =
        draft.action_type === 'receipt_draft' && isRecord(draft.payload)
            ? draft.payload
            : null;
    const paymentDraftPayload =
        (draft.action_type === 'payment_process' ||
            draft.action_type === 'payment_draft') &&
        isRecord(draft.payload)
            ? draft.payload
            : null;
    const invoiceMatchPayload =
        draft.action_type === 'invoice_match' && isRecord(draft.payload)
            ? draft.payload
            : null;
    const status = draft.status;
    const summary =
        draft.summary ??
        'Copilot saved this draft so you can approve or reject it.';
    const isActionable = !status || status === 'drafted';
    const allowApproveAction = Boolean(onApprove) && !requireExternalApproval;
    const allowRejectAction = Boolean(onReject);
    const showActions =
        isActionable && (allowApproveAction || allowRejectAction);
    let payloadContent: ReactNode | null = null;

    if (invoiceDraftPayload) {
        payloadContent = <InvoiceDraftPayload payload={invoiceDraftPayload} />;
    } else if (invoicePaymentPayload) {
        payloadContent = (
            <InvoicePaymentPayload payload={invoicePaymentPayload} />
        );
    } else if (receiptDraftPayload) {
        payloadContent = <ReceiptDraftPayload payload={receiptDraftPayload} />;
    } else if (paymentDraftPayload) {
        payloadContent = <PaymentDraftPayload payload={paymentDraftPayload} />;
    } else if (invoiceMatchPayload) {
        payloadContent = <InvoiceMatchPayload payload={invoiceMatchPayload} />;
    } else if (payloadEntries.length > 0) {
        payloadContent = (
            <dl className="mt-3 space-y-2 text-sm">
                {payloadEntries.map(([key, value]) => (
                    <div key={key}>
                        <dt className="text-xs tracking-wide text-slate-400 uppercase">
                            {humanizeKey(key)}
                        </dt>
                        <dd className="text-slate-100">{renderValue(value)}</dd>
                    </div>
                ))}
            </dl>
        );
    }

    const handleApproveClick = async () => {
        if (!onApprove || isApproving || isRejecting) {
            return;
        }

        const result = await onApprove(draft);
        if (result) {
            setRejectMode(false);
            setRejectReason('');
        }
    };

    const handleRejectSubmit = async () => {
        if (!onReject || isApproving || isRejecting) {
            return;
        }

        const trimmed = rejectReason.trim();
        if (!trimmed) {
            return;
        }

        const result = await onReject(draft, trimmed);
        if (result) {
            setRejectReason('');
            setRejectMode(false);
        }
    };

    return (
        <CollapsibleCard
            title={`Draft · ${formatActionLabel(draft.action_type)}`}
            open={open}
            onToggle={() => setOpen((value) => !value)}
        >
            <div className="flex flex-wrap items-center gap-2">
                <Badge
                    variant="outline"
                    className={cn(
                        'border-white/10 text-xs tracking-wide uppercase',
                        draftStatusBadgeClass(status),
                    )}
                >
                    {formatDraftStatus(status)}
                </Badge>
                {draft.entity_type ? (
                    <span className="text-[11px] tracking-wide text-slate-500 uppercase">
                        Target · {humanizeKey(draft.entity_type)}
                        {draft.entity_id ? ` #${draft.entity_id}` : ''}
                    </span>
                ) : null}
            </div>
            <p className="mt-2 text-sm text-slate-200">{summary}</p>
            {payloadContent}

            {showActions ? (
                <div className="mt-5 space-y-3 rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p className="text-xs text-slate-400">
                        Approvals and rejections log to the thread so your team
                        sees who cleared it.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {allowApproveAction && onApprove ? (
                            <Button
                                type="button"
                                className="gap-2"
                                disabled={isApproving || isRejecting}
                                onClick={() => void handleApproveClick()}
                            >
                                {isApproving ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : null}
                                Approve draft
                            </Button>
                        ) : null}
                        {allowRejectAction && onReject && !rejectMode ? (
                            <Button
                                type="button"
                                variant="secondary"
                                className="bg-slate-800/60 text-slate-100"
                                disabled={isApproving || isRejecting}
                                onClick={() => {
                                    setRejectMode(true);
                                    setRejectReason('');
                                }}
                            >
                                Reject draft
                            </Button>
                        ) : null}
                    </div>

                    {rejectMode && onReject ? (
                        <div className="space-y-3 rounded-xl border border-white/10 bg-slate-950/50 p-3">
                            <Textarea
                                value={rejectReason}
                                onChange={(event) =>
                                    setRejectReason(event.target.value)
                                }
                                placeholder="Add a short note so Copilot knows what to fix."
                                rows={3}
                                disabled={isRejecting}
                            />
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={isRejecting}
                                    onClick={() => {
                                        setRejectMode(false);
                                        setRejectReason('');
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    className="gap-2"
                                    disabled={
                                        isRejecting ||
                                        rejectReason.trim().length === 0
                                    }
                                    onClick={() => void handleRejectSubmit()}
                                >
                                    {isRejecting ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : null}
                                    Submit rejection
                                </Button>
                            </div>
                        </div>
                    ) : null}
                </div>
            ) : null}
        </CollapsibleCard>
    );
}

function UnsafeActionConfirmationCard({
    prompt,
    draft,
    onApprove,
    isConfirming = false,
}: {
    prompt: AiChatUnsafeActionPrompt;
    draft: AiChatDraftSnapshot;
    onApprove?: (draft: AiChatDraftSnapshot) => Promise<boolean>;
    isConfirming?: boolean;
}) {
    const [acknowledged, setAcknowledged] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const checkboxId = `unsafe-action-${prompt.id}`;
    const acknowledgementText =
        prompt.acknowledgement?.trim() ||
        'I understand this action will update financial records.';
    const confirmLabel = prompt.confirm_label?.trim() || 'Confirm action';

    const handleConfirm = async () => {
        if (!onApprove || isConfirming || !acknowledged) {
            return;
        }

        setError(null);

        try {
            const success = await onApprove(draft);

            if (!success) {
                setError('Copilot could not run this action. Try again.');
            } else {
                setAcknowledged(false);
            }
        } catch {
            setError('Copilot could not run this action. Try again.');
        }
    };

    return (
        <div
            className="space-y-4 rounded-3xl border border-rose-400/40 bg-rose-950/20 p-5"
            data-testid="unsafe-action-confirmation"
        >
            <div className="flex flex-wrap items-start gap-3">
                <div className="rounded-2xl bg-rose-500/10 p-3 text-rose-100">
                    <AlertTriangle className="size-5" />
                </div>
                <div className="flex-1">
                    <p className="text-xs tracking-[0.2em] text-rose-200 uppercase">
                        High-impact action
                    </p>
                    <h3 className="mt-1 text-lg font-semibold text-white">
                        {prompt.headline}
                    </h3>
                    {prompt.summary ? (
                        <p className="mt-1 text-sm text-rose-100/90">
                            {prompt.summary}
                        </p>
                    ) : null}
                </div>
            </div>
            {prompt.description ? (
                <p className="text-sm text-slate-100">{prompt.description}</p>
            ) : null}
            {prompt.entity || prompt.impact ? (
                <div className="flex flex-wrap items-center gap-3 text-xs text-rose-100/80">
                    {prompt.entity ? (
                        <Badge
                            variant="outline"
                            className="border-rose-200/40 text-rose-50"
                        >
                            {prompt.entity}
                        </Badge>
                    ) : null}
                    {prompt.impact ? (
                        <span>
                            Impact ·{' '}
                            <span className="font-semibold text-white">
                                {prompt.impact}
                            </span>
                        </span>
                    ) : null}
                </div>
            ) : null}
            {prompt.risks && prompt.risks.length > 0 ? (
                <ul className="space-y-1 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-rose-50/90">
                    {prompt.risks.map((risk) => (
                        <li key={risk} className="flex items-start gap-2">
                            <span
                                className="mt-[6px] h-1.5 w-1.5 rounded-full bg-rose-300"
                                aria-hidden="true"
                            />
                            <span>{risk}</span>
                        </li>
                    ))}
                </ul>
            ) : null}
            <label
                className="flex items-start gap-3 text-sm text-slate-100"
                htmlFor={checkboxId}
            >
                <Checkbox
                    id={checkboxId}
                    checked={acknowledged}
                    onCheckedChange={(checked) =>
                        setAcknowledged(Boolean(checked))
                    }
                    aria-label={acknowledgementText}
                />
                <span>{acknowledgementText}</span>
            </label>
            {error ? <p className="text-xs text-rose-300">{error}</p> : null}
            <Button
                type="button"
                className="w-full gap-2 bg-rose-600/90 hover:bg-rose-600"
                disabled={!acknowledged || isConfirming}
                onClick={() => void handleConfirm()}
            >
                {isConfirming ? (
                    <Loader2 className="size-4 animate-spin" />
                ) : null}
                {confirmLabel}
            </Button>
        </div>
    );
}

interface InvoiceDraftLine {
    description: string;
    qty: number;
    unitPrice: number;
    taxRate: number;
}

function InvoiceDraftPayload({
    payload,
}: {
    payload: Record<string, unknown>;
}) {
    const poId = normalizeStringValue(payload.po_id) ?? '—';
    const invoiceDate = formatDateLabel(
        normalizeStringValue(payload.invoice_date),
    );
    const dueDate = formatDateLabel(normalizeStringValue(payload.due_date));
    const notes = normalizeStringValue(payload.notes);
    const currency = normalizeCurrencyCode(payload.currency) ?? 'USD';
    const lineItems = Array.isArray(payload.line_items)
        ? (payload.line_items
              .map((item) => {
                  if (!isRecord(item)) {
                      return null;
                  }

                  const qty = numberValue(item.qty);
                  const unitPrice = numberValue(item.unit_price);

                  if (qty === null || unitPrice === null) {
                      return null;
                  }

                  return {
                      description:
                          normalizeStringValue(item.description) ?? 'Line item',
                      qty,
                      unitPrice,
                      taxRate: Math.max(0, numberValue(item.tax_rate) ?? 0),
                  } satisfies InvoiceDraftLine;
              })
              .filter(Boolean) as InvoiceDraftLine[])
        : [];

    const subtotal = lineItems.reduce(
        (sum, line) => sum + line.qty * line.unitPrice,
        0,
    );
    const taxTotal = lineItems.reduce(
        (sum, line) => sum + line.qty * line.unitPrice * line.taxRate,
        0,
    );
    const grandTotal = subtotal + taxTotal;

    return (
        <div className="mt-3 space-y-4 rounded-2xl border border-white/10 bg-slate-950/40 p-4">
            <div className="grid gap-4 text-sm text-white sm:grid-cols-2">
                <InfoItem label="Purchase order" value={poId} />
                <InfoItem label="Invoice date" value={invoiceDate} />
                <InfoItem label="Due date" value={dueDate} />
                <InfoItem label="Currency" value={currency} />
            </div>

            <div>
                <p className="text-xs tracking-wide text-slate-400 uppercase">
                    Line items
                </p>
                <div className="mt-2 space-y-2">
                    {lineItems.length === 0 ? (
                        <p className="text-sm text-slate-400">
                            Copilot did not include any invoice lines.
                        </p>
                    ) : (
                        lineItems.map((line, index) => (
                            <div
                                key={`${line.description}-${index}`}
                                className="rounded-xl border border-white/5 bg-white/5 p-3"
                            >
                                <div className="flex items-center justify-between gap-3 text-sm font-semibold text-white">
                                    <span>{line.description}</span>
                                    <span>
                                        {formatCurrencyValue(
                                            line.qty * line.unitPrice,
                                            currency,
                                        )}
                                    </span>
                                </div>
                                <p className="text-xs text-slate-400">
                                    {line.qty} ×{' '}
                                    {formatCurrencyValue(
                                        line.unitPrice,
                                        currency,
                                    )}{' '}
                                    · Tax {formatPercentValue(line.taxRate)}
                                </p>
                            </div>
                        ))
                    )}
                </div>
            </div>

            <div className="rounded-xl border border-white/10 bg-slate-950/60 p-4 text-sm">
                <div className="flex items-center justify-between text-slate-300">
                    <span>Subtotal</span>
                    <span>{formatCurrencyValue(subtotal, currency)}</span>
                </div>
                <div className="mt-2 flex items-center justify-between text-slate-300">
                    <span>Estimated tax</span>
                    <span>{formatCurrencyValue(taxTotal, currency)}</span>
                </div>
                <div className="mt-3 flex items-center justify-between border-t border-white/10 pt-3 text-base font-semibold text-white">
                    <span>Total</span>
                    <span>{formatCurrencyValue(grandTotal, currency)}</span>
                </div>
            </div>

            {notes ? (
                <div className="rounded-xl border border-white/5 bg-white/5 p-3 text-sm text-slate-100">
                    <p className="text-xs tracking-wide text-slate-400 uppercase">
                        Reviewer note
                    </p>
                    <p className="mt-1 whitespace-pre-wrap">{notes}</p>
                </div>
            ) : null}
        </div>
    );
}

function InvoicePaymentPayload({
    payload,
}: {
    payload: Record<string, unknown>;
}) {
    const invoiceId = normalizeStringValue(payload.invoice_id) ?? '—';
    const paymentReference =
        normalizeStringValue(payload.payment_reference) ?? '—';
    const paymentMethod = normalizeStringValue(payload.payment_method) ?? '—';
    const paidAt = formatDateLabel(normalizeStringValue(payload.paid_at));
    const note = normalizeStringValue(payload.note);
    const currency = normalizeCurrencyCode(payload.payment_currency) ?? 'USD';
    const amount = numberValue(payload.payment_amount);

    return (
        <div className="mt-3 space-y-4 rounded-2xl border border-emerald-500/20 bg-emerald-950/20 p-4 text-sm text-white">
            <div className="grid gap-4 sm:grid-cols-2">
                <InfoItem label="Invoice" value={invoiceId} />
                <InfoItem label="Payment reference" value={paymentReference} />
                <InfoItem label="Payment method" value={paymentMethod} />
                <InfoItem label="Paid date" value={paidAt} />
            </div>

            <div className="rounded-xl border border-white/10 bg-emerald-950/30 p-4">
                <p className="text-xs tracking-wide text-emerald-200 uppercase">
                    Amount recorded
                </p>
                <p className="mt-2 text-2xl font-semibold">
                    {amount === null
                        ? '—'
                        : formatCurrencyValue(amount, currency)}
                </p>
            </div>

            {note ? (
                <div className="rounded-xl border border-white/10 bg-white/5 p-3 text-slate-100">
                    <p className="text-xs tracking-wide text-slate-400 uppercase">
                        Payment note
                    </p>
                    <p className="mt-1 whitespace-pre-wrap">{note}</p>
                </div>
            ) : null}
        </div>
    );
}

interface ReceiptDraftLine {
    reference: string;
    lineNumber: number | null;
    description: string;
    uom: string | null;
    expectedQty: number | null;
    receivedQty: number | null;
    acceptedQty: number | null;
    rejectedQty: number | null;
    issues: string[];
    notes: string | null;
}

function ReceiptDraftPayload({
    payload,
}: {
    payload: Record<string, unknown>;
}) {
    const poId = normalizeStringValue(payload.po_id) ?? '—';
    const reference = normalizeStringValue(payload.reference) ?? '—';
    const inspector = normalizeStringValue(payload.inspected_by) ?? '—';
    const receivedDate = formatDateLabel(
        normalizeStringValue(payload.received_date),
    );
    const status = normalizeStringValue(payload.status);
    const totalReceivedQty = numberValue(payload.total_received_qty);
    const notes = normalizeStringValue(payload.notes);
    const lineItems = Array.isArray(payload.line_items)
        ? (payload.line_items
              .map((entry, index) => normalizeReceiptLine(entry, index))
              .filter(Boolean) as ReceiptDraftLine[])
        : [];
    const visibleLines = lineItems.slice(0, 5);
    const hiddenLineCount = Math.max(0, lineItems.length - visibleLines.length);
    const payloadWarnings = normalizeStringList(payload.warnings);
    const derivedWarnings = buildReceiptWarnings(lineItems);
    const warnings = [...payloadWarnings, ...derivedWarnings];
    const warningsToShow = warnings.slice(0, 3);
    const hiddenWarnings = Math.max(0, warnings.length - warningsToShow.length);

    return (
        <div className="mt-3 space-y-4 rounded-2xl border border-white/10 bg-slate-950/40 p-4">
            <div className="grid gap-4 text-sm text-white sm:grid-cols-2">
                <InfoItem label="Purchase order" value={poId} />
                <InfoItem label="Receipt reference" value={reference} />
                <InfoItem label="Received date" value={receivedDate} />
                <InfoItem label="Inspector" value={inspector} />
                <InfoItem
                    label="Status"
                    value={status ? humanizeKey(status) : 'Draft'}
                />
                <InfoItem
                    label="Total received qty"
                    value={
                        totalReceivedQty === null
                            ? '—'
                            : formatDecimalValue(totalReceivedQty, 3)
                    }
                />
            </div>

            <div>
                <p className="text-xs tracking-wide text-slate-400 uppercase">
                    Line items
                </p>
                {visibleLines.length > 0 ? (
                    <div className="mt-2 space-y-2">
                        {visibleLines.map((line, index) => {
                            const key = `${line.reference}-${line.lineNumber ?? index}`;
                            const isFlagged =
                                line.issues.length > 0 ||
                                Boolean(line.rejectedQty);

                            return (
                                <div
                                    key={key}
                                    className={cn(
                                        'rounded-xl border bg-white/5 p-3 text-sm text-white',
                                        isFlagged
                                            ? 'border-amber-300/40 bg-amber-950/20'
                                            : 'border-white/5',
                                    )}
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="font-semibold">
                                                {line.description}
                                            </p>
                                            <p className="text-xs text-slate-400">
                                                Line{' '}
                                                {line.lineNumber ??
                                                    line.reference}
                                            </p>
                                        </div>
                                        <span className="text-sm font-semibold">
                                            {formatQuantityValue(
                                                line.receivedQty,
                                                line.uom,
                                            )}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-400">
                                        Expected{' '}
                                        {formatQuantityValue(
                                            line.expectedQty,
                                            line.uom,
                                        )}{' '}
                                        · Accepted{' '}
                                        {formatQuantityValue(
                                            line.acceptedQty,
                                            line.uom,
                                        )}
                                        {line.rejectedQty
                                            ? ` · Rejected ${formatQuantityValue(line.rejectedQty, line.uom)}`
                                            : ''}
                                    </p>
                                    {line.issues.length > 0 ? (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {line.issues.map(
                                                (issue, issueIndex) => (
                                                    <Badge
                                                        key={`${key}-issue-${issueIndex}`}
                                                        variant="outline"
                                                        className="border-amber-200/40 text-[11px] tracking-wide text-amber-100 uppercase"
                                                    >
                                                        {issue}
                                                    </Badge>
                                                ),
                                            )}
                                        </div>
                                    ) : null}
                                    {line.notes ? (
                                        <p className="mt-2 text-xs text-slate-300">
                                            {line.notes}
                                        </p>
                                    ) : null}
                                </div>
                            );
                        })}
                        {hiddenLineCount > 0 ? (
                            <p className="text-xs text-slate-400">
                                +{hiddenLineCount} more line items not shown.
                            </p>
                        ) : null}
                    </div>
                ) : (
                    <p className="mt-2 text-sm text-slate-400">
                        Copilot did not include line-level receipt data.
                    </p>
                )}
            </div>

            {warningsToShow.length > 0 ? (
                <div className="rounded-xl border border-amber-300/30 bg-amber-950/30 p-3 text-sm text-amber-100">
                    <p className="text-xs tracking-wide text-amber-200 uppercase">
                        Warnings
                    </p>
                    <ul className="mt-2 list-disc space-y-1 pl-5">
                        {warningsToShow.map((warning, index) => (
                            <li key={`${warning}-${index}`}>{warning}</li>
                        ))}
                    </ul>
                    {hiddenWarnings > 0 ? (
                        <p className="mt-2 text-xs text-amber-200/80">
                            +{hiddenWarnings} more warnings not shown.
                        </p>
                    ) : null}
                </div>
            ) : null}

            {notes ? (
                <div className="rounded-xl border border-white/5 bg-white/5 p-3 text-sm text-slate-100">
                    <p className="text-xs tracking-wide text-slate-400 uppercase">
                        Notes
                    </p>
                    <p className="mt-1 whitespace-pre-wrap">{notes}</p>
                </div>
            ) : null}
        </div>
    );
}

function normalizeReceiptLine(
    entry: unknown,
    index: number,
): ReceiptDraftLine | null {
    if (!isRecord(entry)) {
        return null;
    }

    const lineNumber = numberValue(entry.line_number);
    const reference =
        normalizeStringValue(entry.po_line_id) ??
        normalizeStringValue(entry.line_reference) ??
        normalizeTextValue(entry.po_line_id) ??
        normalizeTextValue(entry.line_reference) ??
        `Line ${index + 1}`;
    const description =
        normalizeStringValue(entry.description) ??
        normalizeStringValue(entry.item) ??
        normalizeStringValue(entry.part_number) ??
        'Received line';
    const uom =
        normalizeStringValue(entry.uom) ??
        normalizeStringValue(entry.unit) ??
        null;
    const issues = normalizeStringList(
        entry.issues ?? entry.defects ?? entry.warnings,
    );
    const notes =
        normalizeStringValue(entry.notes) ??
        normalizeStringValue(entry.quality_notes);

    return {
        reference,
        lineNumber,
        description,
        uom,
        expectedQty: numberValue(entry.expected_qty ?? entry.ordered_qty),
        receivedQty: numberValue(entry.received_qty ?? entry.qty),
        acceptedQty: numberValue(entry.accepted_qty),
        rejectedQty: numberValue(entry.rejected_qty),
        issues,
        notes,
    } satisfies ReceiptDraftLine;
}

function buildReceiptWarnings(lines: ReceiptDraftLine[]): string[] {
    const warnings: string[] = [];

    lines.forEach((line) => {
        if (line.rejectedQty && line.rejectedQty > 0) {
            warnings.push(
                `${line.reference} has ${formatQuantityValue(line.rejectedQty, line.uom)} rejected.`,
            );
        }
        line.issues.forEach((issue) =>
            warnings.push(`${line.reference}: ${issue}`),
        );
    });

    return warnings;
}

function PaymentDraftPayload({
    payload,
}: {
    payload: Record<string, unknown>;
}) {
    const invoiceId = normalizeStringValue(payload.invoice_id) ?? '—';
    const scheduledDate = formatDateLabel(
        normalizeStringValue(payload.scheduled_date),
    );
    const paymentMethod = normalizeStringValue(payload.payment_method);
    const reference =
        normalizeStringValue(payload.reference) ??
        normalizeStringValue(payload.payment_reference) ??
        '—';
    const amount = numberValue(payload.amount ?? payload.payment_amount);
    const currency =
        normalizeCurrencyCode(payload.currency ?? payload.payment_currency) ??
        'USD';
    const notes = normalizeStringValue(payload.notes ?? payload.note);

    return (
        <div className="mt-3 space-y-4 rounded-2xl border border-emerald-400/30 bg-emerald-950/20 p-4 text-sm text-white">
            <div className="grid gap-4 sm:grid-cols-2">
                <InfoItem label="Invoice" value={invoiceId} />
                <InfoItem label="Scheduled date" value={scheduledDate} />
                <InfoItem
                    label="Payment method"
                    value={paymentMethod ? humanizeKey(paymentMethod) : '—'}
                />
                <InfoItem label="Reference" value={reference} />
            </div>

            <div className="rounded-xl border border-white/10 bg-emerald-950/40 p-4 text-center">
                <p className="text-xs tracking-wide text-emerald-200 uppercase">
                    Planned payment
                </p>
                <p className="mt-2 text-3xl font-semibold">
                    {typeof amount === 'number' && Number.isFinite(amount)
                        ? formatCurrencyValue(amount, currency)
                        : '—'}
                </p>
            </div>

            {notes ? (
                <div className="rounded-xl border border-white/10 bg-white/5 p-3 text-slate-100">
                    <p className="text-xs tracking-wide text-slate-400 uppercase">
                        Notes
                    </p>
                    <p className="mt-1 whitespace-pre-wrap">{notes}</p>
                </div>
            ) : null}
        </div>
    );
}

type InvoiceMatchSeverity = 'info' | 'warning' | 'risk';

interface InvoiceMatchMismatch {
    type: string;
    lineReference: string | null;
    severity: InvoiceMatchSeverity;
    detail: string;
    expected: number | null;
    actual: number | null;
}

function InvoiceMatchPayload({
    payload,
}: {
    payload: Record<string, unknown>;
}) {
    const invoiceId = normalizeStringValue(payload.invoice_id) ?? '—';
    const poId =
        normalizeStringValue(payload.matched_po_id ?? payload.po_id) ?? '—';
    const receiptIds = normalizeStringList(payload.matched_receipt_ids);
    const matchScore = numberValue(payload.match_score);
    const recommendation = isRecord(payload.recommendation)
        ? {
              status: normalizeStringValue(payload.recommendation.status),
              explanation: normalizeStringValue(
                  payload.recommendation.explanation,
              ),
          }
        : null;
    const analysisNotes = normalizeStringList(payload.analysis_notes);
    const warnings = normalizeStringList(payload.warnings);
    const mismatches = normalizeInvoiceMismatches(payload.mismatches);
    const visibleMismatches = mismatches.slice(0, 5);
    const hiddenMismatchCount = Math.max(
        0,
        mismatches.length - visibleMismatches.length,
    );
    const warningsToShow = warnings.slice(0, 3);
    const hiddenWarnings = Math.max(0, warnings.length - warningsToShow.length);

    return (
        <div className="mt-3 space-y-4 rounded-2xl border border-white/10 bg-slate-950/40 p-4">
            <div className="grid gap-4 text-sm text-white sm:grid-cols-2">
                <InfoItem label="Invoice" value={invoiceId} />
                <InfoItem label="Matching PO" value={poId} />
                <InfoItem
                    label="Receipts"
                    value={receiptIds.length > 0 ? receiptIds.join(', ') : '—'}
                />
                <InfoItem
                    label="Match score"
                    value={
                        matchScore === null
                            ? '—'
                            : formatPercentValue(matchScore)
                    }
                />
            </div>

            <div className="rounded-xl border border-white/10 bg-slate-950/60 p-4 text-sm text-slate-100">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant="outline"
                        className={cn(
                            'text-xs tracking-wide uppercase',
                            matchRecommendationBadgeClass(
                                recommendation?.status ?? null,
                            ),
                        )}
                    >
                        {formatMatchRecommendation(recommendation?.status)}
                    </Badge>
                    <span className="text-xs tracking-wide text-slate-400 uppercase">
                        Recommendation
                    </span>
                </div>
                <p className="mt-2 text-slate-100">
                    {recommendation?.explanation ??
                        'Copilot did not include a recommendation note.'}
                </p>
            </div>

            <div>
                <p className="text-xs tracking-wide text-slate-400 uppercase">
                    Mismatch summary
                </p>
                {visibleMismatches.length > 0 ? (
                    <div className="mt-2 space-y-2">
                        {visibleMismatches.map((mismatch, index) => (
                            <div
                                key={`${mismatch.type}-${mismatch.lineReference ?? index}`}
                                className="rounded-xl border border-white/10 bg-white/5 p-3 text-sm text-white"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <p className="font-semibold">
                                        {mismatch.lineReference
                                            ? `Line ${mismatch.lineReference}`
                                            : humanizeKey(mismatch.type)}
                                    </p>
                                    <Badge
                                        variant="outline"
                                        className={cn(
                                            'text-[10px] tracking-wide uppercase',
                                            matchSeverityBadgeClass(
                                                mismatch.severity,
                                            ),
                                        )}
                                    >
                                        {mismatch.severity}
                                    </Badge>
                                </div>
                                <p className="text-xs text-slate-400">
                                    {humanizeKey(mismatch.type)}
                                </p>
                                <p className="mt-1 text-sm text-slate-100">
                                    {mismatch.detail}
                                </p>
                                {mismatch.expected !== null ||
                                mismatch.actual !== null ? (
                                    <p className="mt-1 text-xs text-slate-400">
                                        {mismatch.expected !== null
                                            ? `Expected ${formatDecimalValue(mismatch.expected, 3)}`
                                            : null}
                                        {mismatch.expected !== null &&
                                        mismatch.actual !== null
                                            ? ' · '
                                            : null}
                                        {mismatch.actual !== null
                                            ? `Actual ${formatDecimalValue(mismatch.actual, 3)}`
                                            : null}
                                    </p>
                                ) : null}
                            </div>
                        ))}
                        {hiddenMismatchCount > 0 ? (
                            <p className="text-xs text-slate-400">
                                +{hiddenMismatchCount} more mismatches not
                                shown.
                            </p>
                        ) : null}
                    </div>
                ) : (
                    <div className="mt-2 rounded-xl border border-emerald-400/30 bg-emerald-950/20 p-3 text-sm text-emerald-100">
                        Three-way match looks clean. No mismatches detected.
                    </div>
                )}
            </div>

            {analysisNotes.length > 0 ? (
                <div>
                    <p className="text-xs tracking-wide text-slate-400 uppercase">
                        Analysis notes
                    </p>
                    <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                        {analysisNotes.slice(0, 4).map((note, index) => (
                            <li key={`${note}-${index}`}>{note}</li>
                        ))}
                    </ul>
                </div>
            ) : null}

            {warningsToShow.length > 0 ? (
                <div className="rounded-xl border border-amber-300/30 bg-amber-950/30 p-3 text-sm text-amber-100">
                    <p className="text-xs tracking-wide text-amber-200 uppercase">
                        Warnings
                    </p>
                    <ul className="mt-2 list-disc space-y-1 pl-5">
                        {warningsToShow.map((warning, index) => (
                            <li key={`${warning}-${index}`}>{warning}</li>
                        ))}
                    </ul>
                    {hiddenWarnings > 0 ? (
                        <p className="mt-2 text-xs text-amber-200/80">
                            +{hiddenWarnings} more warnings not shown.
                        </p>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

function normalizeInvoiceMismatches(entries: unknown): InvoiceMatchMismatch[] {
    if (!Array.isArray(entries)) {
        return [];
    }

    return entries
        .map((entry) => {
            if (!isRecord(entry)) {
                return null;
            }

            const type =
                normalizeStringValue(entry.type) ??
                normalizeTextValue(entry.type) ??
                'Mismatch';
            const lineReference =
                normalizeStringValue(entry.line_reference) ??
                normalizeTextValue(entry.line_reference) ??
                null;
            const detail =
                normalizeStringValue(entry.detail) ??
                normalizeTextValue(entry.detail) ??
                'Needs review.';

            return {
                type,
                lineReference,
                severity: normalizeMismatchSeverity(entry.severity),
                detail,
                expected: numberValue(entry.expected),
                actual: numberValue(entry.actual),
            } satisfies InvoiceMatchMismatch;
        })
        .filter((entry): entry is InvoiceMatchMismatch => Boolean(entry));
}

function normalizeMismatchSeverity(value: unknown): InvoiceMatchSeverity {
    if (typeof value === 'string') {
        const normalized = value.toLowerCase();
        if (normalized === 'warning' || normalized === 'risk') {
            return normalized;
        }
    }
    return 'info';
}

function matchSeverityBadgeClass(severity: InvoiceMatchSeverity): string {
    switch (severity) {
        case 'risk':
            return 'border-rose-400/40 text-rose-100';
        case 'warning':
            return 'border-amber-300/40 text-amber-100';
        default:
            return 'border-sky-300/40 text-sky-100';
    }
}

function matchRecommendationBadgeClass(status: string | null): string {
    switch ((status ?? '').toLowerCase()) {
        case 'approve':
            return 'border-emerald-300/40 text-emerald-100';
        case 'hold':
            return 'border-amber-300/40 text-amber-100';
        default:
            return 'border-white/20 text-white';
    }
}

function formatMatchRecommendation(status?: string | null): string {
    if (!status) {
        return 'Review';
    }
    return humanizeKey(status);
}

function WorkflowPreview({
    workflow,
    onStartWorkflow,
    isStarting = false,
}: {
    workflow: AiChatWorkflowSuggestion;
    onStartWorkflow?: (
        workflow: AiChatWorkflowSuggestion,
    ) => Promise<string | null>;
    isStarting?: boolean;
}) {
    const [startedWorkflowId, setStartedWorkflowId] = useState<string | null>(
        null,
    );

    const handleStart = async () => {
        if (!onStartWorkflow || isStarting) {
            return;
        }

        const workflowId = await onStartWorkflow(workflow);
        if (workflowId) {
            setStartedWorkflowId(workflowId);
        }
    };

    return (
        <div className="space-y-3 rounded-2xl border border-white/10 bg-slate-900/50 p-4">
            <div className="flex items-center justify-between gap-3 text-sm font-semibold text-white">
                <span className="inline-flex items-center gap-2">
                    <GitBranch className="size-4" />{' '}
                    {formatWorkflowLabel(workflow.workflow_type)} workflow
                </span>
                {startedWorkflowId ? (
                    <Badge
                        variant="secondary"
                        className="bg-emerald-500/10 text-emerald-200"
                    >
                        #{startedWorkflowId}
                    </Badge>
                ) : null}
            </div>
            <ul className="space-y-3">
                {workflow.steps.map((step, index) => (
                    <li
                        key={`${step.title}-${index}`}
                        className="rounded-xl border border-white/5 bg-white/5 p-3"
                    >
                        <p className="text-sm font-semibold text-white">
                            {index + 1}. {step.title}
                        </p>
                        <p className="text-sm text-slate-200">{step.summary}</p>
                    </li>
                ))}
            </ul>

            {onStartWorkflow ? (
                <div className="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                    {startedWorkflowId ? (
                        <div className="flex flex-wrap items-center gap-3 text-sm text-slate-200">
                            <span>
                                Workflow #{startedWorkflowId} queued for review.
                            </span>
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                className="bg-slate-800/60 text-white"
                                onClick={() =>
                                    openWorkflowDashboard(startedWorkflowId)
                                }
                            >
                                Open workflow
                            </Button>
                        </div>
                    ) : (
                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                type="button"
                                className="gap-2"
                                disabled={isStarting}
                                onClick={() => void handleStart()}
                            >
                                {isStarting ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : null}
                                Start workflow
                            </Button>
                            <p className="text-xs text-slate-400">
                                Steps wait for human approval before executing.
                            </p>
                        </div>
                    )}
                </div>
            ) : null}
        </div>
    );
}

function GuidedResolutionCard({
    resolution,
    onChangeLocale,
    disableLocaleSwitch = false,
}: {
    resolution: AiChatGuidedResolution;
    onChangeLocale?: (locale: string) => void;
    disableLocaleSwitch?: boolean;
}) {
    const ctaLabel = resolution.cta_label?.trim().length
        ? resolution.cta_label
        : 'Open guide';
    const ctaHref = resolution.cta_url ?? undefined;
    const hasLink = Boolean(ctaHref);
    const currentLocale =
        (resolution.locale ?? '').trim().toLowerCase() || 'en';
    const localeLabel = formatGuideLocale(currentLocale);
    const availableLocales = Array.from(
        new Set(
            (resolution.available_locales ?? [])
                .map((code) => (code ?? '').trim().toLowerCase())
                .filter((code) => code.length > 0),
        ),
    );

    if (!availableLocales.includes(currentLocale)) {
        availableLocales.unshift(currentLocale);
    }

    const localeOptions =
        availableLocales.length > 0 ? availableLocales : [currentLocale];
    const canSwitchLocale = Boolean(onChangeLocale) && localeOptions.length > 1;
    const handleLocaleChange = (nextLocale: string) => {
        if (!onChangeLocale || nextLocale === currentLocale) {
            return;
        }

        onChangeLocale(nextLocale);
    };

    return (
        <div className="space-y-3 rounded-2xl border border-sky-400/30 bg-sky-950/30 p-4">
            <div className="flex items-center gap-2 text-xs tracking-wide text-sky-200 uppercase">
                <Sparkles className="size-4" /> Guided resolution suggested
                {localeLabel ? (
                    <Badge
                        variant="outline"
                        className="border-sky-200/30 text-[10px] text-sky-50 uppercase"
                    >
                        {localeLabel}
                    </Badge>
                ) : null}
            </div>
            <div>
                <p className="text-sm font-semibold text-white">
                    {resolution.title}
                </p>
                <p className="mt-1 text-sm text-slate-200">
                    {resolution.description}
                </p>
            </div>
            {canSwitchLocale ? (
                <div className="flex flex-wrap items-center gap-2 text-[11px] tracking-wide text-slate-300 uppercase">
                    <span>View in</span>
                    <Select
                        value={currentLocale}
                        onValueChange={handleLocaleChange}
                        disabled={disableLocaleSwitch}
                    >
                        <SelectTrigger className="h-8 w-[140px] border-white/20 bg-white/5 text-[11px] text-white uppercase">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent align="start" className="text-slate-900">
                            {localeOptions.map((localeCode) => (
                                <SelectItem
                                    key={localeCode}
                                    value={localeCode}
                                    className="text-sm uppercase"
                                >
                                    {formatGuideLocale(localeCode) ||
                                        localeCode.toUpperCase()}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            ) : null}
            {hasLink ? (
                <Button asChild className="gap-2">
                    <a href={ctaHref} target="_blank" rel="noreferrer">
                        {ctaLabel}
                    </a>
                </Button>
            ) : null}
        </div>
    );
}

function InfoItem({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div>
            <p className="text-xs tracking-wide text-slate-400 uppercase">
                {label}
            </p>
            <p className="text-base font-semibold text-white">{value}</p>
        </div>
    );
}

function QuickReplyRail({
    replies,
    disabled,
    onSelect,
}: {
    replies: string[];
    disabled: boolean;
    onSelect: (value: string) => void;
}) {
    return (
        <div className="border-t border-white/5 px-5 py-4">
            <p className="text-xs tracking-wide text-slate-500 uppercase">
                Suggested follow-ups
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
                {replies.map((reply) => (
                    <Button
                        key={reply}
                        variant="secondary"
                        size="sm"
                        className="bg-white/10 text-white"
                        disabled={disabled}
                        onClick={() => onSelect(reply)}
                    >
                        {reply}
                    </Button>
                ))}
            </div>
        </div>
    );
}

function ThinkingMessage() {
    return (
        <div className="flex items-center gap-3 text-sm text-slate-400">
            <AssistantAvatar />
            <div className="flex items-center gap-2 rounded-2xl border border-white/5 bg-white/5 px-4 py-3">
                <Loader2 className="size-4 animate-spin" /> Copilot is grounding
                the next turn…
            </div>
        </div>
    );
}

function AssistantAvatar() {
    return (
        <span className="inline-flex size-10 items-center justify-center rounded-2xl bg-white/10 text-white">
            <Bot className="size-5" />
        </span>
    );
}

function UserAvatar() {
    return (
        <span className="inline-flex size-10 items-center justify-center rounded-2xl border border-white/10 bg-white/20 text-white">
            <User className="size-5" />
        </span>
    );
}

function CollapsibleCard({
    title,
    children,
    open,
    onToggle,
}: {
    title: string;
    children: ReactNode;
    open: boolean;
    onToggle: () => void;
}) {
    return (
        <div className="rounded-2xl border border-white/10 bg-slate-900/50">
            <button
                type="button"
                className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-semibold text-white"
                onClick={onToggle}
            >
                {title}
                <ChevronDown
                    className={cn(
                        'size-4 transition-transform',
                        open ? 'rotate-180' : '',
                    )}
                />
            </button>
            {open ? (
                <div className="border-t border-white/5 px-4 py-4 text-sm text-slate-200">
                    {children}
                </div>
            ) : null}
        </div>
    );
}

function MarkdownText({ markdown }: { markdown: string }) {
    const blocks = markdown
        .split(/\n{2,}/)
        .map((block) => block.trim())
        .filter((block) => block.length > 0);

    if (blocks.length === 0) {
        return (
            <p className="text-sm leading-relaxed whitespace-pre-wrap">
                {markdown}
            </p>
        );
    }

    return (
        <div className="space-y-2 text-sm leading-relaxed">
            {blocks.map((block, index) => {
                const lines = block
                    .split('\n')
                    .map((line) => line.trim())
                    .filter((line) => line.length > 0);
                const isList = lines.every((line) => /^[-*]\s+/.test(line));
                if (isList) {
                    return (
                        <ul
                            key={`${block}-${index}`}
                            className="list-disc space-y-1 pl-5"
                        >
                            {lines.map((line, lineIndex) => (
                                <li key={`${line}-${lineIndex}`}>
                                    {renderInlineMarkdown(
                                        line.replace(/^[-*]\s+/, ''),
                                    )}
                                </li>
                            ))}
                        </ul>
                    );
                }
                return (
                    <p
                        key={`${block}-${index}`}
                        className="whitespace-pre-wrap"
                    >
                        {renderInlineMarkdown(block)}
                    </p>
                );
            })}
        </div>
    );
}

function renderInlineMarkdown(text: string): ReactNode {
    const boldPattern = /\*\*(.+?)\*\*/g;
    const nodes: ReactNode[] = [];
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    while ((match = boldPattern.exec(text)) !== null) {
        if (match.index > lastIndex) {
            nodes.push(text.slice(lastIndex, match.index));
        }
        nodes.push(
            <strong
                key={`${match.index}-${match[1]}`}
                className="font-semibold"
            >
                {match[1]}
            </strong>,
        );
        lastIndex = match.index + match[0].length;
    }

    if (lastIndex < text.length) {
        nodes.push(text.slice(lastIndex));
    }

    return nodes.length > 0 ? nodes : text;
}

function renderValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (Array.isArray(value)) {
        return value.map((entry) => renderValue(entry)).join(', ');
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function formatDateLabel(value?: string | null): string {
    if (!value) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
        }).format(new Date(value));
    } catch {
        return value;
    }
}

function formatCurrencyValue(amount: number, currency = 'USD'): string {
    if (!Number.isFinite(amount)) {
        return '—';
    }

    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    } catch {
        return `${currency} ${amount.toFixed(2)}`;
    }
}

function formatDecimalValue(
    value: number | null,
    maximumFractionDigits = 2,
): string {
    if (
        typeof value !== 'number' ||
        Number.isNaN(value) ||
        !Number.isFinite(value)
    ) {
        return '—';
    }

    const hasFraction = Math.abs(value % 1) > 0.0001;

    try {
        return new Intl.NumberFormat('en-US', {
            maximumFractionDigits,
            minimumFractionDigits: hasFraction
                ? Math.min(2, maximumFractionDigits)
                : 0,
        }).format(value);
    } catch {
        return value.toFixed(
            hasFraction ? Math.min(2, maximumFractionDigits) : 0,
        );
    }
}

function formatQuantityValue(
    value: number | null,
    uom?: string | null,
): string {
    const label = formatDecimalValue(value, 3);
    if (label === '—') {
        return label;
    }

    return uom ? `${label} ${uom}` : label;
}

function normalizeStringList(value: unknown): string[] {
    if (typeof value === 'string') {
        const trimmed = value.trim();
        return trimmed === '' ? [] : [trimmed];
    }

    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((entry) => {
            if (typeof entry === 'string') {
                const trimmed = entry.trim();
                return trimmed === '' ? null : trimmed;
            }
            if (typeof entry === 'number' && Number.isFinite(entry)) {
                return entry.toString();
            }
            return null;
        })
        .filter((entry): entry is string => Boolean(entry));
}

function numberValue(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (!trimmed) {
            return null;
        }
        const parsed = Number(trimmed);
        return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
}

function formatPercentValue(value: number): string {
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'percent',
            maximumFractionDigits: value === 0 ? 0 : 1,
        }).format(value);
    } catch {
        return `${(value * 100).toFixed(1)}%`;
    }
}

function normalizeCurrencyCode(value: unknown): string | null {
    const code = normalizeStringValue(value);
    return code ? code.toUpperCase() : null;
}

function humanizeKey(key: string): string {
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatActionLabel(value: string): string {
    return value
        .split('_')
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(' ');
}

function formatWorkflowLabel(value: string): string {
    return formatActionLabel(value);
}

function buildWorkflowStartVariables(
    workflow: AiChatWorkflowSuggestion,
): StartAiWorkflowVariables {
    const payload = isRecord(workflow.payload) ? workflow.payload : {};
    const rawInputs = isRecord(payload.inputs)
        ? payload.inputs
        : Object.fromEntries(
              Object.entries(payload).filter(
                  ([key]) => !['goal', 'rfq_id', 'inputs'].includes(key),
              ),
          );

    return {
        workflow_type: workflow.workflow_type,
        rfq_id: normalizeStringValue(payload.rfq_id),
        goal: normalizeStringValue(payload.goal),
        inputs: rawInputs,
    };
}

function extractAnalyticsCards(
    response: AiChatAssistantResponse | null,
): AnalyticsCardViewModel[] {
    if (!response || !ANALYTICS_RESPONSE_TYPES.has(response.type)) {
        return [];
    }

    const rawCards = getAnalyticsPayloads(response);
    const normalizedCards = rawCards
        .map((card) => normalizeAnalyticsCardPayload(card))
        .filter((card): card is AnalyticsCardViewModel => Boolean(card));

    if (normalizedCards.length > 0) {
        return normalizedCards;
    }

    return buildCardsFromToolResults(response);
}

type AnalyticsResponsePayload = AiChatAssistantResponse & {
    analytics_cards?: unknown;
    analytics?: unknown;
};

function getAnalyticsPayloads(response: AnalyticsResponsePayload): unknown[] {
    if (Array.isArray(response.analytics_cards)) {
        return response.analytics_cards;
    }

    if (Array.isArray(response.analytics)) {
        return response.analytics;
    }

    return [];
}

function normalizeAnalyticsCardPayload(
    card: unknown,
): AnalyticsCardViewModel | null {
    if (!isRecord(card)) {
        return null;
    }

    const title = normalizeTextValue(card.title ?? card.metric ?? card.type);
    const metric = normalizeAnalyticsMetric(card.metric);
    const chartData =
        normalizeChartData(card.chartData) ||
        normalizeChartData(card.chart_data) ||
        normalizeChartData(card.series);

    if (!title || chartData.length === 0) {
        return null;
    }

    const summary = normalizeTextValue(
        card.summary ?? card.description ?? card.subtitle,
    );
    const citations = normalizeAnalyticsCitations(card.citations);

    return {
        metric,
        title,
        chartData,
        summary,
        citations,
    };
}

function normalizeAnalyticsMetric(value: unknown): AiAnalyticsMetric | null {
    if (typeof value !== 'string') {
        return null;
    }

    return (KNOWN_ANALYTICS_METRICS as ReadonlyArray<string>).includes(value)
        ? (value as AiAnalyticsMetric)
        : null;
}

function normalizeChartData(value: unknown): AiAnalyticsChartDatum[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((entry) => {
            if (!isRecord(entry)) {
                return null;
            }

            const label = normalizeTextValue(
                entry.label ?? entry.name ?? entry.metric,
            );
            const numericValue = numberValue(
                entry.value ?? entry.score ?? entry.amount,
            );

            if (!label || numericValue === null) {
                return null;
            }

            return {
                label,
                value: numericValue,
            } satisfies AiAnalyticsChartDatum;
        })
        .filter((datum): datum is AiAnalyticsChartDatum => Boolean(datum));
}

function normalizeAnalyticsCitations(value: unknown): AiAnalyticsCitation[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((entry, index): AiAnalyticsCitation | null => {
            if (typeof entry === 'string') {
                const label = normalizeTextValue(entry);
                return label ? { id: `${label}-${index}`, label } : null;
            }

            if (!isRecord(entry)) {
                return null;
            }

            const label =
                normalizeTextValue(entry.label) ??
                normalizeTextValue(entry.title) ??
                normalizeTextValue(entry.name) ??
                normalizeTextValue(entry.snippet);
            const docId = normalizeTextValue(entry.doc_id);
            const fallbackLabel =
                label ?? (docId ? `Document #${docId}` : null);

            if (!fallbackLabel) {
                return null;
            }

            const source = normalizeTextValue(
                entry.source ?? entry.source_type,
            );
            const url = normalizeTextValue(
                entry.url ?? entry.link ?? entry.download_url ?? entry.href,
            );
            const citationId =
                normalizeCitationId(entry.id) ??
                docId ??
                (typeof entry.chunk_id === 'number'
                    ? `${fallbackLabel}-${entry.chunk_id}`
                    : null) ??
                `${fallbackLabel}-${index}`;

            return {
                id: citationId,
                label: fallbackLabel,
                source,
                url,
            };
        })
        .filter((citation): citation is AiAnalyticsCitation =>
            Boolean(citation),
        );
}

function normalizeCitationId(value: unknown): string | number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    return normalizeTextValue(value);
}

function buildCardsFromToolResults(
    response: AiChatAssistantResponse,
): AnalyticsCardViewModel[] {
    const toolResults = Array.isArray(response.tool_results)
        ? response.tool_results
        : [];
    if (toolResults.length === 0) {
        return [];
    }

    const fallbackCitations = normalizeAnalyticsCitations(response.citations);

    return toolResults
        .map((result) => {
            if (!result || typeof result.tool_name !== 'string') {
                return null;
            }

            const metric = TOOL_TO_ANALYTICS_METRIC[result.tool_name];
            if (!metric || !isRecord(result.result)) {
                return null;
            }

            return buildAnalyticsCardFromTool(
                metric,
                result.result,
                fallbackCitations,
            );
        })
        .filter((card): card is AnalyticsCardViewModel => Boolean(card));
}

function buildAnalyticsCardFromTool(
    metric: AiAnalyticsMetric,
    payload: Record<string, unknown>,
    fallbackCitations: AiAnalyticsCitation[],
): AnalyticsCardViewModel | null {
    switch (metric) {
        case 'forecast_spend':
            return buildForecastSpendCard(payload, fallbackCitations);
        case 'forecast_supplier_performance':
            return buildSupplierPerformanceCard(payload, fallbackCitations);
        case 'forecast_inventory':
            return buildInventoryForecastCard(payload, fallbackCitations);
        default:
            return null;
    }
}

function buildForecastSpendCard(
    payload: Record<string, unknown>,
    fallbackCitations: AiAnalyticsCitation[],
): AnalyticsCardViewModel | null {
    const projectedTotal = numberValue(payload.projected_total);
    const periodDays = numberValue(payload.projected_period_days);
    const confidenceInterval = isRecord(payload.confidence_interval)
        ? payload.confidence_interval
        : null;
    const lowerBound = confidenceInterval
        ? numberValue(confidenceInterval.lower)
        : null;
    const upperBound = confidenceInterval
        ? numberValue(confidenceInterval.upper)
        : null;

    const chartData: AiAnalyticsChartDatum[] = [];
    if (projectedTotal !== null) {
        chartData.push({
            label: periodDays ? `Projected ${periodDays}d` : 'Projected spend',
            value: projectedTotal,
        });
    }
    if (lowerBound !== null) {
        chartData.push({ label: 'Lower bound', value: lowerBound });
    }
    if (upperBound !== null) {
        chartData.push({ label: 'Upper bound', value: upperBound });
    }

    if (chartData.length === 0) {
        return null;
    }

    const drivers = Array.isArray(payload.drivers)
        ? payload.drivers
              .map((driver) =>
                  typeof driver === 'string' ? driver.trim() : '',
              )
              .filter((driver) => driver.length > 0)
        : [];

    const summaryParts: string[] = [];
    const category = normalizeTextValue(payload.category);
    if (category) {
        summaryParts.push(`Category ${category}`);
    }
    if (projectedTotal !== null) {
        summaryParts.push(
            `Projected spend ${formatCurrencyValue(projectedTotal, 'USD')}`,
        );
    }
    if (lowerBound !== null && upperBound !== null) {
        summaryParts.push(
            `Confidence ${formatCurrencyValue(lowerBound, 'USD')} – ${formatCurrencyValue(upperBound, 'USD')}`,
        );
    }
    if (drivers.length > 0) {
        summaryParts.push(drivers.slice(0, 2).join(' '));
    }

    const citations = normalizeAnalyticsCitations(payload.citations);

    return {
        metric: 'forecast_spend',
        title: 'Spend forecast',
        chartData,
        summary: summaryParts.filter(Boolean).join(' • ') || undefined,
        citations: citations.length > 0 ? citations : fallbackCitations,
        valueFormatter: (value) => formatCurrencyValue(value, 'USD'),
    };
}

function buildSupplierPerformanceCard(
    payload: Record<string, unknown>,
    fallbackCitations: AiAnalyticsCitation[],
): AnalyticsCardViewModel | null {
    const projection = numberValue(payload.projection);
    const confidenceInterval = isRecord(payload.confidence_interval)
        ? payload.confidence_interval
        : null;
    const lowerBound = confidenceInterval
        ? numberValue(confidenceInterval.lower)
        : null;
    const upperBound = confidenceInterval
        ? numberValue(confidenceInterval.upper)
        : null;
    const periodDays = numberValue(payload.period_days);

    const chartData: AiAnalyticsChartDatum[] = [];
    if (projection !== null) {
        chartData.push({ label: 'Projection', value: projection });
    }
    if (lowerBound !== null) {
        chartData.push({ label: 'Lower bound', value: lowerBound });
    }
    if (upperBound !== null) {
        chartData.push({ label: 'Upper bound', value: upperBound });
    }

    if (chartData.length === 0) {
        return null;
    }

    const metricName = normalizeTextValue(payload.metric) ?? 'Performance';
    const supplierId = normalizeTextValue(payload.supplier_id);
    const summaryParts: string[] = [];

    if (projection !== null) {
        summaryParts.push(
            `${metricName} projected at ${formatPercentValue(projection)}`,
        );
    }
    if (periodDays !== null) {
        summaryParts.push(`Window ${periodDays} days`);
    }
    if (lowerBound !== null && upperBound !== null) {
        summaryParts.push(
            `Confidence ${formatPercentValue(lowerBound)} – ${formatPercentValue(upperBound)}`,
        );
    }

    const title = supplierId
        ? `Supplier ${supplierId} · ${metricName}`
        : `${metricName} forecast`;
    const citations = normalizeAnalyticsCitations(payload.citations);

    return {
        metric: 'forecast_supplier_performance',
        title,
        chartData,
        summary: summaryParts.filter(Boolean).join(' • ') || undefined,
        citations: citations.length > 0 ? citations : fallbackCitations,
        valueFormatter: (value) => formatPercentValue(value),
    };
}

function buildInventoryForecastCard(
    payload: Record<string, unknown>,
    fallbackCitations: AiAnalyticsCitation[],
): AnalyticsCardViewModel | null {
    const expectedUsage = numberValue(payload.expected_usage);
    const safetyStock = numberValue(payload.safety_stock);
    const periodDays = numberValue(payload.period_days);
    const reorderDate = normalizeTextValue(payload.expected_reorder_date);

    const chartData: AiAnalyticsChartDatum[] = [];
    if (expectedUsage !== null) {
        chartData.push({ label: 'Expected usage', value: expectedUsage });
    }
    if (safetyStock !== null) {
        chartData.push({ label: 'Safety stock', value: safetyStock });
    }

    if (chartData.length === 0) {
        return null;
    }

    const summaryParts: string[] = [];
    if (periodDays !== null) {
        summaryParts.push(`Window ${periodDays} days`);
    }
    if (reorderDate) {
        summaryParts.push(`Reorder by ${formatDateLabel(reorderDate)}`);
    }
    if (expectedUsage !== null) {
        summaryParts.push(`Usage ${formatWholeNumber(expectedUsage)} units`);
    }
    if (safetyStock !== null) {
        summaryParts.push(
            `Safety stock ${formatWholeNumber(safetyStock)} units`,
        );
    }

    const itemId = normalizeTextValue(payload.item_id);
    const citations = normalizeAnalyticsCitations(payload.citations);

    return {
        metric: 'forecast_inventory',
        title: itemId ? `Inventory forecast · ${itemId}` : 'Inventory forecast',
        chartData,
        summary: summaryParts.filter(Boolean).join(' • ') || undefined,
        citations: citations.length > 0 ? citations : fallbackCitations,
        valueFormatter: (value) => formatWholeNumber(value),
    };
}

function formatWholeNumber(value: number): string {
    if (!Number.isFinite(value)) {
        return '—';
    }

    try {
        const maximumFractionDigits = Math.abs(value) < 10 ? 1 : 0;
        return new Intl.NumberFormat('en-US', { maximumFractionDigits }).format(
            value,
        );
    } catch {
        return value.toString();
    }
}

function normalizeTextValue(value: unknown): string | null {
    if (typeof value === 'string') {
        const trimmed = value.trim();
        return trimmed === '' ? null : trimmed;
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
        return value.toString();
    }

    return null;
}

function normalizeStringValue(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

const WORKFLOW_DASHBOARD_PATH = '/app/ai/workflows'; // TODO: confirm deep link target per workflow spec.

function buildWorkflowDashboardUrl(workflowId: string): string {
    const params = new URLSearchParams({ workflow_id: workflowId });

    return `${WORKFLOW_DASHBOARD_PATH}?${params.toString()}`;
}

function openWorkflowDashboard(workflowId: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    const url = buildWorkflowDashboardUrl(workflowId);
    window.open(url, '_blank', 'noreferrer');
}

function formatDraftStatus(status?: AiActionDraftStatus): string {
    switch (status) {
        case 'approved':
            return 'Approved';
        case 'rejected':
            return 'Rejected';
        case 'expired':
            return 'Expired';
        default:
            return 'Needs review';
    }
}

function draftStatusBadgeClass(status?: AiActionDraftStatus): string {
    switch (status) {
        case 'approved':
            return 'border-emerald-400/40 text-emerald-100';
        case 'rejected':
            return 'border-rose-400/40 text-rose-100';
        case 'expired':
            return 'border-slate-400/40 text-slate-200';
        default:
            return 'border-amber-300/40 text-amber-100';
    }
}

function describeDraftEntity(draft?: AiActionDraft | null): string | null {
    if (!draft) {
        return null;
    }

    const entityType = draft.entity_type
        ? humanizeKey(draft.entity_type)
        : null;
    const entityId = draft.entity_id ?? draft.payload?.entity_id;
    const normalizedId =
        typeof entityId === 'number' || typeof entityId === 'string'
            ? String(entityId)
            : null;

    if (entityType && normalizedId) {
        return `${entityType} #${normalizedId}`;
    }

    if (entityType) {
        return entityType;
    }

    if (normalizedId) {
        return `Record #${normalizedId}`;
    }

    if (draft.action_type) {
        return humanizeKey(draft.action_type);
    }

    return draft.summary ?? null;
}

function formatDisplayTimestamp(value?: string | null): string {
    if (!value) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat('en-US', {
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date(value));
    } catch {
        return value;
    }
}

function extractAssistantResponse(
    message: AiChatMessage,
): AiChatAssistantResponse | null {
    if (
        message.content &&
        typeof message.content === 'object' &&
        'assistant_message_markdown' in message.content
    ) {
        return message.content as AiChatAssistantResponse;
    }

    return null;
}

function PanelSkeleton() {
    return (
        <div className="space-y-4">
            {[0, 1, 2].map((index) => (
                <div key={index} className="animate-pulse">
                    <div className="mb-2 h-4 w-1/3 rounded-full bg-white/10" />
                    <div className="h-20 rounded-3xl bg-white/5" />
                </div>
            ))}
        </div>
    );
}

function EmptyConversationState() {
    return (
        <div className="flex h-full flex-col items-center justify-center rounded-3xl border border-dashed border-white/10 bg-white/5 p-8 text-center text-slate-300">
            <p className="text-sm font-semibold tracking-wide text-slate-100">
                Ask anything about RFQs, quotes, or inventory.
            </p>
            <p className="mt-2 text-sm text-slate-400">
                Copilot cites every answer and stays within your workspace data.
            </p>
        </div>
    );
}

function HelpLanguageSelector({
    value,
    onChange,
}: {
    value: string;
    onChange: (locale: string) => void;
}) {
    return (
        <div className="flex items-center gap-2 text-[11px] tracking-wide text-slate-400 uppercase">
            <span>Guide language</span>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger className="h-8 w-[140px] border-white/20 bg-white/5 text-[11px] text-white uppercase">
                    <SelectValue placeholder="Language" />
                </SelectTrigger>
                <SelectContent align="start" className="text-slate-900">
                    {HELP_LANGUAGE_OPTIONS.map((option) => (
                        <SelectItem
                            key={option.value}
                            value={option.value}
                            className="text-sm uppercase"
                        >
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function formatGuideLocale(locale?: string | null): string {
    if (!locale) {
        return '';
    }

    const normalized = locale.trim().toLowerCase();
    if (normalized === '') {
        return '';
    }

    const base = normalized.split('-')[0];
    return (
        GUIDE_LANGUAGE_LABELS[normalized] ??
        GUIDE_LANGUAGE_LABELS[base] ??
        normalized.toUpperCase()
    );
}
