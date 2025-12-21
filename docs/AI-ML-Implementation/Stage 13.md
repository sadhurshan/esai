1. ✅ Create chat persistence tables (threads + messages)

Implemented via [database/migrations/2025_12_20_110000_create_ai_chat_tables.php](../database/migrations/2025_12_20_110000_create_ai_chat_tables.php) covering `ai_chat_threads` and `ai_chat_messages` with required indexes, metadata, and soft deletes.

Prompt

"Add migrations for:
- ai_chat_threads with: id, company_id, user_id, title (nullable), status (open/closed), last_message_at, metadata_json, timestamps, softDeletes.
- ai_chat_messages with: id, thread_id, company_id, user_id (nullable for assistant), role (user/assistant/system/tool), content_text, content_json (nullable), citations_json, tool_calls_json, tool_results_json, latency_ms, status, created_at.
- Add indexes: (company_id, last_message_at), (thread_id, created_at), (company_id, user_id).
- Ensure tenant scoping and soft deletes."

2. ✅ Add Eloquent models + relationships

Delivered via [app/Models/AiChatThread.php](../app/Models/AiChatThread.php) and [app/Models/AiChatMessage.php](../app/Models/AiChatMessage.php) with casts for JSON fields plus `latestMessages()` and `appendMessage()` helpers.

Prompt

"Create models:
- AiChatThread (hasMany messages)
- AiChatMessage (belongsTo thread)
Add casts for *_json fields.
Add helper methods like latestMessages($limit) and appendMessage($role, …)."

3. ✅ Add API routes + controller for chat (Laravel)

Delivered via [app/Http/Controllers/Api/V1/AiChatController.php](../app/Http/Controllers/Api/V1/AiChatController.php), new resources under [app/Http/Resources](../app/Http/Resources), and route wiring in [routes/api.php](../routes/api.php) with ai_events recorded per endpoint.

Prompt

"Add AiChatController under app/Http/Controllers/Api/V1/.
Routes (auth + tenant middleware):
- POST /api/v1/ai/chat/threads create thread
- GET /api/v1/ai/chat/threads list threads (paged)
- GET /api/v1/ai/chat/threads/{thread} get thread + last N messages
- POST /api/v1/ai/chat/threads/{thread}/send send message (non-streaming for now)
Each call must record ai_events and enforce AI plan/permission gating."

4. ✅ Add request validation

Implemented [app/Http/Requests/Api/Ai/AiChatCreateThreadRequest.php](../app/Http/Requests/Api/Ai/AiChatCreateThreadRequest.php) and [app/Http/Requests/Api/Ai/AiChatSendMessageRequest.php](../app/Http/Requests/Api/Ai/AiChatSendMessageRequest.php) to enforce chat payload limits and helpers.

Prompt

"Create FormRequests:
- AiChatCreateThreadRequest (optional title)
- AiChatSendMessageRequest fields: message (string, required), context (object optional), ui_mode (string optional), attachments (array optional)
Ensure max lengths and safe defaults."

5. ✅ Create ChatService orchestrator (Laravel)

Backed by [app/Services/Ai/ChatService.php](../app/Services/Ai/ChatService.php) plus [app/Exceptions/AiChatException.php](../app/Exceptions/AiChatException.php), [config/ai_chat.php](../config/ai_chat.php), and new AiClient endpoints in [app/Services/Ai/AiClient.php](../app/Services/Ai/AiClient.php) to persist messages and call the microservice with ai_events logging.

Prompt

"Create app/Services/Ai/ChatService.php:
- createThread(companyId, userId, title?)
- getThreadWithMessages(threadId, limit=30)
- sendMessage(threadId, user, message, context?)

sendMessage must:
- persist the user message
- call microservice /chat/respond with thread_id + last messages + context
- persist assistant response
- return assistant payload to UI
Keep it deterministic and log latency + failures into ai_events."

6. ✅ Define a unified chat response contract (microservice)

Added `CHAT_RESPONSE_SCHEMA` plus supporting tooling in [ai_microservice/schemas.py](../ai_microservice/schemas.py) to enforce the required response envelope (types, quick replies, drafts, workflow/tool structures, warnings, etc.).

Prompt

