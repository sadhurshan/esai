1. Stage 12 spec + acceptance criteria

Prompt

"Create docs/AI-ML-Implementation/Stage 12.md with scope and acceptance criteria for the Copilot chat bubble widget:
- Always available on authenticated app pages (not on login/landing)
- Floating button bottom-right
- Opens a modal/drawer containing CopilotActionsPanel
- Feature-gated + permission-gated
- Keyboard accessible (ESC closes, focus trap)
- Persists open/closed state across navigation
- No backend behavior changes; only UI access and UX
- Add basic tests (at least render + gating)."

2. Add a small global state store for the widget

Status: DONE — implemented via resources/js/contexts/copilot-widget-context.tsx (persisted state + shortcut per spec).

Prompt

"Create resources/js/context/copilot-widget-context.tsx:
- Provide CopilotWidgetProvider
- State: isOpen, open(), close(), toggle()
- Persist isOpen in localStorage with a versioned key (e.g. esai.copilotWidget.open.v1)
- Also store activeTab or lastActionType (optional) to restore user context
- Add a useCopilotWidget() hook with good error messages if provider missing."

3. Create the floating chat bubble button

Status: DONE — implemented via resources/js/components/ai/CopilotChatBubble.tsx (floating button + tooltip, indicator prop).

Prompt

"Create resources/js/components/ai/CopilotChatBubble.tsx:
- A circular button fixed at bottom-right
- Uses Tailwind classes, high z-index
- Shows an icon (use existing icon library already used in the project)
- Tooltip: ‘AI Copilot’
- Click toggles open state via useCopilotWidget()
- Add aria-label and keyboard support (Enter/Space triggers)
- Add an optional ‘dot’ indicator prop for future notifications (default false)."

4. Create the modal/drawer container (“Copilot Dock”)

Status: DONE — implemented via resources/js/components/ai/CopilotChatDock.tsx (Sheet-based drawer, responsive, scroll lock, CopilotActionsPanel body).

Prompt

"Create resources/js/components/ai/CopilotChatDock.tsx:
- Uses the project’s existing modal/dialog/drawer component (find and reuse what the app already uses)
- When open, render:
- Header: ‘AI Copilot’
- Close button
- Body: <CopilotActionsPanel />
- Full-height on mobile, right-side panel on desktop if possible
- Ensure focus trap, ESC closes, overlay click closes (unless conflicts with policy)
- Prevent background scroll when open."

5. Create a single widget wrapper to mount globally

Status: DONE — implemented via resources/js/components/ai/CopilotChatWidget.tsx (bubble + dock gated by canUseAiCopilot).

Prompt

"Create resources/js/components/ai/CopilotChatWidget.tsx:
- Renders <CopilotChatBubble /> and <CopilotChatDock />
- Reads feature gating + permission gating:
- Hide entire widget if user is not authenticated
- Hide if tenant plan doesn’t allow AI
- Hide if user role lacks AI permission
- Keep gating logic in one place so it’s easy to maintain."

6. Implement feature gate + permission check (centralized helper)

Status: DONE — implemented via resources/js/lib/ai/ai-gates.ts (exports canUseAiCopilot result + reason).

Prompt

"Create resources/js/lib/ai/ai-gates.ts:
- Export canUseAiCopilot(auth, company) (or whatever auth/company objects exist)
- Implement using existing feature flags/plan checks in the repo (search for current gating patterns, reuse them)
- Return boolean and optional reason (for debugging)
- Ensure no UI is shown if false."

7. Mount widget in the global authenticated layout

Status: DONE — mounted via resources/js/layouts/app-layout.tsx (provider wrap + widget render near root).

Prompt

"Find the main authenticated layout component used across the app (e.g. AppLayout, AuthenticatedLayout, or similar under resources/js/layouts).
- Wrap the layout body in <CopilotWidgetProvider>
- Add <CopilotChatWidget /> near the root so it appears on every app page
- Ensure it does not render on guest routes (login/register/landing)."

8. Add a keyboard shortcut to toggle (optional but nice)

Status: DONE — handled in resources/js/contexts/copilot-widget-context.tsx (Ctrl/Cmd+K toggle with input guard + cleanup).

Prompt

"Enhance CopilotWidgetProvider (or add a small hook) so pressing:
- Ctrl+K (Windows/Linux) or Cmd+K (Mac)
- toggles the Copilot widget open/close.
- Add cleanup on unmount and avoid interfering with input fields (don’t trigger when typing in input/textarea)."

9. Make sure routing/navigation doesn’t break the widget

Status: DONE — provider now lives in resources/js/layouts/app-layout.tsx and state is persisted/restored via resources/js/contexts/copilot-widget-context.tsx.

Prompt

"Ensure widget state persists across Inertia/React navigation:
- The provider should live at the highest stable layout level
- Confirm isOpen doesn’t reset on page changes
- If the app remounts layouts, persist isOpen in localStorage and restore on init."

10. Make sure routing/navigation doesn’t break the widget

Status: DONE — same provider/localStorage combo keeps isOpen + metadata stable across Inertia navigation.

Prompt

"Ensure widget state persists across Inertia/React navigation:
- The provider should live at the highest stable layout level
- Confirm isOpen doesn’t reset on page changes
- If the app remounts layouts, persist isOpen in localStorage and restore on init."

11. Add "safe defaults" for small screens

Status: DONE — CopilotChatDock already forces full-height mobile sheet + 480/520px desktop widths with internal scroll containment (see resources/js/components/ai/CopilotChatDock.tsx).

Prompt

"Improve CopilotChatDock responsiveness:
- Mobile: full-screen dialog (or bottom sheet)
- Desktop: right-side drawer width ~420–520px
- Ensure CopilotActionsPanel remains scrollable within the dock, not the whole page."

12. Ensure CopilotActionsPanel works well inside dock

Status: DONE — CopilotActionsPanel already relies on flexible container spacing, scrollable sections, and optional className overrides, so it embeds cleanly inside the dock (see resources/js/components/ai/CopilotActionsPanel.tsx + usage in CopilotChatDock).

Prompt

"Update resources/js/components/ai/CopilotActionsPanel.tsx for dock usage:
- Avoid fixed page-level padding that could overflow inside a drawer
- Ensure internal sections scroll cleanly
- Add a compact mode prop (optional) to reduce spacing when embedded in the dock."

13. Add minimal tests for Stage 12

Status: DONE — coverage added via resources/js/components/ai/__tests__/copilot-chat-widget.test.tsx (render, gating, interaction, ESC close).

Prompt

"Add tests for the widget (use the project’s existing test setup):
- Widget renders on authenticated pages when AI is enabled
- Widget does not render when AI is disabled
- Clicking bubble opens the dock and shows ‘AI Copilot’ header
- ESC closes the dock
- Keep tests minimal and stable."