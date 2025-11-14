RFQ Detail Page + RFQ Creation Wizard (React + TS)

Goal

Implement:
    - RfqDetailPage at /app/rfqs/:id with tabs (Overview, Lines, Suppliers & Clarifications, Timeline/Audit, Attachments).
    - RfqCreateWizard at /app/rfqs/new with a multi-step flow to create and publish an RFQ.

Use our TS SDK + React Query, react-hook-form + zod, Tailwind + shadcn/ui, plan gating via useAuth().hasFeature, and the existing ApiClientProvider / AuthProvider patterns.

1.  Routes & files

    a. Add routes:
    <Route element={<RequireAuth><AppLayout/></RequireAuth>}>
        <Route path="/app/rfqs/new" element={<RfqCreateWizard />} />
        <Route path="/app/rfqs/:id" element={<RfqDetailPage />} />
    </Route>

    b. Create:
    resources/js/pages/rfqs/rfq-detail-page.tsx
    resources/js/pages/rfqs/rfq-create-wizard.tsx
    resources/js/components/rfqs/*
    resources/js/hooks/api/rfqs/*

2.  Hooks (SDK-backed)

Create these hooks using React Query; all call our generated SDK:

    a. useRfq(id) → GET RFQ; returns { data, isLoading, error, refetch }.
    b. useRfqLines(id, params) → GET lines; supports pagination/sort.
    c. useRfqSuppliers(id) → GET invited suppliers & their statuses.
    d. useRfqClarifications(id) → list Q&A, post question/answer.
    e. useRfqTimeline(id) → audit events (published, amended, awarded, etc.).
    f. Mutations:
        - useCreateRfq() → POST RFQ draft
        - useUpdateRfq() → PATCH RFQ
        - usePublishRfq() → POST /rfqs/:id/publish
        - useAmendRfq() → POST /rfqs/:id/amend
        - useCloseRfq() → POST /rfqs/:id/close
        - useInviteSuppliers() → POST /rfqs/:id/suppliers/invite
        - useAddLine() / useUpdateLine() / useDeleteLine()
        - useUploadAttachment() / useDeleteAttachment()
    g. Each mutation should:
        - Use onSuccess to invalidate/reload relevant queries (RFQ, lines, suppliers).
        - Handle 402 (plan) and 403 (policy) via our toast + PlanUpgradeBanner.

3.  RFQ Detail UI (RfqDetailPage)

Structure with shadcn Tabs:

    a. Header bar
        - RFQ number, title, status badge, due date.
        - Primary actions:
            i. Draft: Publish
            ii. Published: Amend, Close
            iii. Closed/Awarded: disable Publish/Amend appropriately
        - Secondary: Edit details, Invite suppliers, Export PDF (stub), More …

    b. Tabs
        - Overview
            i. Summary: method/material, incoterms/payment (if applicable), publish & due dates, visibility.
            ii. Supplier coverage: invited count, responses received (show mini stats).
            iii. Quick links to open clarifications.
        - Lines
            i. Table with columns: Part/Description, Qty + UoM, Target price (Money), Required date, Notes.
            ii. Inline add/edit modal using react-hook-form + zod.
            iii. If localization/UoM is enabled, display the company base UoM and allow inline conversion (use our conversion endpoint; display converted values).
        - Suppliers & Clarifications
            i. Invited suppliers list with status (invited/accepted/responded).
            ii. Button to Invite suppliers (emails or supplier IDs).
            iii. Clarifications thread: list questions/answers grouped by supplier; form to add Q/A.
        - Timeline / Audit
            i. Vertical timeline of events: created, published, amendments, invitations, responses, close/award, etc.
        - Attachments
            i. Upload (drag/drop), list with file name, size, upload date; delete action.
    
    c. States
        - Show skeletons while loading.
        - Empty states for no lines/suppliers/attachments.
        - Error boundaries for network/API errors → toast + retry.
        - Optimistic updates for add/edit line and supplier invites where safe.

4.  RFQ Creation Wizard (RfqCreateWizard)

    a. Multi-step form using react-hook-form with a shared zod schema. Steps:

        - Basics
            i. Title, summary, method/material selection, visibility (public/invite-only).
            ii. Required: title, due date > today; zod validation messages.

        - Lines
            i. Dynamic line items: Part/description, quantity + UoM, required date, optional target price.
            ii. If UoM/localization enabled, provide UoM select and show converted preview.

        - Suppliers
            i. Invite suppliers: paste emails or select from directory (if endpoint exists). Deduplicate.

        - Dates & Terms
            i. Publish date (default now), due date, optional incoterms/payment terms fields if supported.

        - Attachments
            i. Upload specs; list and allow removal before submit.

        - Review & Publish
            i. Read-only summary; confirm.
            ii. Actions: Save as Draft or Publish Now (toggle).

    b. Behavior

        - On Next step validate with zod; persist wizard state in component (and localStorage as backup).
        - On Finish:
            i. Create RFQ draft (useCreateRfq).
            ii. Upsert lines, attachments, supplier invites.
            iii. If “Publish Now”, call usePublishRfq.
            iv. Redirect to /app/rfqs/:id and toast success.

    c. UX
        - Stepper component at top, “Back/Next” buttons bottom, disabled state on submit, progress indicator.
        - Show plan-limit guardrails (max lines/suppliers) from feature flags; on 402 raise upgrade banner.

5.  Shared components

Build small, reusable pieces in components/rfqs:

    a. RfqStatusBadge
    b. RfqActionBar
    c. RfqLineEditorModal
    d. InviteSuppliersDialog
    e. ClarificationThread
    f. AttachmentUploader

All should be typed, accessible, and theme-consistent.

6.  Types, validation & money/UoM

    a. Mirror backend DTOs from the TS SDK types.
    b. zod schemas per step; enforce due date in future, positive qty, valid money minor amounts.
    c. Use our Money helpers in the SDK (or map to {amount_minor, currency}) and show formatted human value.
    d. UoM: when converting, call the conversion endpoint; display base UoM alongside selected UoM.

7.  Testing & linting

    a. Add unit tests for useRfq and useRfqs hooks (mock SDK).
    b. Render tests for wizard step validation (at least Basics & Lines).
    c. ESLint/Prettier clean; TypeScript strict.

8.  Acceptance Criteria

    a. /app/rfqs/:id renders the RFQ with tabs; actions correctly reflect status (Draft → Publish; Published → Amend/Close).
    b. Lines tab supports add/edit/delete with optimistic UX and server sync.
    c. Suppliers & Clarifications tab lists invites and supports posting Q/A.
    d. Timeline displays recent audit events.
    e. Attachments support upload/delete.
    f. /app/rfqs/new wizard creates an RFQ draft, adds lines/suppliers/attachments, and optionally publishes, then redirects to detail with a success toast.
    g. All network calls go through the TS SDK; 401/402/403 handled via existing global logic.
    h. Plan gating hides features and shows upgrade prompts where appropriate.
    i. Responsive and accessible UI using Tailwind + shadcn/ui.