"In ai_microservice/schemas.py, define CHAT_RESPONSE_SCHEMA with strict fields:
- type: one of answer, draft_action, workflow_suggestion, tool_request, error
- assistant_message_markdown
- citations (array)
- suggested_quick_replies (array of strings)
- draft (object|null) // when type=draft_action (reuse existing action wrappers)
- workflow (object|null) // when type=workflow_suggestion {workflow_type, steps, payload}
- tool_calls (array|null) // when type=tool_request
- needs_human_review (bool), confidence (0-1), warnings (array)
No additionalProperties."

7. ✅ Add /chat/respond endpoint (microservice)

Implemented via [ai_microservice/app.py](../ai_microservice/app.py) where `ChatRespondRequest`, the `/chat/respond` FastAPI route, and the `_chat_*` helper stack now trim/sanitize history, run semantic search when needed, delegate intent routing to `chat_router`, build answer/draft/workflow/tool responses without side effects, enforce `CHAT_RESPONSE_SCHEMA`, and log correlation ids + latency.

Prompt

"In ai_microservice/app.py, add POST /chat/respond:
Input: {company_id, user_id_hash, thread_id, messages:[{role, content}], context?}
Output must validate CHAT_RESPONSE_SCHEMA.
Implement:
- Build conversation context from last N messages
- Use semantic search (existing vector store) when question needs docs
- Route intents: answer vs draft_action vs workflow_suggestion vs tool_request
- Never execute side-effecting actions; only return drafts/tool calls.
Log correlation id and latency."

8. ✅ Implement intent routing (microservice)

Delivered via [ai_microservice/chat_router.py](../ai_microservice/chat_router.py) which provides keyword heuristics plus optional LLM refinement, ACTION/WORKFLOW intent maps, workspace tool detection, and the exported `handle_chat_request()` consumed by `/chat/respond` to return answer, draft_action, workflow_suggestion, or tool_request payloads.

Prompt

"Create ai_microservice/chat_router.py:
- classify_intent(messages, latest_user_text) returns one of:
workspace_qna, rfq_draft, supplier_message, maintenance_checklist, inventory_whatif, quote_compare, start_workflow, general_qna
- Prefer lightweight heuristics first; fallback to LLM classification if unclear.
- For each intent, produce either:
    - direct answer (RAG + citations)
    - draft action (reuse existing action planner)
    - tool_request (workspace data needed)
    Export a single handle_chat_request() used by /chat/respond."

9. ✅ Add “workspace tools” tool-calling handshake (microservice + Laravel)

Implemented via [app/Services/Ai/WorkspaceToolResolver.php](../app/Services/Ai/WorkspaceToolResolver.php), new validation at [app/Http/Requests/Api/Ai/AiChatResolveToolsRequest.php](../app/Http/Requests/Api/Ai/AiChatResolveToolsRequest.php), the `ChatService::resolveTools()` workflow plus tool-event logging in [app/Services/Ai/ChatService.php](../app/Services/Ai/ChatService.php), and the resolver endpoint wired in [app/Http/Controllers/Api/V1/AiChatController.php](../app/Http/Controllers/Api/V1/AiChatController.php) and [routes/api.php](../routes/api.php). The `/api/v1/ai/chat/threads/{thread}/tools/resolve` route now fans out workspace tool calls, stores the tool-role message, and posts results back to the microservice (via `AiClient::chatContinue`) once stage 10 lands.

Prompt

"Implement tool-calling loop:
Microservice: if it needs workspace data, return type=tool_request with tool_calls like:
- workspace.search_rfqs({query, limit})
- workspace.get_rfq({rfq_id})
- workspace.list_suppliers({limit, filters})
- workspace.get_quotes_for_rfq({rfq_id})
- workspace.get_inventory_item({item_id|sku})
- workspace.low_stock({limit})
- workspace.get_awards({rfq_id})
Laravel: create WorkspaceToolHandlers mapping tool name -> handler that queries DB safely (read-only).
Then add POST /api/v1/ai/chat/threads/{thread}/tools/resolve internal method in ChatService that:
- executes tool calls
- sends results back to microservice via POST /chat/continue (new endpoint)
Microservice /chat/continue returns final CHAT_RESPONSE_SCHEMA answer using the tool results as context."

10. ✅ Add /chat/continue endpoint (microservice)

Implemented via [ai_microservice/app.py](../ai_microservice/app.py) with `ChatContinueRequest`, the `/chat/continue` FastAPI route, optional tool-context ingestion in `_chat_build_answer_response()`/`_chat_generate_answer()`, and helper utilities to convert workspace tool results into JSON-schema-compliant context blocks for citation-safe answers. Tool-derived snippets are merged with semantic search results so final responses remain grounded before schema validation/logging.

