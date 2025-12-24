export type AiChatThreadStatus = 'open' | 'closed';
export type AiChatMessageRole = 'user' | 'assistant' | 'system' | 'tool';

export type AiActionDraftStatus = 'drafted' | 'approved' | 'rejected' | 'expired';

export type AiChatResponseType =
    | 'answer'
    | 'draft_action'
    | 'workflow_suggestion'
    | 'guided_resolution'
    | 'tool_request'
    | 'review_rfq'
    | 'review_quote'
    | 'review_po'
    | 'review_invoice'
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
    cta_url: string;
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
    workflow?: AiChatWorkflowSuggestion | null;
    guided_resolution?: AiChatGuidedResolution | null;
    review?: AiChatReviewPayload | null;
    tool_calls?: AiChatWorkspaceToolCall[] | null;
    tool_results?: AiChatWorkspaceToolResult[] | null;
    needs_human_review?: boolean;
    confidence?: number;
    warnings?: string[];
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
