# Prompt for Copilot: Analytics MVP (KPI Cards + Charts)

## Goal
Ship a **lightweight analytics** module: KPI cards and a few charts for RFQs, Quotes, Spend, and Inventory. Respect plan gating.

---

## 1) Routes & Files
```tsx
<Route element={<RequireAuth><AppLayout/></RequireAuth>}>
  <Route path="/app/analytics" element={<AnalyticsPage/>} />
</Route>
```
Create:
```
resources/js/pages/analytics/analytics-page.tsx
resources/js/components/analytics/kpi-card.tsx
resources/js/components/analytics/mini-chart.tsx

resources/js/hooks/api/analytics/use-analytics.ts
```
---

## 2) KPIs & Charts
- KPIs: Open RFQs, Avg RFQ Cycle Time, Quotes Received (30d), Spend (30d), On‑time Receipts %.
- Charts: RFQs over time, Spend by Supplier (bar), On‑time vs Late Receipts (stacked).
- Use recharts; responsive containers; skeletons while loading.

---

## 3) Tests & Acceptance
- API contracts stable; charts render with correct series; plan gating hides page if disabled.
