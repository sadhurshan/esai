Build Dashboard & RFQ List Pages (React + TS)

1.  Dashboard page (DashboardPage.tsx)

    a. Display summary cards for key metrics: number of open RFQs, quotes awaiting review, POs awaiting acknowledgement, unpaid invoices, and low‑stock parts. Use Card and icons from lucide-react to keep the UI consistent.
    b. Fetch data via the TS SDK (RfqsApi, QuotesApi, etc.) using React Query hooks. Show skeleton placeholders while loading and handle errors with toasts.
    c. Include a “Create RFQ” button linking to the RFQ wizard.
    d. Respect plan gating: hide analytics cards if the analytics_enabled flag is false and show an upgrade call‑to‑action using PlanUpgradeBanner.
    e. Use responsive grid layouts with Tailwind (grid-cols-1 sm:grid-cols-2 lg:grid-cols-3) so cards wrap on small screens.

2.  RFQ List page (RfqListPage.tsx)

    a. Implement a useRfqs hook that accepts filters (status, search term, date range, pagination) and uses the TS SDK to fetch paginated RFQ data. Expose loading and error states.
    b. Add a search input and filter dropdown for RFQ status (draft, open, closed, awarded). Optionally add date range filters.
    c. Render results in a table with columns: RFQ number, title, total quantity, method/material, publish/due dates, status. Use NavLink to link the RFQ number or title to its detail page.
    d. Provide pagination controls based on the meta data returned by the API. Show an empty state message when no RFQs match the filters.
    e. Add a “New RFQ” button to the header, linking to the RFQ creation page.

3.  Routing & Layout

    a. Define routes in routes.tsx or App.tsx to include:
    <Route element={<RequireAuth><AppLayout /></RequireAuth>}>
        <Route path="/app" index element={<DashboardPage />} />
        <Route path="/app/rfqs" element={<RfqListPage />} />
        <Route path="/app/rfqs/:id" element={<RfqDetailPage />} />
        {/* add more routes as needed */}
    </Route>
    <Route path="/login" element={<LoginPage />} />

    b. Ensure the sidebar highlights the active route (Dashboard or RFQs) and hides sections the user’s plan doesn’t allow.

4.  Form & state handling

    a. Use react-hook-form with zod schemas for form validation. For now, there are no forms on these pages, but you’ll need them when you build the RFQ creation/edit pages.
    b. Use React Query for all data fetching and mutation to leverage caching and automatic refetching.
    c. Pull plan and feature information from the AuthContext via useAuth() to conditionally render UI.

5.  UX considerations

    a. Implement proper loading states (skeletons) and empty states (“No RFQs yet – start by creating one!”).
    b. Display global errors or plan limit messages using the toast system. Handle HTTP 402 (“Upgrade required”) responses by triggering the upgrade banner.
    c. Ensure accessibility of all inputs and buttons, and responsive design across breakpoints.

6.  Acceptance criteria

    a. Navigating to /app shows the dashboard with metric cards populated from the API.
    b. Navigating to /app/rfqs displays a paginated, filterable list of RFQs, with proper loading/error/empty states.
    c. All data is fetched via the TS SDK through React Query; auth tokens are automatically attached.
    d. Plan gating hides analytics cards and nav items appropriately and displays an upgrade prompt when necessary.
    e. The UI is consistent with existing styles (Tailwind + shadcn/ui) and fully responsive.
