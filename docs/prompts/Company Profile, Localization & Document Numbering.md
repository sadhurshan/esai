# Prompt for Copilot: Company Profile, Localization & Document Numbering

## Goal
Implement tenant-level **Company & Localization settings**: company profile (names, addresses), **currencies & FX display**, **UoM maps/conversions**, **time/date/number formats**, and **document numbering schemes** (RFQ/Quote/PO/Invoice/GRN/Credit). Ensure all downstream pages respect these settings. Use **TS SDK + React Query**, **react-hook-form + zod**, **Tailwind + shadcn/ui**, and existing providers. Admin-only.

---

## 1) Routes & Files
```tsx
<Route element={<RequireAuth><AppLayout/></RequireAuth>}>
  <Route path="/app/settings/company" element={<CompanySettingsPage/>} />
  <Route path="/app/settings/localization" element={<LocalizationSettingsPage/>} />
  <Route path="/app/settings/numbering" element={<NumberingSettingsPage/>} />
</Route>
```
Create/extend:
```
resources/js/pages/settings/company-settings-page.tsx
resources/js/pages/settings/localization-settings-page.tsx
resources/js/pages/settings/numbering-settings-page.tsx

resources/js/components/settings/address-editor.tsx
resources/js/components/settings/currency-preferences.tsx
resources/js/components/settings/uom-mapper.tsx
resources/js/components/settings/numbering-rule-editor.tsx

resources/js/hooks/api/settings/use-company.ts
resources/js/hooks/api/settings/use-update-company.ts
resources/js/hooks/api/settings/use-localization.ts
resources/js/hooks/api/settings/use-update-localization.ts
resources/js/hooks/api/settings/use-numbering.ts
resources/js/hooks/api/settings/use-update-numbering.ts
```
---

## 2) Backend (SDK) Contracts
- **Company**: GET/PATCH `/settings/company` → `{ legal_name, display_name, tax_id?, emails[], phones[], bill_to, ship_from, logos? }`
- **Localization**: GET/PATCH `/settings/localization` → `{ timezone, locale, date_format, number_format, currency: { primary, display_fx: boolean }, uom: { base_uom, maps: Record<string,string> } }`
- **Numbering**: GET/PATCH `/settings/numbering` → per-doc rules:
  ```ts
  type NumberRule = { prefix:string; seq_len:number; next:number; reset:"never"|"yearly"; sample?:string }
  { rfq:NumberRule, quote:NumberRule, po:NumberRule, invoice:NumberRule, grn:NumberRule, credit:NumberRule }
  ```

---

## 3) UI Requirements
- **CompanySettingsPage**: logo upload (optional), primary/billing/shipping addresses via `AddressEditor`; contact methods. Preview card.
- **LocalizationSettingsPage**: timezone select (with current UTC preview), locale, date/number format live preview; currency primary + “display FX tooltip” toggle; UoM mapping (`UomMapper`) with base UoM.
- **NumberingSettingsPage**: editable rules per document with live **sample**; confirm dialog when decreasing `seq_len` or changing `reset`.

---

## 4) Enforcement Across App
- Inject helpers into context: `formatMoney`, `formatDate`, `formatNumber`, `displayFx` toggle.
- Ensure RFQ/Quote/PO/Invoice/GRN/Credit pages display numbers/dates using helpers.
- Apply numbering when creating new docs (server ultimately assigns; FE shows **preview**).

---

## 5) Validation & Rules
- Timezone must be valid IANA string. Date/number formats must produce non-ambiguous previews.
- Base UoM cannot map to multiple targets; validate cycles.
- Numbering: `seq_len` 3–10; prefix ≤ 12 chars; show collision warning if preview overlaps existing year scope.

---

## 6) Tests
- Hooks unit tests for PATCH endpoints.
- Snapshot tests for live preview (date/money/number formatting).

---

## 7) Acceptance
- Settings pages save & persist; app wide formatting updates immediately.
- New docs show numbering preview and server-supplied final numbers.
- UoM and FX display respected across detail/list/compare tables.
