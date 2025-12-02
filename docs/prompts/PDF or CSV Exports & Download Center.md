# Prompt for Copilot: PDF/CSV Exports & Download Center

## Goal
Provide **brandable PDFs** and **CSV exports** for RFQ, Quote, PO, Invoice, GRN, Credit; add a **Download Center** tracking generation/status/history with retry. Respect localization/numbering.

---

## 1) Routes & Files
```tsx
<Route element={<RequireAuth><AppLayout/></RequireAuth>}>
  <Route path="/app/downloads" element={<DownloadCenterPage/>} />
</Route>
```
Create/extend:
```
resources/js/pages/downloads/download-center-page.tsx
resources/js/components/downloads/download-job-row.tsx

resources/js/hooks/api/downloads/use-downloads.ts
resources/js/hooks/api/downloads/use-request-export.ts
resources/js/hooks/api/downloads/use-retry-download.ts
```
---

## 2) Triggers
- Add “Export PDF/CSV” buttons to RFQ/Quote/PO/Invoice/GRN/Credit detail pages → call `useRequestExport({ type, id, format })`.
- Show toast + link to Download Center.

---

## 3) Download Center
- Table: Job Id, Doc Type, Ref#, Format, Status (queued/processing/ready/failed), Requested At, Expires At, Actions (download/retry).
- Polling with React Query; auto-refresh; skeleton/empty states.

---

## 4) Templates
- PDFs include company logo, addresses, document number/date, line tables, totals (money/UoM), and footer notes.
- CSVs include raw data fields for import.

---

## 5) Tests & Acceptance
- Requests create jobs; ready jobs provide download URL.
- PDFs/CSVs reflect localization & numbering settings.
