import { useEffect, useRef, useState } from 'react';
import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { api, ApiError } from '@/lib/api';
import { emitCopilotToolError } from '@/lib/copilot-events';
import { queryKeys } from '@/lib/queryKeys';
import type {
    AiChatAssistantResponse,
    AiChatMessage,
    AiChatMessageContextPayload,
    AiChatResolveToolsResponse,
    AiChatSendResponse,
    AiChatStreamPreparation,
    AiChatThreadResponse,
    AiChatWorkspaceToolCall,
} from '@/types/ai-chat';

interface SendMessageVariables {
    message: string;
    context?: AiChatMessageContextPayload;
}

interface MutationContext {
    queryKey: ReturnType<(typeof queryKeys)['ai']['chat']['thread']>;
    previous?: AiChatThreadResponse;
    optimisticId: number;
    threadId: number;
}

type SendMessageResult =
    | {
          mode: 'complete';
          initial: AiChatSendResponse;
          toolRuns: AiChatResolveToolsResponse[];
          contextPayload?: AiChatMessageContextPayload;
      }
    | {
          mode: 'stream';
          preparation: AiChatStreamPreparation;
          contextPayload?: AiChatMessageContextPayload;
      };

export type UseAiChatSendResult = UseMutationResult<SendMessageResult, ApiError, SendMessageVariables, MutationContext> & {
    isStreaming: boolean;
};

interface ActiveStreamSession {
    source: EventSource;
    placeholderId: number;
    queryKey: ReturnType<(typeof queryKeys)['ai']['chat']['thread']>;
    threadId: number;
    accumulatedText: string;
    pendingResponse: AiChatAssistantResponse | null;
    contextPayload?: AiChatMessageContextPayload;
}

const MAX_TOOL_ROUNDS = 3;
const STREAM_FALLBACK_STATUSES = new Set([502]);

const unwrap = <T>(payload: T | { data: T }): T => {
    if (payload && typeof payload === 'object' && 'data' in (payload as Record<string, unknown>)) {
        return (payload as { data: T }).data;
    }

    return payload as T;
};

const sanitizeContextPayload = (context?: AiChatMessageContextPayload): AiChatMessageContextPayload | undefined => {
    if (!context) {
        return undefined;
    }

    const normalized: AiChatMessageContextPayload = {};

    if (context.context && typeof context.context === 'object') {
        normalized.context = context.context;
    }

    if (typeof context.ui_mode === 'string') {
        const trimmed = context.ui_mode.trim();
        if (trimmed !== '') {
            normalized.ui_mode = trimmed;
        }
    }

    if (Array.isArray(context.attachments) && context.attachments.length > 0) {
        normalized.attachments = context.attachments;
    }

    if (typeof context.locale === 'string') {
        const trimmedLocale = context.locale.trim().toLowerCase();

        if (trimmedLocale !== '') {
            normalized.locale = trimmedLocale.slice(0, 10);
        }
    }

    const clarification = sanitizeClarificationReference(context.clarification);
    if (clarification) {
        normalized.clarification = clarification;
    }

    const entityPicker = sanitizeEntityPickerReference(context.entity_picker);
    if (entityPicker) {
        normalized.entity_picker = entityPicker;
    }

    return Object.keys(normalized).length > 0 ? normalized : undefined;
};

const sanitizeClarificationReference = (
    clarification?: AiChatMessageContextPayload['clarification'],
): AiChatMessageContextPayload['clarification'] | undefined => {
    if (!clarification || typeof clarification !== 'object') {
        return undefined;
    }

    const identifier = typeof clarification.id === 'string' ? clarification.id.trim() : '';

    if (identifier === '') {
        return undefined;
    }

    return { id: identifier.slice(0, 100) };
};

const sanitizeEntityPickerReference = (
    picker?: AiChatMessageContextPayload['entity_picker'],
): AiChatMessageContextPayload['entity_picker'] | undefined => {
    if (!picker || typeof picker !== 'object') {
        return undefined;
    }

    const identifier = typeof picker.id === 'string' ? picker.id.trim() : '';
    const candidateId = typeof picker.candidate_id === 'string' ? picker.candidate_id.trim() : '';

    if (identifier === '' || candidateId === '') {
        return undefined;
    }

    return {
        id: identifier.slice(0, 100),
        candidate_id: candidateId.slice(0, 100),
    };
};

const supportsEventSource = (): boolean => typeof window !== 'undefined' && typeof window.EventSource !== 'undefined';

const isStreamPreparation = (payload: AiChatSendResponse | AiChatStreamPreparation): payload is AiChatStreamPreparation => {
    return (payload as AiChatStreamPreparation).stream_token !== undefined;
};

const shouldFallbackToSync = (error: ApiError): boolean => {
    return error.status !== undefined && STREAM_FALLBACK_STATUSES.has(error.status);
};