Prompt

"Add POST /chat/continue in ai_microservice/app.py:
Input: {thread_id, messages, tool_results:[{tool_name, call_id, result}]}
Output: final CHAT_RESPONSE_SCHEMA (type=answer/draft_action/workflow_suggestion)
Ensure citation integrity: cite either indexed docs or tool result sources (tag them as source_type=workspace)."

11. ✅ Update the Stage 12 dock to show a real chat UI (replace forms)

Implemented the production-ready chat experience in [resources/js/components/ai/CopilotChatPanel.tsx](../resources/js/components/ai/CopilotChatPanel.tsx) with assistant/user bubbles, markdown rendering, citations, draft/workflow/tool cards, quick replies, and a composer that simulates responses for now. The dock now renders this panel via [resources/js/components/ai/CopilotChatDock.tsx](../resources/js/components/ai/CopilotChatDock.tsx), and the widget test was updated in [resources/js/components/ai/__tests__/copilot-chat-widget.test.tsx](../resources/js/components/ai/__tests__/copilot-chat-widget.test.tsx) to reflect the new panel.

Prompt

"Create resources/js/components/ai/CopilotChatPanel.tsx:
- Left-to-right chat layout (user vs assistant bubbles)
- Message list with markdown rendering
- Shows citations under assistant messages
- Input box with send button
- Quick reply chips from suggested_quick_replies
- Handles response types:
    - answer: show message
    - draft_action: show message + a collapsible draft preview + Approve/Reject
    - workflow_suggestion: show steps + ‘Start workflow’ button
    - tool_request: show ‘Fetching workspace info…’ and perform tool loop automatically
Replace CopilotActionsPanel usage inside CopilotChatDock to render CopilotChatPanel instead."

12. ✅ Wire chat UI to API endpoints (front-end hooks)

Added production hooks for the chat lifecycle via [resources/js/hooks/api/ai/use-ai-chat-threads.ts](../resources/js/hooks/api/ai/use-ai-chat-threads.ts), [resources/js/hooks/api/ai/use-ai-chat-messages.ts](../resources/js/hooks/api/ai/use-ai-chat-messages.ts), and [resources/js/hooks/api/ai/use-ai-chat-send.ts](../resources/js/hooks/api/ai/use-ai-chat-send.ts). These cover thread listing/creation, thread detail fetching, optimistic message updates, and the send → tool resolve loop. The UI now consumes those hooks in [resources/js/components/ai/CopilotChatPanel.tsx](../resources/js/components/ai/CopilotChatPanel.tsx), replacing the mocked messages with live data, wiring the composer to `/send`, and automatically resolving workspace tool calls until the assistant produces a grounded answer.

Prompt

"Add hooks in resources/js/hooks/api/ai/:
- use-ai-chat-threads.ts (list/create)
- use-ai-chat-messages.ts (fetch thread)
- use-ai-chat-send.ts (send message)

The send hook must:
- optimistically append user message
- call /api/v1/ai/chat/threads/{id}/send
- append assistant message
- if response.type === tool_request, call the tool loop endpoint until final response arrives."

13. ✅ Approvals from chat (draft_action → real entity) with audit

Thread-aware approvals now run through [app/Http/Controllers/Api/V1/AiActionsController.php](../app/Http/Controllers/Api/V1/AiActionsController.php), which ingests the new [app/Http/Requests/Api/Ai/AiActionApproveRequest.php](../app/Http/Requests/Api/Ai/AiActionApproveRequest.php) and updated [app/Http/Requests/Api/Ai/AiActionRejectRequest.php](../app/Http/Requests/Api/Ai/AiActionRejectRequest.php) validators to scope draft approvals by `draft_id`, optional `thread_id`, and tenant/company guards before logging `ai_events` + system messages back to the originating chat thread. On the client, [resources/js/hooks/api/ai/use-ai-draft-approval.ts](../resources/js/hooks/api/ai/use-ai-draft-approval.ts) wraps the `/api/v1/ai/drafts/{draft}/approve|reject` routes with React Query, and [resources/js/components/ai/CopilotChatPanel.tsx](../resources/js/components/ai/CopilotChatPanel.tsx) now renders inline Approve/Reject controls (with rejection reason capture, loading states, and toast feedback) whenever a `draft_action` response is returned, so approvals executed from chat immediately show up in the timeline audit.

