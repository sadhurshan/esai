- [x] 1. Add a “start_mode” parameter to the signup request

Prompt:

File: app/Http/Requests/Auth/SelfRegistrationRequest.php
In the rules() method, add validation for a new start_mode field. It should be required and accept only buyer or supplier. This will allow the signup form to specify whether the company is starting as a buyer or supplier. Currently, the request only validates name, email, company information, etc.. Add something like:

'start_mode' => ['required', 'string', Rule::in(['buyer', 'supplier'])],


Also update the PHPDoc and merge logic if needed.

- [x] 2. Pass start_mode from the controller to the action

Prompt:

File: app/Http/Controllers/Api/Auth/SelfRegistrationController.php
In the register() method, after validation, extract the start_mode from the request and include it in the payload passed to RegisterCompanyAction. Currently, the controller constructs the $payload with company name, domain, etc., and calls $this->registerCompanyAction->handle($payload). Modify it so that the payload contains a start_mode key with the user’s choice. Example:

$payload = [
    'name' => $request->input('company_name'),
    // existing fields …
    'start_mode' => $request->input('start_mode'),
];


Then pass this payload to RegisterCompanyAction.

- [x] 3. Update RegisterCompanyAction to handle supplier-first logic

Prompt:

File: app/Actions/Company/RegisterCompanyAction.php
Currently, the action always sets supplier_status to CompanySupplierStatus::None and directory_visibility to 'private'. Modify handle() so that if $payload['start_mode'] === 'supplier', it sets:

$company->supplier_status = CompanySupplierStatus::Pending;

$company->supplier_profile_completed_at = null;

Optionally set a flag (e.g., company_type or start_mode) on the company model to remember this choice.
For buyer (default) keep supplier_status = CompanySupplierStatus::None and existing defaults.
Also ensure directory_visibility is still 'private' and the owner is created as usual.

- [x] 4. Exempt supplier-first companies from plan selection & billing

Prompt:

File: app/Factories/AuthResponseFactory.php (or wherever requires_plan_selection is computed)
Currently, requires_plan_selection is true whenever the company has no plan_id or plan_code. Modify the logic so that if the company’s start_mode is supplier (or supplier_status !== CompanySupplierStatus::None), then requires_plan_selection is false. This will prevent the plan selection page from appearing for supplier-first companies.

- [x] 5. Add a start-mode selector on the frontend registration page

Prompt:

File: resources/js/pages/auth/register-page.tsx
Add a radio button or dropdown to allow users to choose “Start as Buyer” or “Start as Supplier”. Default to “buyer”. Update the form state and validation schema to include start_mode. When submitting, append start_mode to the FormData before sending it to authClient.register().
This page currently collects name, email, password, and company details and then calls registerAccount() and navigates to /verify-email or /app/setup/plan. After adding start_mode, update the navigation logic:

If the response says requiresPlanSelection && start_mode === 'buyer', redirect to plan selection as before.

If start_mode === 'supplier', skip plan selection and show a message that supplier profile approval is pending.

- [x] 6. Update the auth context to handle supplier-first onboarding

Prompt:

File: resources/js/contexts/auth-context.tsx
Extend the register() method to accept a start_mode. After receiving the auth response, determine whether the user should be routed to plan selection or a supplier-approval waiting page. Introduce a new state property such as needsSupplierApproval to reflect that company.supplier_status is pending. Set requiresPlanSelection to false when start_mode is supplier. Ensure the context still handles email verification and normal buyer flow.

- [x] 7. Create a waiting page for supplier approval

Prompt:

Files:

resources/js/pages/setup/supplier-waiting-page.tsx (new file)

resources/js/components/supplier-status-banner.tsx (optional)
Build a page/component that informs supplier-first users that their company is pending approval. Include a call-to-action that links to the “Supplier Application” page so they can complete their supplier profile while waiting. Display status badges (e.g., “Not Submitted”, “Submitted”, “Pending”, etc.). Use existing logic from supplier-application-panel.tsx for status messages and submission actions.

- [x] 8. Adjust plan gating & middleware

Prompt:

File: resources/js/components/RequireActivePlan.tsx
Currently, this component redirects users without an active plan to /app/setup/plan. Modify it so that if auth.company.start_mode === 'supplier' or auth.company.supplier_status !== 'none', it does not redirect to plan setup (supplier-first companies are free). It should still enforce plan selection for buyers.

- [x] 9. Modify server middleware if necessary

Prompt:

File: app/Http/Middleware/EnsureCompanyRegistered.php and EnsureSubscribed.php
Ensure these middlewares do not block supplier-first users. EnsureSubscribed already bypasses checks for suppliers, but EnsureCompanyRegistered might need adjustments so supplier-first users are considered “registered enough” to access the app while waiting for approval.

- [x] 10. Update the supplier directory queries

Prompt:

File: wherever supplier directory queries are made (likely a repository/service class)
Ensure that only companies with supplier_status === 'approved' are included in the directory. The existing logic already filters approved suppliers, but verify that the new pending and start_mode states are properly considered.

- [x] 11. Additional notes

Update the database schema/migration if needed to persist start_mode or company_type.
Update TypeScript types (e.g., AuthResponse interface) to include start_mode and supplier_status.
Write tests to cover supplier-first signup, plan bypass, and supplier approval flow.