# API Envelope & HTTP Map
```json
{ "status": "success|error", "message": "text", "data": {}, "errors": { "field": ["msg"] } }
```
Codes → Name: 200 OK, 201 Created, 204 NoContent, 400 BadRequest, 401 AuthRequired, 403 Forbidden, 404 NotFound, 409 DuplicateEntity, 422 ValidationError, 429 RateLimited, 500 ServerError.

**Pagination**
Query: `?page=1&per_page=25&sort_by=created_at&direction=desc`
Response: `{ data:[], meta:{ total, page, per_page, last_page } }` (default 25; max 100).
