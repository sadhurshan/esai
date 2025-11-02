# Notifications — Deep Spec

## Events
RfqPublished, RfqInvitationSent, ClarificationPosted, QuoteSubmitted, QuoteWithdrawn, AwardIssued, PoSent, PoAcknowledged, ChangeOrderApproved, OrderStatusChanged, GrnCreated, NcrOpened, InvoiceCreated, InvoiceOverdue, PlanLimitHit.

## Delivery
- Push (Echo/Pusher) + queued email.
- Digests (daily/weekly).

## Prefs
user_notification_prefs: channel on/off per event; quiet hours optional.

## Tests
- Listening to events dispatches correct notifications.
