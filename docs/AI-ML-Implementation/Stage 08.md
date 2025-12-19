1. Define Stage 8 "contracts" first (so everything stays consistent)

Prompt

"Create docs/AI-ML-Implementation/Stage 08.md and write the Stage 8 scope + acceptance criteria:
- Semantic search across documents (Document Control Hub + maintenance manuals + RFQ text)
- Q&A endpoint returns answer + citations (doc_id, version, chunk_id, snippet)
- Tenant scoped (company_id) + permission scoped (user must have access to doc)
- All calls recorded in ai_events
- UI shows sources and never auto-applies changes
Keep it short and testable."

2. [Done] Add microservice embedding provider abstraction (provider-agnostic)

Prompt

"In ai_microservice/, create embedding_provider.py:
- Define interface EmbeddingProvider with embed_texts(list[str]) -> list[list[float]]
- Implement DummyEmbeddingProvider (deterministic hash-based vectors for local dev/tests)
- Add env switch AI_EMBEDDING_PROVIDER=dummy|openai|local
Don’t implement OpenAI/local yet—just structure for it."

3. [Done] Add chunking utility (consistent chunk sizes + metadata)

Prompt

"Create ai_microservice/chunking.py:
- Function chunk_text(text, max_chars=1500, overlap_chars=200) that splits on paragraph/sentence boundaries where possible
- Returns list of chunks with fields: chunk_index, text, char_start, char_end
Add unit tests for chunk sizes and overlap."

4. [Done] Decide the vector store interface (DB-agnostic)

Prompt

"Create ai_microservice/vector_store.py:
- Interface VectorStore with:
    - upsert_chunks(company_id, doc_id, doc_version, chunks, embeddings, metadata)
    - search(company_id, query_embedding, top_k, filters) -> list[SearchHit]
    - delete_doc(company_id, doc_id, doc_version=None)
- Implement InMemoryVectorStore for now (store vectors in RAM, cosine similarity).
Keep types clean and include docstrings."

5. [Done] Add "index document" endpoint (push docs into the vector store)

Prompt

"In ai_microservice/app.py, add Pydantic models:
- IndexDocumentRequest with fields: company_id, doc_id, doc_version, title, source_type, mime_type, text, metadata (dict), acl (list of role/user scopes)
Add POST /index/document:
- chunk the text
- embed chunks via EmbeddingProvider
- upsert into VectorStore with metadata including title, source_type, doc_version
Return: {status:'ok', indexed_chunks:<int>}.
Add logging + request id + error handling."

6. [Done] Add semantic search endpoint (returns snippets only)

Prompt

"Add POST /search endpoint:
- Input: company_id, query, top_k (default 8), filters (optional: source_type, doc_id, tags)
- Embed query, search VectorStore
- Return hits with: doc_id, doc_version, chunk_id, score, title, snippet (first 250 chars), and metadata
Do not generate answers here—search only."

7. [Done] Add "answer with citations" endpoint (RAG)

Prompt

"Add POST /answer endpoint:
- Input: company_id, query, top_k, filters
- Internally call search, then build a prompt context from top hits
- For now, generate a placeholder answer that:
    - summarizes the most relevant snippets in 5–8 bullets
    - returns citations array referencing the hits
Define response schema:
{status:'ok', answer:<string>, citations:[{doc_id, doc_version, chunk_id, score, snippet}] }
Do NOT call any external LLM yet—keep it deterministic so we can ship safely."

8. [Done] Add microservice tests for indexing/searching

Prompt

"Create ai_microservice/tests/test_rag.py:
- Index 2 documents with distinct content
- Search for a term from doc A and ensure top hit is doc A
- Call /answer and verify it returns answer + citations array non-empty
Use FastAPI TestClient."

9. [Done] Extend Laravel AI client with search + answer methods

Prompt

"In app/Services/Ai/AiClient.php, add methods:
- indexDocument(array $payload): array
- search(array $payload): array
- answer(array $payload): array
Use same envelope style + secret header + timeout + request id.
Record failures using existing exception flow."

10. [Done] Create ai_document_indexes table (tracking what’s indexed)

Prompt

"Add a migration ai_document_indexes:
- company_id, doc_id, doc_version
- source_type, title, mime_type
- indexed_at, indexed_chunks
- last_error (nullable), last_error_at (nullable)
- unique(company_id, doc_id, doc_version)
This table tracks indexing status and supports re-indexing."

11. [Done] Create a Laravel job to index documents into the microservice

Prompt

"Create app/Jobs/IndexDocumentForSearchJob.php:
- Input: company_id, doc_id, doc_version
- Load the document record + file text
- If file is PDF and no text exists, attempt server-side text extraction (use a pluggable extractor class; if extractor missing, fail gracefully with clear error)
- Send payload to AiClient->indexDocument()
- Update ai_document_indexes row and write ai_events feature='index_document'
Use chunked processing and good logging."

12. [Done] Add admin endpoint to trigger re-index (safe + gated)

Prompt

"Create POST /api/v1/admin/ai/reindex-document:
- Request: doc_id, doc_version
- Auth: company admin only
- Dispatch IndexDocumentForSearchJob
- Return standard envelope
Record ai_events feature='reindex_document'."

13. [Done] Add frontend API wrapper for search + answer

Prompt

"In resources/js/services/ai.ts, add:
- indexDocument(payload)
- semanticSearch(payload)
- answerQuestion(payload)
Return consistent {status,message,data,errors} and normalize errors."

14. [Done] Build "Copilot Search" UI (results + citations)

Prompt

"Create resources/js/components/ai/CopilotSearchPanel.tsx:
- Input box + filters (source_type, doc tags)
- Button ‘Search’
- Show result list with title, snippet, score, doc_version
- Clicking a result expands to show metadata and a ‘Open Document’ link (use existing document route)
- Don’t generate answers yet—search only."

15. [Done] Build "Ask Copilot" UI (answer + citations)

Prompt

"Create resources/js/components/ai/CopilotAnswerPanel.tsx:
- Input question + filters
- Button ‘Generate answer’
- Call answerQuestion()
- Render answer text and a citations list (each shows doc title + snippet, clickable)
- Add disclaimer text: ‘AI suggestions must be verified against source documents’
Must not auto-apply any changes."

16. [Done] Record all search/answer calls to ai_events

Prompt

"Update the Laravel endpoints (or controller you create for copilot) so every call to:
- indexDocument
- search
- answer
records an ai_events row with request_json, response_json (truncate large payloads), latency, status, entity fields if applicable."

17. [Done] Permission safety: doc ACL enforcement

Prompt

"Before indexing or returning search hits, enforce document access:
- Only index documents the company owns
- Only return results for docs the user can access (role-based / doc permissions)
Implement a server-side DocumentAccessPolicy helper and use it in the AI Copilot controller."

18. [Done] Add minimal feature tests (Laravel)

Prompt

"Write Pest feature tests:
- Non-admin cannot trigger reindex (403)
- Search endpoint requires auth (401)
- Search results exclude docs user doesn’t have access to
- ai_events row created on successful search/answer calls
Use mocked AiClient responses."