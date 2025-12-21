import { type KeyboardEvent, type ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import { AlertTriangle, Bot, ChevronDown, GitBranch, Loader2, Send, Sparkles, User } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import { useAiChatMessages } from '@/hooks/api/ai/use-ai-chat-messages';
import { useAiChatSend } from '@/hooks/api/ai/use-ai-chat-send';
import { useAiDraftApprove, useAiDraftReject } from '@/hooks/api/ai/use-ai-draft-approval';
import { useStartAiWorkflow, type StartAiWorkflowVariables } from '@/hooks/api/ai/use-start-ai-workflow';
import { useAiChatThreads, useCreateAiChatThread } from '@/hooks/api/ai/use-ai-chat-threads';
import { cn } from '@/lib/utils';
import type {
    AiActionDraft,
    AiActionDraftStatus,
    AiChatAssistantResponse,
    AiChatDraftSnapshot,
    AiChatMessage,
    AiChatWorkflowSuggestion,
} from '@/types/ai-chat';

interface CopilotChatPanelProps {
    className?: string;
}

const RESPONSE_LABELS: Record<string, string> = {
    answer: 'Grounded answer',
    draft_action: 'Draft ready',
    workflow_suggestion: 'Workflow suggestion',
    tool_request: 'Workspace lookup needed',
    error: 'Unable to respond',
};

const FALLBACK_THREAD_TITLE = 'New conversation';

type DraftActionState = {
    approvingId: number | null;
    rejectingId: number | null;
    isApprovePending: boolean;
    isRejectPending: boolean;
};

export function CopilotChatPanel({ className }: CopilotChatPanelProps) {
    const [composerValue, setComposerValue] = useState('');
    const [activeThreadId, setActiveThreadId] = useState<number | null>(null);
    const bootstrapAttempted = useRef(false);
    const scrollRef = useRef<HTMLDivElement>(null);

    const threadsQuery = useAiChatThreads();
    const createThread = useCreateAiChatThread({
        onSuccess: (thread) => {
            setActiveThreadId(thread.id);
            bootstrapAttempted.current = true;
        },
    });
    const messagesQuery = useAiChatMessages(activeThreadId, {
        limit: 60,
        enabled: Boolean(activeThreadId),
    });
    const sendMutation = useAiChatSend(activeThreadId);
    const approveDraft = useAiDraftApprove(activeThreadId);
    const rejectDraft = useAiDraftReject(activeThreadId);
    const startWorkflow = useStartAiWorkflow(activeThreadId);

    const messages = messagesQuery.data?.messages ?? [];

    const visibleMessages = useMemo(() => {
        return messages.filter((message) => {
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
        if (activeThreadId || threadsQuery.isLoading) {
            return;
        }

        const firstThread = threadsQuery.data?.items?.[0];
        if (firstThread) {
            setActiveThreadId(firstThread.id);
        }
    }, [threadsQuery.data, threadsQuery.isLoading, activeThreadId]);

    useEffect(() => {
        if (bootstrapAttempted.current) {
            return;
        }

        if (threadsQuery.isLoading || threadsQuery.isFetching || createThread.isPending) {
            return;
        }

        if ((threadsQuery.data?.items?.length ?? 0) > 0) {
            return;
        }

        bootstrapAttempted.current = true;
        createThread.mutate(undefined, {
            onError: (error) => {
                bootstrapAttempted.current = false;
                publishToast({
                    variant: 'destructive',
                    title: 'Unable to start Copilot chat',
                    description: error instanceof Error ? error.message : 'Please try again.',
                });
            },
        });
    }, [threadsQuery.isLoading, threadsQuery.isFetching, threadsQuery.data, createThread]);

    useEffect(() => {
        const node = scrollRef.current;
        if (!node) {
            return;
        }

        node.scrollTop = node.scrollHeight;
    }, [messages, sendMutation.isPending, sendMutation.isStreaming]);

    const lastAssistantResponse = useMemo(() => {
        const assistant = [...messages]
            .reverse()
            .find((message) => {
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
    const approvingDraftId = approveDraft.isPending ? approveDraft.variables?.draftId ?? null : null;
    const rejectingDraftId = rejectDraft.isPending ? rejectDraft.variables?.draftId ?? null : null;
    const draftActionState = {
        approvingId: approvingDraftId,
        rejectingId: rejectingDraftId,
        isApprovePending: approveDraft.isPending,
        isRejectPending: rejectDraft.isPending,
    };

    const isThreadLoading = threadsQuery.isLoading || createThread.isPending;
    const isMessageLoading = Boolean(activeThreadId) && messagesQuery.isLoading;
    const showSkeleton = isThreadLoading || isMessageLoading;
    const showEmptyState = !showSkeleton && visibleMessages.length === 0;
    const activeThread = messagesQuery.data;
    const threadTitle = activeThread?.title ?? FALLBACK_THREAD_TITLE;

    const handleSend = async (preset?: string) => {
        if (!activeThreadId || sendMutation.isPending || sendMutation.isStreaming) {
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

        try {
            await sendMutation.mutateAsync({ message: trimmed });
        } catch (error) {
            if (!preset) {
                setComposerValue(previousDraft);
            }

            publishToast({
                variant: 'destructive',
                title: 'Unable to send message',
                description: error instanceof Error ? error.message : 'Please try again.',
            });
        }
    };

    const handleApproveDraftAction = async (draft: AiChatDraftSnapshot): Promise<boolean> => {
        if (!draft.draft_id) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to approve draft',
                description: 'Draft metadata is missing. Ask Copilot to regenerate it and try again.',
            });

            return false;
        }

        try {
            const result = await approveDraft.mutateAsync({ draftId: draft.draft_id });
            const entityDescription = describeDraftEntity(result.draft);

            publishToast({
                title: 'Draft approved',
                description: entityDescription ? `Created ${entityDescription}.` : 'Copilot saved your approval.',
            });

            return true;
        } catch (error) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to approve draft',
                description: error instanceof Error ? error.message : 'Please try again.',
            });

            return false;
        }
    };

    const handleRejectDraftAction = async (draft: AiChatDraftSnapshot, reason: string): Promise<boolean> => {
        if (!draft.draft_id) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to reject draft',
                description: 'Draft metadata is missing. Ask Copilot to regenerate it and try again.',
            });

            return false;
        }

        const trimmed = reason.trim();

        if (trimmed === '') {
            publishToast({
                variant: 'destructive',
                title: 'Add a rejection reason',
                description: 'Include a short note so Copilot can learn from the feedback.',
            });

            return false;
        }

        try {
            await rejectDraft.mutateAsync({ draftId: draft.draft_id, reason: trimmed });

            publishToast({
                title: 'Draft rejected',
                description: 'Copilot recorded your feedback.',
            });

            return true;
        } catch (error) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to reject draft',
                description: error instanceof Error ? error.message : 'Please try again.',
            });

            return false;
        }
    };

    const handleStartWorkflowSuggestion = async (workflow: AiChatWorkflowSuggestion): Promise<string | null> => {
        if (!workflow?.workflow_type) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to start workflow',
                description: 'Workflow metadata is missing. Ask Copilot to regenerate it and try again.',
            });

            return null;
        }

        if (!activeThreadId) {
            publishToast({
                variant: 'destructive',
                title: 'Chat thread not ready',
                description: 'Wait for Copilot to finish loading this thread, then try again.',
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
                description: error instanceof Error ? error.message : 'Please try again.',
            });

            return null;
        }
    };

    const handleComposerKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
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
                        <p className="text-sm uppercase tracking-[0.2em] text-slate-400">Procurement Copilot</p>
                        <div className="flex items-center gap-2">
                            <h2 className="text-lg font-semibold text-white">{threadTitle}</h2>
                            {activeThread?.status ? (
                                <Badge variant="outline" className="border-white/10 bg-white/5 text-xs uppercase tracking-wide text-white/70">
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

            <div ref={scrollRef} className="flex-1 space-y-6 overflow-y-auto px-5 py-6">
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
                        />
                    ))
                )}
                {sendMutation.isPending ? <ThinkingMessage /> : null}
            </div>

            {needsHumanReview ? <NeedsReviewBanner warnings={reviewWarnings} /> : null}

            {quickReplies.length > 0 ? (
                <QuickReplyRail
                    replies={quickReplies}
                    disabled={sendMutation.isPending || sendMutation.isStreaming}
                    onSelect={handleSend}
                />
            ) : null}

            <footer className="border-t border-white/5 p-5">
                <div className="rounded-2xl border border-white/10 bg-slate-900/60 p-4 shadow-inner">
                    <Textarea
                        value={composerValue}
                        onChange={(event) => setComposerValue(event.target.value)}
                        onKeyDown={handleComposerKeyDown}
                        disabled={!activeThreadId || isThreadLoading}
                        placeholder="Ask Copilot to summarize, draft, or compare. Shift + Enter for a new line."
                        className="min-h-[96px] resize-none border-0 bg-transparent text-base text-white placeholder:text-slate-500 focus-visible:ring-0 disabled:cursor-not-allowed"
                    />
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <p className="text-xs text-slate-500">Copilot cites every answer with doc ids or workspace tools.</p>
                        <Button
                            type="button"
                            size="sm"
                            disabled={!activeThreadId || isThreadLoading || sendMutation.isPending || sendMutation.isStreaming}
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
    onRejectDraft?: (draft: AiChatDraftSnapshot, reason: string) => Promise<boolean>;
    draftActionState?: DraftActionState;
    onStartWorkflow?: (workflow: AiChatWorkflowSuggestion) => Promise<string | null>;
    isWorkflowStarting?: boolean;
};

