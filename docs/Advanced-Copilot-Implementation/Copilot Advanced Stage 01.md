1. Create an audit checklist file

Prompt:

- Create a new markdown file at docs/AI-ML-Implementation/Stage-01-Audit.md. Add sections:
- What exists today (UI, API, microservice, workflows, actions)
- What’s missing vs requirements (1–8)
- Risks / unknowns
- Next steps for Stage 02
Leave TODO bullets under each section.

2. Map the current Copilot UI entry points

Prompt:

Scan resources/js/components/ai/ and list all Copilot-related components (chat dock/panel/actions/workflow). Update docs/AI-ML-Implementation/Stage-01-Audit.md with: file paths + 1–2 line purpose for each.

3. Document how chat messages are rendered and filtered

Prompt:

In resources/js/components/ai/CopilotChatPanel.tsx, explain (in comments + in the audit doc) how messages are loaded, what message roles are hidden, and how assistant response types are handled (draft_action/workflow_suggestion/tool_request/etc). Add a small diagram in the audit doc.

4. Identify the front-end API hooks used by Copilot

Prompt:

Search resources/js/hooks/api/ai/ for Copilot chat + actions + workflows hooks. In the audit doc, add a table: Hook name → endpoint it calls → what it returns → where it’s used.

5. Inspect server-side Copilot controllers + routes

Prompt:

Find all Laravel controllers related to AI/Copilot (app/Http/Controllers/**/Ai* and CopilotController). Add to the audit doc: controller methods, routes, and what they do. If any routes are defined in routes/api.php or route files, list them too.

6. Confirm action types and workflow templates already supported

Prompt:

Open config/ai_workflows.php and summarize existing templates and steps. Then search for action_type usage across backend + frontend + microservice. Update the audit doc with a single canonical list of supported action_types and where each is implemented (microservice schema/tool, Laravel converter, UI renderer).

7. Verify draft approval pipeline end-to-end

Prompt:

Trace the “draft_action” flow end-to-end:
UI triggers → API call → draft stored → approve endpoint → converter writes entity.
Use code references from: resources/js/components/ai/CopilotChatPanel.tsx, resources/js/services/ai.ts, app/Models/AiActionDraft.php, converters in app/Services/Ai/Converters/*.
Document the full flow in the audit doc with exact file paths and key functions.

8. Verify workflow pipeline end-to-end

Prompt:

Trace the workflow flow end-to-end:
UI starts workflow → backend service/controller → microservice workflow endpoints (if present) → step approvals → converters.
Use: app/Services/Ai/WorkflowService.php, app/Http/Controllers/Api/V1/AiWorkflowController.php, resources/js/components/ai/WorkflowPanel.tsx (if present).
Update the audit doc with the complete flow + step states.

9. Confirm “workspace grounding” / tool calling exists and what tools are available

Prompt:

Locate the tool-calling implementation that resolves workspace.* tools (search for tool_request, AiChatToolCall, ToolResolver, or similar). List each supported tool name and what data it can access. Update the audit doc with: tool name → inputs → output shape → where it’s used.

10. Run a “requirements gap mapping” directly against your 8 requirements

Prompt:

In docs/AI-ML-Implementation/Stage-01-Audit.md, add a section “Gap vs Requirements (1–8)”.
For each requirement:
- Status: ✅ done / 🟡 partial / ❌ missing
- Evidence: file paths + functions
- Missing pieces (bullet list)

11. Add a Stage 02 “backlog” list (tiny tasks only)

Prompt:

Based on the audit doc, add a Stage 02 backlog with ONLY small tasks (each task ≤ half day). Format as a checklist with file paths and acceptance criteria.