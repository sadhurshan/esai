# Receiving & Quality — Deep Spec

## Data Model
- GRN: id, company_id, po_id, number, received_at, reference, attachments.
- GRNLine: grn_id, po_line_id, qty_received, qty_accepted, defects_json.
- NCR: id, company_id, grn_id, po_line_id, reason, disposition enum(rework,return,accept_as_is), status enum(open,closed).

## API
POST /pos/{id}/grns; POST /grns/{id}/ncrs; PATCH /ncrs/{id} close.

## UI
- GRN Create from PO lines; NCR modal.
- Quality dashboard (counts).

## Tests
- Three-way match surfaces discrepancies.
