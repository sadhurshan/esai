Start Front‑End – Auth, Layout & SDK Integration (React + TS)

Project setup & environment

1. Use the existing React Starter Kit (TypeScript) within the repo. Verify react-router v6+, @tanstack/react-query, @shadcn/ui, tailwindcss, and our own TS SDK (generated under resources/sdk/ts-client/ in the API contract step).

2. Update package.json scripts if needed:
    a. dev should run Vite/Next/CRA dev server.
    b. Add generate-sdk that runs the API spec → TS SDK generator (if not already).
    c. Add lint and format scripts for ESLint/Prettier.

3. Configure Tailwind with custom colors (from the brand guidelines) and fonts. Use tailwind.config.cjs with our palette (primary, secondary, accent, neutral) and typical spacing scale.

Authentication & API integration

1. Auth context: create src/contexts/AuthContext.tsx providing user state, JWT token, login, logout, refresh. Use createContext & useReducer or useState. Persist JWT in localStorage and set default Authorization header on our TS SDK’s fetch wrapper.

2. API client: import our generated client (e.g. import { Api } from '@sdk/ts-client'). Instantiate a single Api with base URL from .env (e.g. VITE_API_BASE_URL), passing in token retrieval and error handling. Provide this client via a context (ApiClientContext) or use React Query’s QueryClient with custom fetcher.

3. Auth pages:
    a. /login: build a page with email and password fields, using shadcn/ui components (Input, Button, Card). Call the login API (POST /auth/login or equivalent) via the TS SDK. On success, store token and user, redirect to dashboard; on failure, display toast.
    b. /forgot-password: optional, call POST /auth/forgot-password.
    c. /reset-password/:token: optional.
    d. /register can be skipped if registration is invite-only; otherwise include basic sign‑up fields and call POST /auth/register.

4. Route protection: implement RequireAuth component that reads auth context and either renders children or redirects to /login. Apply to all /app/* routes.

Core layout & navigation

1. App shell: create src/layouts/AppLayout.tsx. This layout wraps the main routes with:
    a. A TopBar with logo, company switcher (if multi‑tenant), notifications icon (fetch unread counts via API), and user avatar with dropdown (profile, settings, logout).
    b. A Sidebar with primary nav items (use shadcn/ui NavigationMenu or custom vertical menu). Items should match major modules: Dashboard, RFQs, Quotes, POs, Invoices, Inventory, Assets, Orders, Risk/ESG, Analytics, Settings, Admin (conditional on role). Use icons from lucide-react.
    c. A Content area using Outlet from react-router to render page components.

2. Responsive design: On mobile, collapse the sidebar into a hamburger menu; use useState to toggle. Use Tailwind breakpoints (md: etc.).

3. Breadcrumbs: in the TopBar or within each page component, show breadcrumbs derived from useLocation() and route definitions.

4. Global feedback: integrate @/components/ui/toast system (shadcn) for success/error messages; ensure API error handling surfaces messages to the user.

Page routing & skeletons

1. Set up React Router routes in src/App.tsx (or routes.tsx):
<Route element={<RequireAuth><AppLayout /></RequireAuth>}>
  <Route path="/app" index element={<DashboardPage />} />
  <Route path="/app/rfqs" element={<RfqListPage />} />
  <Route path="/app/rfqs/:id" element={<RfqDetailPage />} />
  …
  {/* Add stubs for Quotes, POs, Invoices, Inventory, etc. */}
</Route>
<Route path="/login" element={<LoginPage />} />

2. Create stub components for each page (e.g. RfqListPage.tsx) displaying a heading and skeleton loader (shadcn Skeleton). Use React Query to fetch data and show EmptyState when none.

3. Use Helmet or react-helmet-async to set page titles and meta descriptions.

State management & hooks

1. Use @tanstack/react-query for data fetching/mutation. Define custom hooks per resource, e.g. useRfqs(), useCreateRfq(), etc., using the TS SDK internally and returning query status.

2. For forms, use react-hook-form with zod for validation (mirrors backend validation rules).

3. Store user preferences (theme, language, table page size) in localStorage or call /user/prefs endpoints if available.

Feature flags & plan gating

1. In AuthContext, include the company’s plan and feature flags (decoded from JWT or fetched via /company/profile). Expose helper hasFeature(key) to conditionally render routes/menu items (e.g. hide Inventory if inventory_enabled=false).

2. Show an upgrade banner when hitting plan limits (the API returns HTTP 402; handle it globally by checking error codes and displaying a modal that links to Billing page).

Error handling & unauthorized

1. Create an ErrorBoundary component wrapping routes to show fallback UI when unexpected errors occur.

2. For 403 responses (policy/plan gating), route to a “Access Denied” page with explanatory text and link back to dashboard.

3. For 404 (not found), create a NotFound page.

Testing & storybook (optional for later prompts)

1. Configure Cypress or Playwright for E2E testing (login flow, navigation, page loads).

2. Set up Storybook to document reusable components (Card, Form, Button, Table).

Acceptance Criteria

1. User can log in with valid credentials and is redirected to /app.

2. Protected routes redirect unauthenticated users to /login.

3. Sidebar and top bar render correctly on desktop and mobile, with menu items reflecting enabled modules.

4. TS SDK is used for all API calls; tokens automatically included in Authorization header.

5. Each stub page shows a skeleton and properly handles loading, empty and error states via React Query.

6. Plan‑gating hides modules not enabled for the current plan.

7. Code is TypeScript‑strict, follows established directory structure (components, pages, hooks, layouts, etc.), and passes ESLint/Prettier.