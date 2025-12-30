import type { AiAnalyticsCardPayload } from './ai-analytics';

export type AiChatThreadStatus = 'open' | 'closed';
export type AiChatMessageRole = 'user' | 'assistant' | 'system' | 'tool';

export type AiActionDraftStatus = 'drafted' | 'approved' | 'rejected' | 'expired';

export type AiChatResponseType =
    | 'answer'
    | 'draft_action'
    | 'unsafe_action_confirmation'
    | 'workflow_suggestion'
    | 'guided_resolution'
    | 'clarification'
    | 'entity_picker'
    | 'tool_request'
    | 'review_rfq'
    | 'review_quote'
    | 'review_po'
    | 'review_invoice'
    | 'analytics'
    | 'forecast_spend'
    | 'forecast_supplier_performance'
    | 'forecast_inventory'
    | 'error';

export interface AiChatWorkspaceToolCall {
    tool_name: string;
    call_id: string;
    arguments?: Record<string, unknown>;
}

export interface AiChatWorkspaceToolResult {
    tool_name: string;
    call_id: string;
    result: Record<string, unknown> | null;
}

export interface AiChatWorkflowSuggestion {
    workflow_type: string;
    steps: Array<{ title: string; summary: string }>;
    payload?: Record<string, unknown>;
}

export interface AiChatGuidedResolution {
    title: string;
    description: string;
    cta_label: string;
    cta_url: string | null;
    locale?: string;
    available_locales?: string[];
}

export interface AiChatClarificationPrompt {
    id: string;
    tool: string;
    question: string;
    missing_args: string[];
    args?: Record<string, unknown>;
}

export interface AiChatEntityPickerCandidate {
    candidate_id: string;
    label: string;
    description?: string | null;
    status?: string | null;
    meta?: string[];
}

export interface AiChatEntityPickerPrompt {
    id: string;
    title?: string | null;
    description?: string | null;
    query: string;
    entity_type: string;
    search_tool: string;
    target_tool: string;
    candidates: AiChatEntityPickerCandidate[];
}

export interface AiChatDraftSnapshot {
    draft_id?: number;
    action_type: string;
    status?: AiActionDraftStatus;
    summary?: string | null;
    payload?: Record<string, unknown>;
    entity_type?: string | null;
    entity_id?: number | null;
}

export interface AiChatUnsafeActionPrompt {
    id: string;
    action_type: string;
    action_label: string;
    headline: string;
    summary?: string | null;
    description?: string | null;
    impact?: string | null;
    entity?: string | null;
    acknowledgement?: string | null;
    confirm_label?: string | null;
    risks?: string[];
}

export interface AiChatCitation {
    doc_id: string;
    title?: string | null;
    doc_version?: string | null;
    chunk_id?: number | null;
    snippet: string;
    source_type?: string | null;
}

export interface AiChatAssistantResponse {
    type: AiChatResponseType;
    assistant_message_markdown: string;
    citations?: AiChatCitation[];
    suggested_quick_replies?: string[];
    draft?: AiChatDraftSnapshot | null;
    unsafe_action?: AiChatUnsafeActionPrompt | null;
    workflow?: AiChatWorkflowSuggestion | null;
    guided_resolution?: AiChatGuidedResolution | null;
    review?: AiChatReviewPayload | null;
    tool_calls?: AiChatWorkspaceToolCall[] | null;
    tool_results?: AiChatWorkspaceToolResult[] | null;
    needs_human_review?: boolean;
    confidence?: number;
    warnings?: string[];
    analytics_cards?: AiAnalyticsCardPayload[] | null;
    clarification?: AiChatClarificationPrompt | null;
    entity_picker?: AiChatEntityPickerPrompt | null;
}

export interface AiChatReviewChecklistItem {
    label: string;
    value?: string | number | null;
    status: 'ok' | 'warning' | 'risk';
    detail: string;
}

export interface AiChatReviewPayload {
    entity_type: string;
    entity_id: string | number;
    title?: string;
    summary?: string;
    checklist: AiChatReviewChecklistItem[];
    highlights?: string[];
    metadata?: Record<string, unknown>;
}

export interface AiChatMessageContextPayload {
    context?: Record<string, unknown>;
    ui_mode?: string;
    attachments?: Array<Record<string, unknown>>;
    locale?: string;
    clarification?: { id: string };
    entity_picker?: { id: string; candidate_id: string };
}

export interface AiChatMessage {
    id: number;
    thread_id: number;
    user_id: number | null;
    role: AiChatMessageRole;
    content_text: string | null;
    content: AiChatAssistantResponse | Record<string, unknown> | null;
    citations: AiChatCitation[];
    tool_calls: AiChatWorkspaceToolCall[];
    tool_results: AiChatWorkspaceToolResult[];
    latency_ms: number | null;
    status: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface AiChatThread {
    id: number;
    title: string | null;
    status: AiChatThreadStatus;
    user_id: number;
    last_message_at: string | null;
    metadata: Record<string, unknown>;
    thread_summary: string | null;
    created_at: string | null;
    updated_at: string | null;
    messages?: AiChatMessage[];
}

export interface AiChatThreadListResponse {
    items: AiChatThread[];
    meta?: Record<string, unknown>;
}

export interface AiChatThreadResponse {
    thread: AiChatThread;
}

export interface AiChatSendResponse {
    user_message: AiChatMessage;
    assistant_message: AiChatMessage;
    response: AiChatAssistantResponse;
}

export interface AiChatStreamPreparation {
    user_message: AiChatMessage;
    stream_token: string;
    expires_in: number;
}

export interface AiChatResolveToolsResponse {
    tool_message: AiChatMessage;
    assistant_message: AiChatMessage;
    response: AiChatAssistantResponse;
}

export interface AiActionDraft {
    id: number;
    action_type: string;
    status: AiActionDraftStatus;
    summary: string | null;
    payload: Record<string, unknown>;
    citations: AiChatCitation[];
    confidence: number | null;
    needs_human_review: boolean;
    warnings: string[];
    output: Record<string, unknown>;
    entity_type: string | null;
    entity_id: number | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface AiActionDraftResponse {
    draft: AiActionDraft;
}
