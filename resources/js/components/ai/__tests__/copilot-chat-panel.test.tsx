import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { useEffect, useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { AiChatAssistantResponse, AiChatMessage, AiChatThread } from '@/types/ai-chat';
import { CopilotChatPanel } from '@/components/ai/CopilotChatPanel';
import type { UseAiChatSendResult } from '@/hooks/api/ai/use-ai-chat-send';

type ThreadUpdater = (updater: (thread: AiChatThread | undefined) => AiChatThread | undefined) => void;

let updateThreadState: ThreadUpdater | null = null;
let messageIdCounter = 1;
let sendMutationSpy: ReturnType<typeof vi.fn> | null = null;
let lastSendPayload: { message: string; context?: Record<string, unknown> } | null = null;
let approveDraftSpy: ReturnType<typeof vi.fn> | null = null;
let rejectDraftSpy: ReturnType<typeof vi.fn> | null = null;

const nextMessageId = (): number => messageIdCounter++;

const buildAssistantResponse = (markdown: string, overrides: Partial<AiChatAssistantResponse> = {}): AiChatAssistantResponse => ({
    type: 'answer',
    assistant_message_markdown: markdown,
    citations: [],
    suggested_quick_replies: [],
    draft: null,
    unsafe_action: null,
    workflow: null,
    tool_calls: null,
    tool_results: null,
    needs_human_review: false,
    confidence: 0.5,
    warnings: [],
    clarification: null,
    ...overrides,
});

const buildMessage = (
    threadId: number,
    role: 'user' | 'assistant',
    text: string,
    assistantResponse?: AiChatAssistantResponse,
): AiChatMessage => {
    const timestamp = '2025-01-01T00:00:00.000Z';
    const content = role === 'assistant' ? assistantResponse ?? buildAssistantResponse(text) : null;

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
        const mutateAsync = vi.fn(async ({
            message,
            context,
        }: {
            message: string;
            context?: Record<string, unknown>;
        }) => {
            lastSendPayload = { message, context };
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

        sendMutationSpy = mutateAsync;

        return {
            mutateAsync,
            mutate: mutateAsync,
            reset: vi.fn(),
            isPending: false,
            isStreaming: false,
        } as unknown as UseAiChatSendResult;
    },
}));

vi.mock('@/components/ai/AnalyticsCard', () => ({
    AnalyticsCard: () => <div data-testid="analytics-card" />,
}));

const waitForThreadReady = async () => {
    await waitFor(() => expect(screen.getByRole('button', { name: /send/i })).not.toBeDisabled());
};

const buildDraftMutation = (spy: ReturnType<typeof vi.fn>) => ({
    mutate: spy,
    mutateAsync: spy,
    reset: vi.fn(),
    isPending: false,
    status: 'idle',
    data: undefined,
    error: null,
    variables: undefined,
    context: undefined,
});

const buildDraftResponse = (draftId: number) => ({
    draft: {
        id: draftId,
        action_type: 'payment_draft',
        status: 'approved',
        summary: 'Test draft',
        payload: {},
        citations: [],
        confidence: 0.9,
        needs_human_review: false,
        warnings: [],
        entity_type: null,
        entity_id: null,
        output: {},
        created_at: null,
        updated_at: null,
    },
});