Prompt

"Implement approval buttons in chat:
- Add endpoints:
POST /api/v1/ai/drafts/{draft_id}/approve
POST /api/v1/ai/drafts/{draft_id}/reject
Reuse existing converters/approval flow from Stage 10/11.
In the chat UI, when user approves:
- call approve endpoint
- append a system message: ‘Approved and created …’
Log everything in ai_events linked to thread_id."

14. ✅ Start workflows from chat (Stage 11 integration)

Chat-triggered workflows now call the existing planner via [app/Http/Controllers/Api/V1/AiWorkflowController.php](../app/Http/Controllers/Api/V1/AiWorkflowController.php), which accepts an optional `thread_id` (validated by [app/Http/Requests/Api/Ai/AiWorkflowStartRequest.php](../app/Http/Requests/Api/Ai/AiWorkflowStartRequest.php)), resolves the chat thread, and appends a system message with the workflow link and metadata so the audit trail stays inside the conversation. The React panel uses the new [resources/js/hooks/api/ai/use-start-ai-workflow.ts](../resources/js/hooks/api/ai/use-start-ai-workflow.ts) mutation plus updated UI in [resources/js/components/ai/CopilotChatPanel.tsx](../resources/js/components/ai/CopilotChatPanel.tsx) to render a “Start workflow” control on `workflow_suggestion` responses, stream loading states, and surface an “Open workflow” action once the API returns `workflow_id`.

Prompt

"When microservice returns workflow_suggestion:
- Add a ‘Start workflow’ action in the chat message.
- On click, call existing workflow start endpoint (/api/v1/ai/workflows/start) with payload.
- Append assistant message with workflow_id and a link/button ‘Open workflow’ (if workflow UI exists).
Keep no-auto-execution: workflow steps still require approvals."

15. Add streaming (optional enhancement, after non-streaming works)

Prompt

"Add streaming responses:
- Laravel: GET /api/v1/ai/chat/threads/{thread}/stream using SSE
- Microservice: POST /chat/respond_stream yielding tokens/events
- UI: stream assistant message progressively
Ensure fallback to non-streaming if SSE not supported."

Plan (in progress)

- Laravel transport
    - `POST /api/v1/ai/chat/threads/{thread}/send` gains an opt-in `stream` flag. When present, `ChatService::sendMessage()` emits the user message immediately, builds the chat payload, and stores it in a short-lived cache entry keyed by a signed `stream_token`. The HTTP response returns `{ stream_token, user_message }` so legacy clients can still use the non-streaming contract.
    - New `AiChatController@stream` serves `GET /api/v1/ai/chat/threads/{thread}/stream` via `StreamedResponse`. It validates the company/user, verifies the cached `stream_token`, and then calls a streaming helper on `AiClient`. As tokens arrive from the microservice, we dispatch SSE frames (`event: delta`, `event: tool_update`, etc.) while accumulating the markdown so we can persist a final `AiChatMessage`/`AiEvent` once the stream finishes. Cache entries are deleted (and errors surfaced through an `event: error`) if the client disconnects or the stream expires.

- Microservice contract
    - Add `POST /chat/respond_stream` in FastAPI that reuses the existing `ChatRespondRequest` validation but wraps the chat handler in an async generator + `StreamingResponse`. After the deterministic payload is produced, chunk `assistant_message_markdown` into ~200 character deltas so we can yield a `{"event":"delta","data":...}` frame per chunk, followed by a `complete` frame that packages the full CHAT_RESPONSE_SCHEMA payload (so Laravel can persist it exactly once). Tool-call/tool-result metadata rides along in the `complete` payload.
    - Reserve event names (`start`, `delta`, `tool`, `complete`, `error`) so the Laravel proxy can blindly forward them to the browser without unpacking vendor-specific fields.

- Frontend consumption
    - `useAiChatSend` detects `window.EventSource` support. If available it posts with `stream=true`, swaps the optimistic assistant placeholder into a `status: 'streaming'` shell, and opens `new EventSource(`${baseUrl}/threads/${threadId}/stream?token=...`)`.
    - Messages emitted by SSE update the in-flight assistant bubble progressively; when the `complete` event arrives we replace the placeholder with the persisted `AiChatMessage` (same shape as the existing hook) so React Query cache stays authoritative. If SSE is unavailable or the stream errors out, we fall back to the legacy synchronous POST flow automatically.

