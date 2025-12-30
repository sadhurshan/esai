export type AiWorkflowStatus =
    | 'pending'
    | 'in_progress'
    | 'completed'
    | 'failed'
    | 'rejected'
    | 'aborted';

export interface AiWorkflowStepSummary {
    step_index: number | null;
    action_type: string | null;
    approval_status: string | null;
    name: string | null;
    has_pending_approval_request?: boolean;
}

export interface AiWorkflowApprovalRequestParty {
    id: number | null;
    name: string | null;
}

export interface AiWorkflowApprovalRequest {
    id: number;
    workflow_id: string;
    workflow_step_id: number | null;
    step_index: number | null;
    step_type: string | null;
    entity_type: string | null;
    entity_id: string | null;
    approver_role: string | null;
    approver_user: AiWorkflowApprovalRequestParty | null;
    requested_by: AiWorkflowApprovalRequestParty | null;
    status: string;
    message: string | null;
    created_at?: string | null;
    resolved_at?: string | null;
}

export interface AiWorkflowSummary {
    workflow_id: string;
    workflow_type: string;
    status: AiWorkflowStatus;
    current_step: number | null;
    last_event_type?: string | null;
    last_event_time?: string | null;
    steps: AiWorkflowStepSummary[];
    created_at?: string | null;
    updated_at?: string | null;
}

export interface AiWorkflowStepDetail {
    workflow_id: string;
    step_index: number;
    name: string | null;
    action_type: string;
    inputs: Record<string, unknown>;
    draft: Record<string, unknown>;
    output: Record<string, unknown>;
    approval_status: string;
    approved_by?: number | null;
    approved_at?: string | null;
    updated_at?: string | null;
    approval_request?: AiWorkflowApprovalRequest | null;
}

export interface AiWorkflowListResponse {
    items: AiWorkflowSummary[];
    meta?: {
        data?: Record<string, unknown>;
        envelope?: {
            pagination?: Record<string, unknown>;
            cursor?: {
                next_cursor?: string | null;
                prev_cursor?: string | null;
                has_next?: boolean;
                has_prev?: boolean;
            };
            [key: string]: unknown;
        };
    };
}

export interface AiWorkflowStepResponse {
    workflow: AiWorkflowSummary;
    step: AiWorkflowStepDetail | null;
    next_step?: AiWorkflowStepDetail | null;
}
