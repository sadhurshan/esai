export const COPILOT_TOOL_ERROR_EVENT = 'copilot:tool_error';
export const COPILOT_DRAFT_REJECT_EVENT = 'copilot:draft_reject';

function dispatchCopilotEvent(
    name: string,
    detail?: Record<string, unknown>,
): void {
    if (
        typeof window === 'undefined' ||
        typeof window.dispatchEvent !== 'function'
    ) {
        return;
    }

    window.dispatchEvent(new CustomEvent(name, { detail }));
}

export function emitCopilotToolError(detail?: Record<string, unknown>): void {
    dispatchCopilotEvent(COPILOT_TOOL_ERROR_EVENT, detail);
}

export function emitCopilotDraftRejected(
    detail?: Record<string, unknown>,
): void {
    dispatchCopilotEvent(COPILOT_DRAFT_REJECT_EVENT, detail);
}