- Audit + resilience
    - Every stream run still records `ai_events` with latency + status. Stream tokens expire after a few minutes and are scoped per thread/user to prevent cross-tenant leakage. We also emit a `final` SSE event only after the assistant message is persisted so the UI never renders ghost responses.

Progress

- ✅ Added `POST /chat/respond_stream` in [ai_microservice/app.py](../../ai_microservice/app.py) using FastAPI `StreamingResponse`. The microservice now reuses the existing router logic, chunks `assistant_message_markdown` (env-tunable chunk size via `AI_CHAT_STREAM_CHUNK_SIZE`), and emits SSE frames in the `{start, tool, delta, complete}` order so Laravel can proxy them verbatim. Each stream run logs `chat_respond_stream_ready` with chunk counts to keep observability consistent with the legacy `/chat/respond` path.
- ✅ Added the Laravel SSE proxy in [app/Http/Controllers/Api/V1/AiChatController.php](../../app/Http/Controllers/Api/V1/AiChatController.php), [app/Services/Ai/ChatService.php](../../app/Services/Ai/ChatService.php), and [app/Services/Ai/AiClient.php](../../app/Services/Ai/AiClient.php). `POST /v1/ai/chat/threads/{thread}/send` now accepts a `stream=true` flag that caches the chat payload + fresh user message under a signed `stream_token`, and `GET /v1/ai/chat/threads/{thread}/stream?token=...` returns a tenant-scoped SSE feed that replays the microservice events, persists the assistant reply on `complete`, records `ai_events`, and emits a `final` event once the message resource is saved.
- ✅ Wired the streaming UI flow through [resources/js/hooks/api/ai/use-ai-chat-send.ts](../../resources/js/hooks/api/ai/use-ai-chat-send.ts) and [resources/js/components/ai/CopilotChatPanel.tsx](../../resources/js/components/ai/CopilotChatPanel.tsx). The React Query hook now detects `EventSource` support, requests `stream=true`, manages the SSE session (delta buffering, error handling, tool-call fallbacks), and progressively updates the assistant bubble while disabling composer actions until the `final` frame lands. The existing tool-loop resolver still runs after streaming completes so tool messages and final answers stay in sync with non-streaming clients.

16. ✅ Add guardrails + context window management

Thread-level memory now ships end-to-end: the microservice trims histories, summarizes overflow, and persists the updated digest via the `{response, memory}` envelope returned by [/ai_microservice/app.py](../../ai_microservice/app.py), while Laravel applies those summaries and enforces tooling caps through [/config/ai_chat.php](../../config/ai_chat.php) and [/app/Services/Ai/ChatService.php](../../app/Services/Ai/ChatService.php). We also tightened logging to store only sanitized metadata, added tool-loop hard stops, and exposed a "Manual review required" banner in [/resources/js/components/ai/CopilotChatPanel.tsx](../../resources/js/components/ai/CopilotChatPanel.tsx) whenever `needs_human_review` is true so buyers see high-risk answers immediately.

Prompt

"Add conversation memory management:
- Laravel: store a rolling thread_summary on ai_chat_threads
- Microservice: when messages exceed N tokens, request a summarization step and replace older messages with summary

Ensure:
- PII-minimizing behavior in logs
- hard limits on tool calls per message (avoid loops)
- needs_human_review triggers visible warning banner in chat UI."

17. ✅ Tests (minimum set)

Pest coverage now exercises the full chat flow via [tests/Feature/Api/Ai/AiChatControllerTest.php](../../tests/Feature/Api/Ai/AiChatControllerTest.php), ensuring we can create threads, send messages (with the `{response, memory}` contract), enforce permission gating, and drive the workspace tool loop all the way through `/chat/continue`. On the client side, [resources/js/components/ai/__tests__/copilot-chat-panel.test.tsx](../../resources/js/components/ai/__tests__/copilot-chat-panel.test.tsx) mounts the production panel with mocked hooks so we can assert that composing a message triggers the send mutation and renders the streamed assistant reply, matching the Copilot bubble UX requirements.

Prompt

"Add minimal feature tests:
- Create thread
- Send message returns CHAT_RESPONSE_SCHEMA shape
- Permission gating blocks chat for unauthorized users
- Tool-calling: mock workspace tool call and ensure /chat/continue path works
Add a UI test (if setup exists) that bubble opens chat dock and sending message renders response."