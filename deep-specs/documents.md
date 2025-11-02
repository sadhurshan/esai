# Documents & CAD — Deep Spec

## Data Model
- Document (morph): id, company_id, documentable_type/id, kind enum(rfq,quote,po,invoice,supplier,part,cad,manual,certificate,other), filename, path (S3), mime, size_bytes, version, hash_sha256, watermark_json, soft deletes.

## API
POST /documents (S3 upload); GET /{entity}/{id}/documents.

## UI
- Document hub with filters (kind, entity, owner, date).
- Preview for PDF/images; 3D/CAD viewer placeholder.

## Rules
- Max 50MB per file (configurable).
- Public thumbnails only; binaries private on S3.

## Tests
- Upload policy; version bump on replace.
