# Documents & CAD — Deep Spec

## Data Model
- Document (morph): id, company_id, documentable_type/id, kind enum(rfq,quote,po,invoice,supplier,part,cad,manual,certificate,other), filename, path (S3), mime, size_bytes, version, hash_sha256, watermark_json, soft deletes.

## API
POST /documents (S3 upload); GET /{entity}/{id}/documents.

## UI
- Document hub with filters (kind, entity, owner, date).
- Preview for PDF/images; 3D/CAD viewer placeholder.
- CAD insights panel for RFQ attachments (materials, finishes, tolerances, similar parts, GD&T review flag).

## Rules
- Max 50MB per file (configurable).
- Public thumbnails only; binaries private on S3.

## RFQ Attachment Parsing Pipeline
Purpose: Extract structured RFQ fields from uploaded attachments (PDF, CAD, images) and surface prefill + rationale in the RFQ wizard.

### Inputs
- RFQ draft context (title, notes, method, supplier list).
- Attachments metadata: filename, mime, size, checksum, storage path.
- Optional user hints (process, material, target dates).

### Outputs
- Suggested RFQ line items: part name, material, finish, process, quantity, tolerance, required date.
- Detected conflicts or gaps (missing material/finish/process/qty/lead time).
- Rationale map: which attachment or page/section informed each field.

### Flow
- On attachment upload, enqueue a parsing job.
- Job extracts text + metadata, then runs structured field extraction.
- Results are stored as a draft suggestion payload linked to the RFQ wizard session.
- Wizard shows prefill banner with per-line rationale and lets the user edit all fields.

### Storage
- Store extraction payloads with document metadata (document id, version, hash) and link to the RFQ draft context.
- Retain extraction history for auditability.

### CAD Extraction Pipeline (Phase 1)
- On CAD/drawing upload, queue `ParseCadDocumentJob` to extract text + filename signals.
- Persist results in `ai_document_extractions` (company_id, document_id, document_version, status, extracted_json, gdt_flags_json, similar_parts_json, extracted_at, last_error).
- API: `GET /documents/{document}/cad-extraction` returns status + extracted fields + similar parts + GD&T flags.

### Guardrails
- Do not auto-apply suggestions without user confirmation.
- Flag low-confidence or conflicting signals for manual review.
- Respect tenant scoping and access permissions.
- Enforce virus scanning before parsing.

### TODO
- Confirm the storage retention policy for extraction artifacts.

## Tests
- Upload policy; version bump on replace.
