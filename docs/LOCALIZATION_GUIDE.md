# Copilot Localization Guide

This guide explains how to add new languages for Copilot’s help and guided-resolution flows.

## 1. Add User Guide Sections
1. Update [`docs/USER_GUIDE.md`](USER_GUIDE.md) with any new workflow instructions written in English.
2. Keep headings concise and use ordered or bulleted steps so the help parser can extract guidance.

## 2. Provide Translations
1. Open `ai_microservice/tools_contract.py` and locate the `HELP_TRANSLATIONS` map.
2. Add (or extend) the locale key (e.g. `"es"`, `"de"`).
3. Inside the locale map, create entries keyed by the `source::anchor` slug for each section (for example `user_guide::draft-an-rfq-with-copilot`).
4. Supply translated `title`, `summary`, `steps`, and optional `cta_label`/`cta_url` overrides.

## 3. Expose the Locale in the UI
1. Ensure the locale code you added is returned by `_normalize_locale` in `tools_contract.py` so the help tool returns it inside `available_locales`.
2. Add the locale to `HELP_LANGUAGE_OPTIONS` and `GUIDE_LANGUAGE_LABELS` inside `resources/js/components/ai/CopilotChatPanel.tsx` so both the global selector and guided-resolution cards can render it.
3. Confirm any locale-specific formatting lives in the shared `formatGuideLocale` helper so badges and selectors stay in sync.

## 4. Update Contracts & Types (when needed)
1. If the locale introduces new schema fields, reflect them in `docs/openapi/fragments/copilot.yaml` by updating `AiChatMessageContext.locale` or `AiChatGuidedResolution` as needed.
2. Keep `resources/js/types/ai-chat.ts` aligned with backend responses (e.g., `locale`/`available_locales` on `AiChatGuidedResolution`).
3. Log the change in `docs/COPILOT_PRIMER.md` or related design docs if SDK consumers need to re-generate clients.

## 5. Test the Locale
1. Run the microservice unit tests: `cd ai_microservice && poetry run pytest tests/test_tools_contract.py -k help`.
2. From the Laravel app, issue a chat message that triggers the help tool and set `context.locale` to your language.
3. Verify the guided resolution bubble displays the translated content and language badge.

## 6. Document the Update
1. Mention the new locale in release notes or internal change logs.
2. If the locale is customer-facing, coordinate with support so they can validate terminology with the customer team.
