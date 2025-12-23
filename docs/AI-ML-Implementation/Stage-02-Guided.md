# Stage 02 – Guided Resolution Responses

Copilot now emits a `guided_resolution` assistant response when it cannot take an automated action but can route the user toward the correct workflow or knowledge article. This keeps the chat experience actionable while larger workflows (receiving discrepancies, compliance exceptions, etc.) are still being wired up.

## Response Shape

- `type`: must be `guided_resolution`.
- `assistant_message_markdown`: short narrative that appears in the transcript.
- `guided_resolution`: object with the following fields:
  - `title`: headline for the fallback card rendered in `CopilotChatPanel`.
  - `description`: brief guidance explaining what the user should do next.
  - `cta_label`: button label shown in the UI.
  - `cta_url`: HTTPS link that opens the relevant workspace or documentation.
- Other fields (`citations`, `warnings`, etc.) remain unchanged and optional.

## Example Payload

```json
{
  "type": "guided_resolution",
  "assistant_message_markdown": "I can guide you to the correct intake form while we finish wiring up automated receiving workflows.",
  "guided_resolution": {
    "title": "Create a Receiving Discrepancy Case",
    "description": "Until the automated receiving-quality workflow ships, click through to the QC case intake so the ops team can triage it.",
    "cta_label": "Open QC Intake",
    "cta_url": "https://app.elements-supply.ai/receiving/quality/intake"
  },
  "suggested_quick_replies": [
    "Show me the latest receipts",
    "How do I escalate this?"
  ]
}
```

The frontend renderer in `resources/js/components/ai/CopilotChatPanel.tsx` automatically displays the card with the CTA and leaves the transcript markdown untouched. Backend responses should follow this contract so future workflows can reuse the guided pattern without UI changes.
