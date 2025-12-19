1. Add an LLM provider abstraction (keep it vendor-agnostic)

Prompt

"In ai_microservice/, create llm_provider.py:
- Define interface/class LLMProvider with method generate_answer(query: str, context_blocks: list[dict], response_schema: dict, safety_identifier: str | None) -> dict
- Add a DummyLLMProvider that returns a simple summary using the provided context blocks (no external calls)
- Add provider selection via env AI_LLM_PROVIDER=dummy|openai (default dummy)
Keep code clean and testable."

2. Implement OpenAI LLM provider (server-side, key via env)

Prompt

"Implement OpenAILLMProvider in llm_provider.py using HTTP requests:
- Read API key from environment (server-side only)
- Use Bearer auth header
- Add optional safety_identifier (hashed user id/email) in requests
- Support Structured Outputs using response_format: { type: 'json_schema', json_schema: { strict: true, schema: <schema> } }
- Return parsed JSON dict or raise a typed exception
Do NOT log secrets. Add clear docstrings."

(Notes Copilot should follow: API keys use Bearer auth and must be kept secret server-side. Structured Outputs with json_schema exists and is the preferred reliable schema mode when supported.)

3. Define the answer JSON schema (single source of truth)

Prompt

"Create ai_microservice/schemas.py and define ANSWER_SCHEMA as JSON Schema for the /answer output:
Required:
- answer_markdown (string)
- citations (array of objects: doc_id, doc_version, chunk_id, score, snippet)
- confidence (number 0..1)
- needs_human_review (boolean)
- warnings (array of strings)
Disallow additional properties.
Keep schema minimal but strict."

(Structured Outputs can force the model to follow your schema exactly when strict:true is used.)

4. Add a prompt template that forces grounding + prevents hallucinations

Prompt

"Create ai_microservice/prompts.py with a function build_answer_messages(query, hits) that returns:
- a system message that instructs: 'Use ONLY the provided sources. If not enough info, say so. Include citations using chunk_id/doc_id. Never invent facts.'
- a developer message that specifies the output must be VALID JSON matching the provided schema.
- a user message containing the query + a compact list of sources (doc title, doc_id, doc_version, chunk_id, snippet)
Keep sources concise and structured."

(If you use JSON mode/structured outputs, make sure the instructions explicitly say "JSON" in the prompt context.)

5. Upgrade /answer to call the LLM provider (feature-flagged)

Prompt

"In ai_microservice/app.py, update POST /answer:
- Perform semantic search (top_k)
- Build context blocks from hits
- If AI_LLM_PROVIDER=dummy keep deterministic summarizer
- If AI_LLM_PROVIDER=openai, call OpenAILLMProvider.generate_answer() with ANSWER_SCHEMA
- Always return {status:'ok', answer:<...>, citations:[...], ...} shaped by schema
- If no hits, return 'Not enough information in indexed sources' with empty citations and needs_human_review=true
Add logging (request id, company_id, latency)."

6. Add "citation integrity checks" before returning responses

Prompt

"Add a post-processing validator in the microservice:
- Ensure every citation chunk_id exists in the retrieved hits
- Trim citation snippets to <= 250 chars
- If citations are missing or invalid, set needs_human_review=true and add a warning
Fail safely (never crash the endpoint). Add unit tests."

7. Add refusal + safety handling (don't break schema)

Prompt

"Implement refusal handling in LLM provider calls:
- If the model returns a refusal or cannot comply, return:
answer_markdown = a safe refusal message,
citations = [],
needs_human_review = true,
include warning 'refused'
Ensure the response still matches ANSWER_SCHEMA."

(Structured outputs can include refusal indicators; handle it programmatically.)

8. Add token/context budgeting so answers don't blow up

Prompt

"Create ai_microservice/context_packer.py:
- Deduplicate near-duplicate chunks
- Keep top N chunks but enforce a max total character budget (e.g., 12k chars)
- Prefer diverse docs (don't take 8 chunks from one doc)
- Always include doc_id/doc_version/chunk_id with each chunk
Wire this into /answer and /search."

9. Add microservice tests for schema + grounding

Prompt

"Create ai_microservice/tests/test_answer_llm.py:
- Test that /answer returns JSON matching ANSWER_SCHEMA
- Test that citations refer to returned hits
- Test empty-hit behavior (needs_human_review true, citations empty)
- For dummy provider, assert deterministic output is stable
Keep tests fast and isolated."

10. Pass safety_identifier + user context safely

Prompt

"In Laravel AiClient and the AI controller:
- Add safety_identifier to payloads (hash user id or email; do not send raw PII)
- Include company_id, user_id and role where needed for auditing
- Keep ai_events logging: request_json/response_json should truncate long fields (store only first 10k chars)
Add tests that confirm these fields are included."

(OpenAI docs recommend using a stable safety_identifier and hashing identifiers to avoid sending PII.)

11. ✅ UI: show "Answer + Sources" with trust cues

Prompt

"Update CopilotAnswerPanel.tsx:
- Render answer_markdown
- Render citations list with doc title + snippet + 'Open document'
- Show a badge when needs_human_review=true ('Verify sources')
- Add 'Copy answer' button and 'Copy citations' button
No auto-actions; keep it advisory only."

12. ✅ Add an admin switch for "LLM answers enabled"

Prompt

"Add a tenant setting (DB + UI toggle) 'LLM Answers Enabled':
- If off, microservice uses dummy provider/deterministic summarizer
- If on, microservice uses OpenAI provider
Enforce this in Laravel so users can't bypass it. Log toggles into ai_events."