const buildOptimisticMessage = (threadId: number, message: string): AiChatMessage => {
    const timestamp = new Date().toISOString();

    return {
        id: -Date.now(),
        thread_id: threadId,
        user_id: null,
        role: 'user',
        content_text: message,
        content: null,
        citations: [],
        tool_calls: [],
        tool_results: [],
        latency_ms: null,
        status: 'pending',
        created_at: timestamp,
        updated_at: timestamp,
    } satisfies AiChatMessage;
};

const appendMessagesToThread = (
    queryClient: ReturnType<typeof useQueryClient>,
    queryKey: ReturnType<(typeof queryKeys)['ai']['chat']['thread']>,
    messages: AiChatMessage[],
): void => {
    if (messages.length === 0) {
        return;
    }

    queryClient.setQueryData<AiChatThreadResponse | undefined>(queryKey, (current) => {
        if (!current?.thread) {
            return current;
        }

        const nextMessages = [...(current.thread.messages ?? []), ...messages];
        const lastTimestamp = messages[messages.length - 1]?.created_at ?? current.thread.last_message_at;

        return {
            ...current,
            thread: {
                ...current.thread,
                messages: nextMessages,
                last_message_at: lastTimestamp ?? current.thread.last_message_at,
            },
        } satisfies AiChatThreadResponse;
    });
};

const updateMessageInThread = (
    queryClient: ReturnType<typeof useQueryClient>,
    queryKey: ReturnType<(typeof queryKeys)['ai']['chat']['thread']>,
    targetId: number,
    updater: (message: AiChatMessage) => AiChatMessage,
): void => {
    queryClient.setQueryData<AiChatThreadResponse | undefined>(queryKey, (current) => {
        if (!current?.thread) {
            return current;
        }

        const updated = (current.thread.messages ?? []).map((message) => (message.id === targetId ? updater(message) : message));

        return {
            ...current,
            thread: {
                ...current.thread,
                messages: updated,
            },
        } satisfies AiChatThreadResponse;
    });
};

const replaceMessageInThread = (
    queryClient: ReturnType<typeof useQueryClient>,
    queryKey: ReturnType<(typeof queryKeys)['ai']['chat']['thread']>,
    targetId: number,
    nextMessage: AiChatMessage,
): void => {
    queryClient.setQueryData<AiChatThreadResponse | undefined>(queryKey, (current) => {
        if (!current?.thread) {
            return current;
        }

        const updated = (current.thread.messages ?? []).map((message) => (message.id === targetId ? nextMessage : message));

        return {
            ...current,
            thread: {
                ...current.thread,
                messages: updated,
            },
        } satisfies AiChatThreadResponse;
    });
};

const removeMessageFromThread = (
    queryClient: ReturnType<typeof useQueryClient>,
    queryKey: ReturnType<(typeof queryKeys)['ai']['chat']['thread']>,
    targetId: number,
): void => {
    queryClient.setQueryData<AiChatThreadResponse | undefined>(queryKey, (current) => {
        if (!current?.thread) {
            return current;
        }

        const filtered = (current.thread.messages ?? []).filter((message) => message.id !== targetId);

        return {
            ...current,
            thread: {
                ...current.thread,
                messages: filtered,
            },
        } satisfies AiChatThreadResponse;
    });
};

const hasToolRequests = (response: AiChatAssistantResponse): boolean => {
    return response.type === 'tool_request' && Array.isArray(response.tool_calls) && response.tool_calls.length > 0;
};