function ChatMessage({
    message,
    onApproveDraft,
    onRejectDraft,
    draftActionState,
    onStartWorkflow,
    isWorkflowStarting,
}: ChatMessageProps) {
    if (message.role === 'user') {
        return (
            <div className="flex items-start justify-end gap-3">
                <div className="hidden sm:block text-white">
                    <UserAvatar />
                </div>
                <div className="max-w-[80%] rounded-2xl bg-white px-4 py-3 text-primary shadow-lg">
                    <MarkdownText markdown={message.content_text ?? ''} />
                    <p className="mt-2 text-right text-xs text-primary">{formatDisplayTimestamp(message.created_at)}</p>
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
}: {
    message: AiChatMessage;
    onApproveDraft?: (draft: AiChatDraftSnapshot) => Promise<boolean>;
    onRejectDraft?: (draft: AiChatDraftSnapshot, reason: string) => Promise<boolean>;
    draftActionState?: DraftActionState;
    onStartWorkflow?: (workflow: AiChatWorkflowSuggestion) => Promise<string | null>;
    isWorkflowStarting?: boolean;
}) {
    const response = extractAssistantResponse(message);
    const label = response ? RESPONSE_LABELS[response.type] ?? 'Assistant' : 'Assistant';
    const markdown = response?.assistant_message_markdown ?? message.content_text ?? '';

    return (
        <div className="flex items-start gap-3">
            <AssistantAvatar />
            <div className="w-full max-w-[82%] rounded-2xl border border-white/5 bg-white/5 p-4 shadow-xl backdrop-blur">
                <div className="flex flex-wrap items-center gap-3">
                    <Badge variant="secondary" className="bg-white/10 text-xs font-semibold uppercase tracking-wide text-white">
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
                />

                <p className="mt-4 text-xs uppercase tracking-wide text-slate-400">{formatDisplayTimestamp(message.created_at)}</p>
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
                <p className="mt-4 text-xs uppercase tracking-wide text-slate-500">{formatDisplayTimestamp(message.created_at)}</p>
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
}: {
    response: AiChatAssistantResponse | null;
    onApproveDraft?: (draft: AiChatDraftSnapshot) => Promise<boolean>;
    onRejectDraft?: (draft: AiChatDraftSnapshot, reason: string) => Promise<boolean>;
    draftActionState?: DraftActionState;
    onStartWorkflow?: (workflow: AiChatWorkflowSuggestion) => Promise<string | null>;
    isWorkflowStarting?: boolean;
}) {
    const warnings = response?.warnings ?? [];
    const hasDraft = response?.type === 'draft_action' && Boolean(response.draft);
    const hasWorkflow = response?.type === 'workflow_suggestion' && Boolean(response.workflow);

    if (!warnings.length && !hasDraft && !hasWorkflow) {
        return null;
    }

    return (
        <div className="mt-4 space-y-4 text-slate-100">
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

            {hasDraft && response?.draft ? (
                <DraftPreview
                    draft={response.draft}
                    onApprove={onApproveDraft}
                    onReject={onRejectDraft}
                    isApproving={Boolean(
                        draftActionState?.isApprovePending && draftActionState?.approvingId === response.draft.draft_id,
                    )}
                    isRejecting={Boolean(
                        draftActionState?.isRejectPending && draftActionState?.rejectingId === response.draft.draft_id,
                    )}
                />
            ) : null}

            {hasWorkflow && response?.workflow ? (
                <WorkflowPreview
                    workflow={response.workflow}
                    onStartWorkflow={onStartWorkflow}
                    isStarting={Boolean(isWorkflowStarting)}
                />
            ) : null}
        </div>
    );
}

function NeedsReviewBanner({ warnings }: { warnings: string[] }) {
    const hasWarnings = warnings.length > 0;

    return (
        <div className="border-t border-white/5 bg-amber-950/30 px-5 py-4 hidden">
            <Alert variant="destructive" className="border-amber-200/50 bg-transparent text-amber-100">
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
                        <span>Copilot flagged the latest response for human review before sharing.</span>
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
}: {
    draft: AiChatDraftSnapshot;
    onApprove?: (draft: AiChatDraftSnapshot) => Promise<boolean>;
    onReject?: (draft: AiChatDraftSnapshot, reason: string) => Promise<boolean>;
    isApproving?: boolean;
    isRejecting?: boolean;
}) {
    const [open, setOpen] = useState(() => draft.status !== 'approved');
    const [rejectMode, setRejectMode] = useState(false);
    const [rejectReason, setRejectReason] = useState('');
    const payloadEntries = Object.entries(draft.payload ?? {});
    const status = draft.status;
    const summary = draft.summary ?? 'Copilot saved this draft so you can approve or reject it.';
    const isActionable = !status || status === 'drafted';
    const showActions = isActionable && (Boolean(onApprove) || Boolean(onReject));

    useEffect(() => {
        if (!showActions) {
            setRejectMode(false);
            setRejectReason('');
        }
    }, [showActions]);

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
                <Badge variant="outline" className={cn('border-white/10 text-xs uppercase tracking-wide', draftStatusBadgeClass(status))}>
                    {formatDraftStatus(status)}
                </Badge>
                {draft.entity_type ? (
                    <span className="text-[11px] uppercase tracking-wide text-slate-500">
                        Target · {humanizeKey(draft.entity_type)}
                        {draft.entity_id ? ` #${draft.entity_id}` : ''}
                    </span>
                ) : null}
            </div>
            <p className="mt-2 text-sm text-slate-200">{summary}</p>
            {payloadEntries.length > 0 ? (
                <dl className="mt-3 space-y-2 text-sm">
                    {payloadEntries.map(([key, value]) => (
                        <div key={key}>
                            <dt className="text-xs uppercase tracking-wide text-slate-400">{humanizeKey(key)}</dt>
                            <dd className="text-slate-100">{renderValue(value)}</dd>
                        </div>
                    ))}
                </dl>
            ) : null}

            {showActions ? (
                <div className="mt-5 space-y-3 rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p className="text-xs text-slate-400">Approvals and rejections log to the thread so your team sees who cleared it.</p>
                    <div className="flex flex-wrap gap-2">
                        {onApprove ? (
                            <Button
                                type="button"
                                className="gap-2"
                                disabled={isApproving || isRejecting}
                                onClick={() => void handleApproveClick()}
                            >
                                {isApproving ? <Loader2 className="size-4 animate-spin" /> : null}
                                Approve draft
                            </Button>
                        ) : null}
                        {onReject && !rejectMode ? (
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
                                onChange={(event) => setRejectReason(event.target.value)}
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
                                    disabled={isRejecting || rejectReason.trim().length === 0}
                                    onClick={() => void handleRejectSubmit()}
                                >
                                    {isRejecting ? <Loader2 className="size-4 animate-spin" /> : null}
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

function WorkflowPreview({
    workflow,
    onStartWorkflow,
    isStarting = false,
}: {
    workflow: AiChatWorkflowSuggestion;
    onStartWorkflow?: (workflow: AiChatWorkflowSuggestion) => Promise<string | null>;
    isStarting?: boolean;
}) {
    const [startedWorkflowId, setStartedWorkflowId] = useState<string | null>(null);

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
                    <GitBranch className="size-4" /> {formatWorkflowLabel(workflow.workflow_type)} workflow
                </span>
                {startedWorkflowId ? (
                    <Badge variant="secondary" className="bg-emerald-500/10 text-emerald-200">
                        #{startedWorkflowId}
                    </Badge>
                ) : null}
            </div>
            <ul className="space-y-3">
                {workflow.steps.map((step, index) => (
                    <li key={`${step.title}-${index}`} className="rounded-xl border border-white/5 bg-white/5 p-3">
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
                            <span>Workflow #{startedWorkflowId} queued for review.</span>
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                className="bg-slate-800/60 text-white"
                                onClick={() => openWorkflowDashboard(startedWorkflowId)}
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
                                {isStarting ? <Loader2 className="size-4 animate-spin" /> : null}
                                Start workflow
                            </Button>
                            <p className="text-xs text-slate-400">Steps wait for human approval before executing.</p>
                        </div>
                    )}
                </div>
            ) : null}
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
            <p className="text-xs uppercase tracking-wide text-slate-500">Suggested follow-ups</p>
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
                <Loader2 className="size-4 animate-spin" /> Copilot is grounding the next turn…
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
                <ChevronDown className={cn('size-4 transition-transform', open ? 'rotate-180' : '')} />
            </button>
            {open ? <div className="border-t border-white/5 px-4 py-4 text-sm text-slate-200">{children}</div> : null}
        </div>
    );
}

function MarkdownText({ markdown }: { markdown: string }) {
    const blocks = markdown
        .split(/\n{2,}/)
        .map((block) => block.trim())
        .filter((block) => block.length > 0);

    if (blocks.length === 0) {
        return <p className="text-sm leading-relaxed whitespace-pre-wrap">{markdown}</p>;
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
                        <ul key={`${block}-${index}`} className="list-disc space-y-1 pl-5">
                            {lines.map((line, lineIndex) => (
                                <li key={`${line}-${lineIndex}`}>{renderInlineMarkdown(line.replace(/^[-*]\s+/, ''))}</li>
                            ))}
                        </ul>
                    );
                }
                return (
                    <p key={`${block}-${index}`} className="whitespace-pre-wrap">
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
            <strong key={`${match.index}-${match[1]}`} className="font-semibold">
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

function buildWorkflowStartVariables(workflow: AiChatWorkflowSuggestion): StartAiWorkflowVariables {
    const payload = isRecord(workflow.payload) ? workflow.payload : {};
    const rawInputs = isRecord(payload.inputs)
        ? payload.inputs
        : Object.fromEntries(
              Object.entries(payload).filter(([key]) => !['goal', 'rfq_id', 'inputs'].includes(key)),
          );

    return {
        workflow_type: workflow.workflow_type,
        rfq_id: normalizeStringValue(payload.rfq_id),
        goal: normalizeStringValue(payload.goal),
        inputs: rawInputs,
    };
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

    const entityType = draft.entity_type ? humanizeKey(draft.entity_type) : null;
    const entityId = draft.entity_id ?? draft.payload?.entity_id;
    const normalizedId = typeof entityId === 'number' || typeof entityId === 'string' ? String(entityId) : null;

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

function extractAssistantResponse(message: AiChatMessage): AiChatAssistantResponse | null {
    if (message.content && typeof message.content === 'object' && 'assistant_message_markdown' in message.content) {
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
            <p className="text-sm font-semibold tracking-wide text-slate-100">Ask anything about RFQs, quotes, or inventory.</p>
            <p className="mt-2 text-sm text-slate-400">Copilot cites every answer and stays within your workspace data.</p>
        </div>
    );
}