vi.mock('@/hooks/api/ai/use-ai-draft-approval', () => {
    approveDraftSpy = vi.fn(async ({ draftId }: { draftId: number }) => buildDraftResponse(draftId));
    rejectDraftSpy = vi.fn(async ({ draftId }: { draftId: number }) => buildDraftResponse(draftId));

    return {
        useAiDraftApprove: () => buildDraftMutation(approveDraftSpy!),
        useAiDraftReject: () => buildDraftMutation(rejectDraftSpy!),
    };
});

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
        sendMutationSpy = null;
        lastSendPayload = null;
        approveDraftSpy?.mockClear();
        rejectDraftSpy?.mockClear();
    });

    it('sends a message and renders the assistant reply', async () => {
        render(<CopilotChatPanel />);

        await waitForThreadReady();

        expect(screen.getByText('Procurement Copilot')).toBeInTheDocument();
        await waitFor(() => expect(screen.getByText('Here is the supplier overview.')).toBeInTheDocument());

        const composer = screen.getByPlaceholderText(/Ask Copilot/i);
        fireEvent.change(composer, { target: { value: 'Need delivery status updates' } });
        fireEvent.click(screen.getByRole('button', { name: /send/i }));

        await waitFor(() =>
            expect(screen.getByText('Assistant reply: Need delivery status updates')).toBeInTheDocument(),
        );
    });

    it('renders a clarification prompt and submits the answer', async () => {
        render(<CopilotChatPanel />);

        await waitForThreadReady();

        await waitFor(() => expect(updateThreadState).not.toBeNull());

        act(() => {
            updateThreadState?.((current) => {
                if (!current) {
                    return current;
                }

                const clarificationResponse = buildAssistantResponse('What should we call this RFQ?', {
                    type: 'clarification',
                    clarification: {
                        id: 'clarify-rfq-title',
                        tool: 'build_rfq_draft',
                        question: 'What should we call this RFQ?',
                        missing_args: ['rfq_title'],
                        args: {},
                    },
                });

                const clarificationMessage = buildMessage(
                    current.id,
                    'assistant',
                    'What should we call this RFQ?',
                    clarificationResponse,
                );

                return {
                    ...current,
                    messages: [...(current.messages ?? []), clarificationMessage],
                };
            });
        });

        await waitFor(() => expect(screen.getByTestId('clarification-form')).toBeInTheDocument());

        const answerField = screen.getByLabelText(/clarification answer/i);
        fireEvent.change(answerField, { target: { value: 'Call it Demo RFQ' } });

        fireEvent.click(screen.getByRole('button', { name: /submit answer/i }));

        await waitFor(() => {
            expect(lastSendPayload).not.toBeNull();
            expect(lastSendPayload).toEqual(
                expect.objectContaining({
                    message: 'Call it Demo RFQ',
                    context: expect.objectContaining({
                        clarification: { id: 'clarify-rfq-title' },
                    }),
                }),
            );
        });
    });

    it('renders an entity picker prompt and forwards the selection metadata', async () => {
        render(<CopilotChatPanel />);

        await waitForThreadReady();

        await waitFor(() => expect(updateThreadState).not.toBeNull());

        act(() => {
            updateThreadState?.((current) => {
                if (!current) {
                    return current;
                }

                const pickerResponse = buildAssistantResponse('Which invoice should I open?', {
                    type: 'entity_picker',
                    entity_picker: {
                        id: 'invoice-picker',
                        title: 'Pick the invoice Copilot should use',
                        description: 'Multiple invoices matched INV-100. Choose the right one.',
                        query: 'INV-100',
                        entity_type: 'invoice',
                        search_tool: 'workspace.search_invoices',
                        target_tool: 'workspace.get_invoice',
                        candidates: [
                            {
                                candidate_id: 'cand-1',
                                label: 'INV-1001',
                                description: 'Atlas Manufacturing · Due in 5 days',
                                status: 'open',
                                meta: ['Due Jan 5', 'Total $12,000'],
                            },
                            {
                                candidate_id: 'cand-2',
                                label: 'INV-1002',
                                description: 'Beacon Plastics · Due in 10 days',
                                status: 'pending',
                                meta: ['Due Jan 10', 'Total $18,500'],
                            },
                        ],
                    },
                });

                const pickerMessage = buildMessage(
                    current.id,
                    'assistant',
                    'Which invoice should I open?',
                    pickerResponse,
                );

                return {
                    ...current,
                    messages: [...(current.messages ?? []), pickerMessage],
                };
            });
        });

        await waitFor(() => expect(screen.getByTestId('entity-picker-form')).toBeInTheDocument());

        const firstChoice = screen.getAllByRole('button', { name: /use this record/i })[0];
        fireEvent.click(firstChoice);

        await waitFor(() => {
            expect(lastSendPayload).not.toBeNull();
            expect(lastSendPayload).toEqual(
                expect.objectContaining({
                    message: expect.stringContaining('INV-1001'),
                    context: expect.objectContaining({
                        entity_picker: { id: 'invoice-picker', candidate_id: 'cand-1' },
                    }),
                }),
            );
        });
    });

    it('requires acknowledgement before confirming unsafe actions', async () => {
        render(<CopilotChatPanel />);

        await waitForThreadReady();
        await waitFor(() => expect(updateThreadState).not.toBeNull());

        act(() => {
            updateThreadState?.((current) => {
                if (!current) {
                    return current;
                }

                const unsafeResponse = buildAssistantResponse('Confirm payment release', {
                    type: 'unsafe_action_confirmation',
                    draft: {
                        draft_id: 5001,
                        action_type: 'payment_draft',
                        status: 'drafted',
                        payload: {
                            invoice_id: 'INV-5001',
                            amount: 12500,
                            currency: 'USD',
                            reference: 'PAY-5001',
                        },
                    },
                    unsafe_action: {
                        id: 'unsafe-payment',
                        action_type: 'payment_draft',
                        action_label: 'Payment Draft',
                        headline: 'Confirm payment release',
                        summary: 'This will release payment PAY-5001 for invoice INV-5001.',
                        description: 'Copilot will record the payment and notify finance.',
                        impact: 'USD 12,500.00 will be recorded immediately.',
                        entity: 'Invoice INV-5001',
                        acknowledgement: 'I understand this will create a payment request.',
                        confirm_label: 'Confirm payment release',
                        risks: ['Creates a payable ready for disbursement.', 'May notify the supplier.'],
                    },
                });

                const unsafeMessage = buildMessage(current.id, 'assistant', 'Confirm payment release', unsafeResponse);

                return {
                    ...current,
                    messages: [...(current.messages ?? []), unsafeMessage],
                };
            });
        });

        await waitFor(() => expect(screen.getByTestId('unsafe-action-confirmation')).toBeInTheDocument());

        const confirmButton = screen.getByRole('button', { name: /confirm payment release/i });
        expect(confirmButton).toBeDisabled();

        fireEvent.click(confirmButton);
        expect(approveDraftSpy).not.toBeNull();
        expect(approveDraftSpy?.mock.calls.length).toBe(0);

        const acknowledgement = screen.getByLabelText(/create a payment request/i);
        fireEvent.click(acknowledgement);

        fireEvent.click(confirmButton);

        await waitFor(() => {
            expect(approveDraftSpy).not.toBeNull();
            expect(approveDraftSpy).toHaveBeenCalledWith({ draftId: 5001 });
        });
    });
});
