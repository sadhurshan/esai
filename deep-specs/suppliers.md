# Suppliers — Deep Spec

## Scope & Goals
Discovery, profile, verification, certificates/KYC, ratings.

## Data Model
- Supplier: id, company_id, name, email, phone, website, address, city, country, geo (lat,lng), capabilities (JSON: methods, materials, tolerances, finishes, industries), lead_time_days, moq, rating_avg (DECIMAL 3,2), status enum(pending,approved,rejected,suspended), verified_at.
- SupplierDocument: supplier_id, type enum(iso9001, iso14001, as9100, itar, reach, rohs, insurance, nda, other), path, mime, size_bytes, issued_at, expires_at, status enum(valid,expiring,expired).
- Indexes: FULLTEXT(name, capabilities); supplier(status), supplier_documents(type,expires_at).

## API Surface (v1)
GET /suppliers — filters: capability, material, finish, tolerance, location, industries, certs, rating_min, lead_time_max; sort: match_score|rating|lead_time|distance|price_band.
POST /suppliers — admin-only (platform).
GET /suppliers/{id}
PUT /suppliers/{id}
GET /suppliers/{id}/documents
POST /suppliers/{id}/documents — S3 upload; size<=50MB; mime whitelist.

## UI States
- Directory page with filters, sort, pagination (25 default), skeleton & empty states.
- Profile with tabs: Overview, Capabilities, Certificates, Documents, Reviews.
- Edit (supplier_admin), Verify (platform_super).

## Workflows & Rules
- Verification sets status=approved and verified_at.
- Expiry watchdog flags supplier_documents within 30 days as 'expiring' and sends notice.

## Notifications
- Certificate expiring, supplier approved/suspended.

## Permissions
- Buyer: read approved suppliers.
- Supplier_admin: edit own supplier profile.
- Platform_super: verify, suspend.

## Tests & Acceptance
- List with filters returns envelope+meta; tenant-scoped.
- Upload rejects >50MB or invalid mime; stores S3 path.