export function useAiChatSend(threadId: number | null): UseAiChatSendResult {
    const queryClient = useQueryClient();
    const [isStreaming, setIsStreaming] = useState(false);
    const activeStreamRef = useRef<ActiveStreamSession | null>(null);

    const stopStreamSession = (session?: ActiveStreamSession | null) => {
        const target = session ?? activeStreamRef.current;

        if (target) {
            target.source.close();
            if (!session || target === activeStreamRef.current) {
                activeStreamRef.current = null;
            }
        }

        setIsStreaming(false);
    };

    useEffect(() => {
        return () => {
            stopStreamSession();
        };
    }, []);

    useEffect(() => {
        stopStreamSession();
    }, [threadId]);

    const startStreamSession = (
        preparation: AiChatStreamPreparation,
        context: MutationContext,
        placeholder: AiChatMessage,
        contextPayload?: AiChatMessageContextPayload,
    ) => {
        if (!supportsEventSource()) {
            return;
        }

        const streamUrl = `/api/v1/ai/chat/threads/${context.threadId}/stream?token=${encodeURIComponent(preparation.stream_token)}`;

        stopStreamSession();

        let source: EventSource;

        try {
            source = new EventSource(streamUrl, { withCredentials: true });
        } catch (error) {
            updateMessageInThread(queryClient, context.queryKey, placeholder.id, (message) => ({
                ...message,
                status: 'failed',
                content_text: 'Streaming is not supported in this browser. Please retry.',
            }));

            return;
        }

        const session: ActiveStreamSession = {
            source,
            placeholderId: placeholder.id,
            queryKey: context.queryKey,
            threadId: context.threadId,
            accumulatedText: '',
            pendingResponse: null,
            contextPayload,
        };

        const handleStreamingFailure = (message: string) => {
            stopStreamSession(session);

            updateMessageInThread(queryClient, session.queryKey, session.placeholderId, (current) => ({
                ...current,
                status: 'failed',
                content_text: message,
            }));

            queryClient.invalidateQueries({ queryKey: session.queryKey });
        };

        const handleDelta = (event: MessageEvent) => {
            const payload = parseEventData(event);
            const text = typeof payload?.text === 'string' ? payload.text : '';

            if (!text) {
                return;
            }

            session.accumulatedText += text;

            updateMessageInThread(queryClient, session.queryKey, session.placeholderId, (current) => ({
                ...current,
                content_text: session.accumulatedText,
                status: 'streaming',
            }));
        };

        const handleComplete = (event: MessageEvent) => {
            const payload = parseEventData(event);

            if (payload?.response) {
                session.pendingResponse = payload.response as AiChatAssistantResponse;
            }
        };

        const finalizeStream = (assistantMessage: AiChatMessage, assistantResponse?: AiChatAssistantResponse) => {
            stopStreamSession(session);

            replaceMessageInThread(queryClient, session.queryKey, session.placeholderId, assistantMessage);

            void (async () => {
                if (assistantResponse) {
                    try {
                        const { toolRuns } = await executeToolLoop(
                            session.threadId,
                            session.contextPayload,
                            assistantResponse,
                        );
                        appendToolRuns(queryClient, session.queryKey, toolRuns);
                    } catch (error) {
                        console.error('Failed to resolve workspace tools during streaming session', error);
                    }
                }

                queryClient.invalidateQueries({ queryKey: session.queryKey });
                queryClient.invalidateQueries({ queryKey: queryKeys.ai.chat.root(), exact: false });
            })();
        };

        const handleFinal = (event: MessageEvent) => {
            const payload = parseEventData(event);
            const assistantMessage = payload?.assistant_message as AiChatMessage | undefined;
            const assistantResponse = (payload?.response as AiChatAssistantResponse | undefined) ?? session.pendingResponse ?? undefined;

            if (!assistantMessage) {
                handleStreamingFailure('Streaming finished without an assistant message.');

                return;
            }

            finalizeStream(assistantMessage, assistantResponse);
        };

        const handleServerError = (event: MessageEvent) => {
            const payload = parseEventData(event);
            const message = typeof payload?.message === 'string' ? payload.message : 'Streaming failed. Please retry.';
            handleStreamingFailure(message);
        };

        source.addEventListener('delta', handleDelta as EventListener);
        source.addEventListener('complete', handleComplete as EventListener);
        source.addEventListener('final', handleFinal as EventListener);
        source.addEventListener('error', handleServerError as EventListener);
        source.onerror = () => handleStreamingFailure('Streaming connection interrupted.');

        activeStreamRef.current = session;
        setIsStreaming(true);
    };

    const mutation = useMutation<SendMessageResult, ApiError, SendMessageVariables, MutationContext>({
        mutationFn: async (variables) => {
            if (!threadId) {
                throw new Error('Thread is not ready to send messages.');
            }

            const contextPayload = sanitizeContextPayload(variables.context);
            const basePayload: Record<string, unknown> = {
                message: variables.message,
            };

            if (contextPayload) {
                basePayload.context = contextPayload;
            }

            let sendResponse: AiChatSendResponse | AiChatStreamPreparation | null = null;
            let preferStreaming = supportsEventSource();

            if (preferStreaming) {
                try {
                    sendResponse = unwrap(
                        await api.post<AiChatSendResponse | AiChatStreamPreparation>(
                            `/v1/ai/chat/threads/${threadId}/send`,
                            {
                                ...basePayload,
                                stream: true,
                            },
                        ),
                    );
                } catch (error) {
                    if (error instanceof ApiError && shouldFallbackToSync(error)) {
                        preferStreaming = false;
                    } else {
                        throw error;
                    }
                }
            }

            if (!sendResponse) {
                sendResponse = unwrap(
                    await api.post<AiChatSendResponse>(`/v1/ai/chat/threads/${threadId}/send`, {
                        ...basePayload,
                        stream: false,
                    }),
                );
            }

            if (isStreamPreparation(sendResponse)) {
                return {
                    mode: 'stream',
                    preparation: sendResponse,
                    contextPayload,
                } satisfies SendMessageResult;
            }

            const { toolRuns } = await executeToolLoop(threadId, contextPayload, sendResponse.response);

            return {
                mode: 'complete',
                initial: sendResponse,
                toolRuns,
                contextPayload,
            } satisfies SendMessageResult;
        },
        onMutate: async (variables) => {
            if (!threadId) {
                throw new Error('Thread is not ready to send messages.');
            }

            const queryKey = queryKeys.ai.chat.thread(threadId);

            await queryClient.cancelQueries({ queryKey });
            const previous = queryClient.getQueryData<AiChatThreadResponse>(queryKey);
            const optimistic = buildOptimisticMessage(threadId, variables.message);

            appendMessagesToThread(queryClient, queryKey, [optimistic]);

            return {
                queryKey,
                previous,
                optimisticId: optimistic.id,
                threadId,
            } satisfies MutationContext;
        },
        onError: (error, _variables, context) => {
            if (!context) {
                return;
            }

            if (context.previous) {
                queryClient.setQueryData(context.queryKey, context.previous);
                return;
            }

            removeMessageFromThread(queryClient, context.queryKey, context.optimisticId);
        },
        onSuccess: (result, _variables, context) => {
            if (!context) {
                return;
            }

            if (result.mode === 'stream') {
                replaceMessageInThread(queryClient, context.queryKey, context.optimisticId, result.preparation.user_message);
                const placeholder = buildStreamingAssistantMessage(context.threadId);
                appendMessagesToThread(queryClient, context.queryKey, [placeholder]);
                startStreamSession(result.preparation, context, placeholder, result.contextPayload);

                return;
            }

            replaceMessageInThread(queryClient, context.queryKey, context.optimisticId, result.initial.user_message);
            appendMessagesToThread(queryClient, context.queryKey, [result.initial.assistant_message]);

            appendToolRuns(queryClient, context.queryKey, result.toolRuns);

            queryClient.invalidateQueries({ queryKey: queryKeys.ai.chat.root(), exact: false });
        },
        onSettled: (_result, _error, _variables, context) => {
            if (!context) {
                return;
            }

            queryClient.invalidateQueries({ queryKey: context.queryKey });
        },
    });

    return Object.assign(mutation, { isStreaming }) as UseAiChatSendResult;
}

