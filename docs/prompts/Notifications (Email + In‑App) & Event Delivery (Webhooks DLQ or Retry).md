# Prompt for Copilot: Notifications (Email + In‑App) & Event Delivery (Webhooks DLQ/Retry)

## Goal
Implement user-facing **Notifications** (email + in‑app center) and robust **Event delivery**: webhook delivery logs, retry/backoff, and dead‑letter queue (DLQ) surfacing. Use **TS SDK + React Query**, Tailwind/shadcn, and existing providers.

---

## 1) Routes & Files
```tsx
<Route element={<RequireAuth><AppLayout/></RequireAuth>}>
  <Route path="/app/notifications" element={<NotificationCenterPage/>} />
  <Route path="/app/events/deliveries" element={<EventDeliveriesPage/>} />
</Route>
```
Create:
```
resources/js/pages/notifications/notification-center-page.tsx
resources/js/pages/events/event-deliveries-page.tsx

resources/js/components/notifications/notification-bell.tsx
resources/js/components/notifications/notification-list.tsx
resources/js/components/events/delivery-status-badge.tsx

resources/js/hooks/api/notifications/use-notifications.ts
resources/js/hooks/api/notifications/use-mark-read.ts
resources/js/hooks/api/events/use-deliveries.ts
resources/js/hooks/api/events/use-retry-delivery.ts
resources/js/hooks/api/events/use-replay-dlq.ts
```
---

## 2) Backend (SDK)
- Notifications:
  - `useNotifications(params)` → GET `/notifications` (status, type, createdAt, payload link).
  - `useMarkRead(ids[])` → POST `/notifications/read`.
- Event deliveries:
  - `useDeliveries(params)` → GET `/events/deliveries` (endpoint, event, status, attempts, latency, lastError).
  - `useRetryDelivery(id)` → POST `/events/deliveries/:id/retry`.
  - `useReplayDlq(ids[])` → POST `/events/dlq/replay`.

---

## 3) UI
- **NotificationBell** in top bar (badge count). Clicking opens **NotificationList** drawer (filter: unread/all).
- **NotificationCenterPage**: table with filters, bulk mark-read, deep links into RFQ/Quote/PO/etc.
- **EventDeliveriesPage**: filter by endpoint/event/status; show DeliveryStatusBadge; retry & replay actions; detail drawer with request/response.

---

## 4) Email Templates
- Add minimal template partials for RFQ published, quote submitted/withdrawn, PO sent/ack, invoice posted, GRN posted, credit note posted.
- Ensure templates use company branding & localization helpers (date/money).

---

## 5) Tests & Acceptance
- Mark-read updates badge count.
- Failed deliveries can be retried; DLQ messages replayed; status updates visible.
- Emails render with correct localization tokens.
