import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { useEffect, useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { AiChatAssistantResponse, AiChatMessage, AiChatThread } from '@/types/ai-chat';
import { CopilotChatPanel } from '@/components/ai/CopilotChatPanel';
import type { UseAiChatSendResult } from '@/hooks/api/ai/use-ai-chat-send';

type ThreadUpdater = (updater: (thread: AiChatThread | undefined) => AiChatThread | undefined) => void;

let updateThreadState: ThreadUpdater | null = null;
let messageIdCounter = 1;

const nextMessageId = (): number => messageIdCounter++;

const buildAssistantResponse = (markdown: string): AiChatAssistantResponse => ({
    type: 'answer',
    assistant_message_markdown: markdown,
    citations: [],
    suggested_quick_replies: [],
    draft: null,
    workflow: null,
    tool_calls: null,
    tool_results: null,
    needs_human_review: false,
    confidence: 0.5,
    warnings: [],
});

const buildMessage = (threadId: number, role: 'user' | 'assistant', text: string): AiChatMessage => {
    const timestamp = '2025-01-01T00:00:00.000Z';
    const content = role === 'assistant' ? buildAssistantResponse(text) : null;

    return {
        id: nextMessageId(),
        thread_id: threadId,
        user_id: role === 'user' ? 1 : null,
        role,
        content_text: text,
        content,
        citations: [],
        tool_calls: [],
        tool_results: [],
        latency_ms: null,
        status: 'completed',
        created_at: timestamp,
        updated_at: timestamp,
    } satisfies AiChatMessage;
};

const baseThread: AiChatThread = {
    id: 42,
    title: 'Procurement daily brief',
    status: 'open',
    user_id: 1,
    last_message_at: '2025-01-01T00:00:00.000Z',
    metadata: {},
    thread_summary: 'Latest supplier context',
    created_at: '2025-01-01T00:00:00.000Z',
    updated_at: '2025-01-01T00:00:00.000Z',
    messages: [
        buildMessage(42, 'user', 'Summarize the latest RFQs'),
        buildMessage(42, 'assistant', 'Here is the supplier overview.'),
    ],
};

const cloneThread = (): AiChatThread => JSON.parse(JSON.stringify(baseThread)) as AiChatThread;

vi.mock('@/hooks/api/ai/use-ai-chat-threads', () => ({
    useAiChatThreads: () => ({
        data: { items: [cloneThread()] },
        isLoading: false,
        isFetching: false,
    }),
    useCreateAiChatThread: () => ({
        mutate: vi.fn(),
        mutateAsync: vi.fn(),
        reset: vi.fn(),
        isPending: false,
        status: 'idle',
        data: undefined,
        error: null,
        variables: undefined,
        context: undefined,
    }),
}));

vi.mock('@/hooks/api/ai/use-ai-chat-messages', () => {
    return {
        useAiChatMessages: () => {
            const [thread, setThread] = useState<AiChatThread | undefined>(cloneThread());

            useEffect(() => {
                updateThreadState = (updater) =>
                    setThread((current: AiChatThread | undefined) => updater(current));
            }, [setThread]);

            return {
                data: thread,
                isLoading: false,
                isFetching: false,
            };
        },
    };
});

vi.mock('@/hooks/api/ai/use-ai-chat-send', () => ({
    useAiChatSend: () => {
        const mutateAsync = vi.fn(async ({ message }: { message: string }) => {
            if (updateThreadState) {
                updateThreadState((current) => {
                    if (!current) {
                        return current;
                    }

                    const userMessage = buildMessage(current.id, 'user', message);
                    const assistantMessage = buildMessage(current.id, 'assistant', `Assistant reply: ${message}`);

                    return {
                        ...current,
                        messages: [...(current.messages ?? []), userMessage, assistantMessage],
                        last_message_at: assistantMessage.created_at,
                    };
                });
            }

            return {
                mode: 'complete',
                initial: {
                    user_message: buildMessage(baseThread.id, 'user', message),
                    assistant_message: buildMessage(baseThread.id, 'assistant', `Assistant reply: ${message}`),
                    response: buildAssistantResponse(`Assistant reply: ${message}`),
                },
                toolRuns: [],
            };
        });

        return {
            mutateAsync,
            mutate: mutateAsync,
            reset: vi.fn(),
            isPending: false,
            isStreaming: false,
        } as unknown as UseAiChatSendResult;
    },
}));

const buildDraftMutation = () => ({
    mutate: vi.fn(),
    mutateAsync: vi.fn(),
    reset: vi.fn(),
    isPending: false,
    status: 'idle',
    data: undefined,
    error: null,
    variables: undefined,
    context: undefined,
});

vi.mock('@/hooks/api/ai/use-ai-draft-approval', () => ({
    useAiDraftApprove: () => buildDraftMutation(),
    useAiDraftReject: () => buildDraftMutation(),
}));

vi.mock('@/hooks/api/ai/use-start-ai-workflow', () => ({
    useStartAiWorkflow: () => ({
        mutate: vi.fn(),
        mutateAsync: vi.fn(),
        reset: vi.fn(),
        isPending: false,
        status: 'idle',
        data: undefined,
        error: null,
        variables: undefined,
        context: undefined,
    }),
}));

describe('CopilotChatPanel', () => {
    beforeEach(() => {
        updateThreadState = null;
        messageIdCounter = 1;
    });

    it('sends a message and renders the assistant reply', async () => {
        render(<CopilotChatPanel />);

        expect(screen.getByText('Procurement Copilot')).toBeInTheDocument();
        await waitFor(() => expect(screen.getByText('Here is the supplier overview.')).toBeInTheDocument());

        const composer = screen.getByPlaceholderText(/Ask Copilot/i);
        fireEvent.change(composer, { target: { value: 'Need delivery status updates' } });
        fireEvent.click(screen.getByRole('button', { name: /send/i }));

        await waitFor(() =>
            expect(screen.getByText('Assistant reply: Need delivery status updates')).toBeInTheDocument(),
        );
    });
});
