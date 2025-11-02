# Quotes — Deep Spec

## Scope
Supplier quote submission, revisions, withdrawals; buyer comparison & award.

## Data Model
- Quote: id, company_id, rfq_id, supplier_id, revision int, status enum(draft,submitted,withdrawn,rejected,awarded), currency, lead_time_days, total_price, notes, attachments_count, submitted_at.
- QuoteItem: quote_id, rfq_item_id, unit_price, lead_time_days, comments.
- Indexes: quotes(rfq_id, supplier_id, status), quote_items(quote_id, rfq_item_id).

## API
GET /quotes?inbox=supplier|buyer&status=…
POST /rfqs/{id}/quotes — supplier creates draft; PUT to submit; PATCH to withdraw.
GET /rfqs/{id}/quotes/compare — buyer view with computed rankings.

## UI
- Supplier Inbox (filters).
- Submit Quote Wizard (lines + attachments).
- Buyer Compare: sortable matrix (price/lead/rating).

## Workflows
- New submission increments revision if existing quote.
- Withdraw permitted before due_at.

## Notifications
- Quote submitted/withdrawn; buyer awarded.

## Permissions
- Supplier must be invited or RFQ open_bidding.
- Buyer within same company.

## Tests
- Compare endpoint returns normalized numbers & envelope; award locks other quotes.
