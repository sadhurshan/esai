# Semantic Search & Tooling Coverage

This tracker outlines which procurement modules are currently indexed for semantic search, the Copilot workspace tools that expose each dataset, and the known TODOs for missing or partial coverage. Update this file whenever a new data source (RFQs, receipts, contracts, etc.) is embedded or a new `workspace.*` tool ships so Copilot responses stay grounded in audited data.

## RFQs
- Status: complete
- Owner: Super Admin
- Sources: RFQ headers, timelines, quote summaries
- Semantic indexing: RFQ headers, timelines, and quote summaries stream into the embeddings pipeline via the RFQ snapshot jobs described in [ai_microservice/chunking.py](ai_microservice/chunking.py).
- Tooling coverage: `workspace.search_rfqs`, `workspace.get_rfq`, `workspace.get_quotes_for_rfq`, `workspace.get_awards`, `workspace.stats_quotes` (resolver: [app/Services/Ai/WorkspaceToolResolver.php](app/Services/Ai/WorkspaceToolResolver.php)).
- TODOs: add embeddings for RFQ clarifications + attachments so Copilot can cite supporting docs.

## Purchase Orders
- Status: partial
- Owner: Super Admin
- Sources: PO headers, line items, approvals
- Semantic indexing: partial; PO metadata is not yet chunked for retrieval, but PO drafts surfaced via workflows are persisted for future indexing (see [app/Services/Ai/Workflow/PurchaseOrderDraftConverter.php](app/Services/Ai/Workflow/PurchaseOrderDraftConverter.php)).
- Tooling coverage: *(none yet)*.
- TODOs: add a `workspace.get_purchase_order` tool plus PO chunking job before enabling Copilot PO answers.

## Receipts
- Status: not-started
- Owner: Super Admin
- Sources: receipts, inspections, received quantities
- Semantic indexing: not indexed; only placeholder data returned for testing.
- Tooling coverage: `workspace.get_receipts` (mocked JSON in [app/Services/Ai/WorkspaceToolResolver.php](app/Services/Ai/WorkspaceToolResolver.php)).
- TODOs: connect to receiving database tables, add virus-scanned attachments to embeddings, and deliver receipt search filters.

## Invoices
- Status: not-started
- Owner: Super Admin
- Sources: invoices, line items, 3-way match status
- Semantic indexing: not indexed; placeholder data only.
- Tooling coverage: `workspace.get_invoices` (mocked JSON in [app/Services/Ai/WorkspaceToolResolver.php](app/Services/Ai/WorkspaceToolResolver.php)).
- TODOs: pipe approved invoices + 3-way match status into embeddings and implement a `workspace.search_invoices` endpoint for filters (status, due date, supplier).

## Maintenance & Asset Health
- Status: not-started
- Owner: Super Admin
- Sources: maintenance work orders, asset telemetry summaries
- Semantic indexing: no maintenance or digital-twin docs in the semantic store yet.
- Tooling coverage: *(none)*.
- TODOs: capture CMMS records + telemetry summaries, then add `workspace.get_asset_maintenance` once the data contract is finalized.

## Contracts
- Status: partial
- Owner: Super Admin
- Sources: contract metadata, clauses, attachments
- Semantic indexing: only contract metadata is searchable through the general documents index; clauses/attachments are not chunked per [docs/documents.md](docs/documents.md).
- Tooling coverage: *(none)*.
- TODOs: add dedicated contract embeddings and expose `workspace.get_contract` / `workspace.search_contracts` with permission-aware snippets.

## Supplier Profiles
- Status: complete
- Owner: Super Admin
- Sources: supplier master data, ESG questionnaires
- Semantic indexing: supplier master data and ESG questionnaires are indexed for global search (see [app/Services/Ai/SupplierScrapeService.php](app/Services/Ai/SupplierScrapeService.php)).
- Tooling coverage: `workspace.list_suppliers` plus supplier stats inside `workspace.get_quotes_for_rfq`.
- TODOs: add a `workspace.get_supplier_profile` tool that includes risk + performance history so Copilot can cite a single supplier view.

## Inventory / Low-Stock Signals
- Status: partial
- Owner: Super Admin
- Sources: part master data, replenishment policies, balances
- Semantic indexing: only static part metadata is chunked; transactional balances live outside the vector store.
- Tooling coverage: `workspace.get_inventory_item`, `workspace.low_stock`.
- TODOs: embed replenishment policies and expose near real-time coverage metrics for SKUs flagged by Copilot.

## Documents & Knowledge Base
- Status: complete
- Owner: Super Admin
- Sources: document uploads, knowledge base content
- Semantic indexing: document ingestion + chunk packing handled by [ai_microservice/vector_store.py](ai_microservice/vector_store.py).
- Tooling coverage: indirect — assistants cite documents via `citations` arrays but there is no dedicated `workspace.get_document` tool.
- TODOs: add tool coverage for key doc sets (quality manuals, compliance checklists) so Copilot can fetch structured summaries rather than only embeddings.