function buildStreamingAssistantMessage(threadId: number): AiChatMessage {
    const timestamp = new Date().toISOString();

    return {
        id: -Date.now() - 1,
        thread_id: threadId,
        user_id: null,
        role: 'assistant',
        content_text: '',
        content: null,
        citations: [],
        tool_calls: [],
        tool_results: [],
        latency_ms: null,
        status: 'streaming',
        created_at: timestamp,
        updated_at: timestamp,
    } satisfies AiChatMessage;
}

const appendToolRuns = (
    queryClient: ReturnType<typeof useQueryClient>,
    queryKey: ReturnType<(typeof queryKeys)['ai']['chat']['thread']>,
    toolRuns: AiChatResolveToolsResponse[],
): void => {
    toolRuns.forEach((toolRun) => {
        appendMessagesToThread(queryClient, queryKey, [toolRun.tool_message, toolRun.assistant_message]);
    });
};

const parseEventData = (event: MessageEvent): Record<string, unknown> | null => {
    if (!event.data) {
        return null;
    }

    try {
        return JSON.parse(event.data) as Record<string, unknown>;
    } catch {
        return null;
    }
};

async function executeToolLoop(
    threadId: number,
    contextPayload: AiChatMessageContextPayload | undefined,
    initialResponse: AiChatAssistantResponse,
): Promise<{ toolRuns: AiChatResolveToolsResponse[] }> {
    const toolRuns: AiChatResolveToolsResponse[] = [];
    let cursor: AiChatAssistantResponse = initialResponse;
    let rounds = 0;

    while (hasToolRequests(cursor) && rounds < MAX_TOOL_ROUNDS) {
        const toolCalls = (cursor.tool_calls ?? []) as AiChatWorkspaceToolCall[];
        const payload: Record<string, unknown> = {
            tool_calls: toolCalls,
        };

        if (contextPayload) {
            payload.context = contextPayload;
        }

        let toolResponse: AiChatResolveToolsResponse | { data: AiChatResolveToolsResponse };

        try {
            toolResponse = await api.post<AiChatResolveToolsResponse>(
                `/v1/ai/chat/threads/${threadId}/tools/resolve`,
                payload,
            );
        } catch (error) {
            emitCopilotToolError({ threadId, reason: error instanceof Error ? error.message : 'unknown' });
            throw error;
        }
        const resolved = unwrap(toolResponse);
        toolRuns.push(resolved);
        cursor = resolved.response;
        rounds += 1;
    }

    return { toolRuns };
}
