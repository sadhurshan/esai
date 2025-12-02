## Scope

Scan the entire workspace (backend + frontend) and verify the full authentication and account lifecycle is implemented correctly.

---

## Email / Password Auth, Sessions, Timeout

- SPA endpoints live under `routes/web.php` (`POST /api/auth/login|register|forgot-password|reset-password`, `POST /api/auth/logout`, `GET /api/auth/me`).
- `AuthSessionController@store` uses `Auth::attempt`, regenerates the session, and emits an `AuthResponseFactory` payload. No rate limiting or reCAPTCHA; unlike Fortify routes these are unthrottled.
- Session token returned to the client is the raw session ID (see `AuthResponseFactory` + `AuthContext` storing `token` in `localStorage`). The front-end replays that value as a `Bearer` token, and `AuthenticateApiSession` accepts bearer tokens as session IDs. This exposes the session to XSS/localStorage compromise.
- Logout (`AuthSessionController@destroy`) invalidates and regenerates the session token correctly.
- Session timeout relies on Laravel session lifetime (`config/session.php`, 120 minutes) and DB driver. There is no idle timeout enforcement on the SPA.

## Email Verification Flow

- Fortify email verification feature enabled (`config/fortify.php`) and custom SPA view wired through `VerifyEmailPage` with resend + status check actions.
- `App\Models\User` now implements `MustVerifyEmail`, so `/app` routes gated by the `verified` middleware actually enforce confirmation.
- `AuthResponseFactory` exposes `requires_email_verification`, `email_verified_at`, and `has_verified_email`. The SPA stores this flag, `RequireAuth` detours unverified users to `/verify-email`, and both login + registration flows redirect accordingly.
- `SelfRegistrationController` dispatches Laravel's `Registered` event before logging the owner in, ensuring Fortify's email notification queue fires. Feature tests (`AuthSessionTest`) cover the JSON payloads.

## Forgot Password → Reset

- APIs: `POST /api/auth/forgot-password` → `PasswordResetController@sendResetLink` (uses broker, generic messaging). `POST /api/auth/reset-password` → `PasswordResetController@reset` emits `PasswordReset` event.
- Frontend pages at `resources/js/pages/auth/forgot-password-page.tsx` and `reset-password-page.tsx` handle forms and client-side validation.
- Token expiry driven by `config/auth.php` (60 minutes). No UI for expired token handling, but broker response covers this.
- Security gap: Reset validation only enforces `min:8` (see `ResetPasswordRequest`), not the stronger `Password::default()` policy required elsewhere.

## Change Password / Change Email

- Password updates handled by `Settings\PasswordController` via `/settings/password`. Uses `current_password` rule and `Password::defaults()`. Route throttled (`throttle:6,1`).
- Email changes flow through `Settings\ProfileController`. Changing email clears `email_verified_at`, but due to missing `MustVerifyEmail` implementation the user is never blocked afterward.
- No re-authentication gates around profile changes beyond the implicit session.

## Two-Factor / SSO

- Fortify two-factor feature enabled with confirm + password confirm requirements, but SPA bypasses it: API login does not redirect to `/two-factor-challenge`, and there is no React page for two-factor setup/challenge despite generated route helpers (`resources/js/routes/two-factor`).
- Tests skip two-factor scenarios because feature “not enabled,” highlighting inconsistency. Practically, no 2FA enforcement exists.
- No SSO (Google/Microsoft) integration present.

## Routes / Controllers / Middleware Inventory

- Primary auth controllers: `Api\Auth\AuthSessionController`, `SelfRegistrationController`, `PasswordResetController`.
- Registration creates a user + company inside a transaction, stores uploaded documents, and logs the user in. Validation uses `SelfRegistrationRequest` (password confirmation + document requirements) but still allows missing company phone despite middleware later requiring it.
- Middleware relevant to auth lifecycle: `EnsureCompanyRegistered`, `EnsureCompanyOnboarded`, `EnsureSubscribed`, `AuthenticateApiSession`. These are wired in `bootstrap/app.php`.
- `EnsureCompanyRegistered` forces completion of company profile before accessing `/app`, but there is no React page mounted at `/company-registration`, so users hit a dead-end.

## Frontend Pages / Validation

- Auth pages implemented under `resources/js/pages/auth`. They use zod + react-hook-form for client validation. Register page enforces password complexity, domain regex, document uploads, etc.
- Plan selection page (`/app/setup/plan`) guards access to the main app until a plan is chosen, but owners cannot access `Settings → Company` to finish onboarding because `AuthContext.isAdmin` excludes `owner`.

## Email Templates

- Fortify default notifications (ResetPassword, VerifyEmail) are referenced in tests, but there is no customization in `resources/views` or `App\Notifications`. SPA flows rely on default SMTP templates.

## Recent Remediation (Nov 21, 2025)

- Implemented `MustVerifyEmail` on `User`, surfaced verification flags via `AuthResponseFactory`, redirected SPA flows to `/verify-email`, and added resend/status polling UI. Added feature tests to lock behavior in place.

## Gaps / Risks Summary

1. **2FA not enforced** – Feature flagged on backend, but login API never triggers challenge and frontend lacks screens. Either disable the feature or implement the challenge/setup flows.
2. **Session ID exposed as bearer token** – Returning DB session IDs to JS (and storing them in `localStorage`) makes session theft trivial. Prefer httpOnly cookies or scoped access tokens.
3. **No rate limiting on SPA auth endpoints** – Add `throttle:login`/`two-factor` middleware or manual limiter checks; currently brute-force is unrestricted.
4. **Company onboarding UX missing** – Middleware redirects to `/company-registration`, but no React route/page implements the wizard described in `docs/ACCEPTANCE.md`.
5. **Owner role lacks admin privileges in SPA** – Owners cannot reach company settings, blocking profile completion and plan requirements.
6. **Password reset policy weaker than registration** – Align `ResetPasswordRequest` with `Password::defaults()` to avoid weaker credentials after reset.
7. **Tests cover Fortify blade routes, not SPA APIs** – Add feature tests targeting `/api/auth/*` to detect regressions specific to the React flows.

Checklist complete with current findings and remediation items.
