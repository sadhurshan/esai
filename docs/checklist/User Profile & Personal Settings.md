User Profile & Personal Settings

Check whether there is a proper user profile and personal settings flow:

- View/update profile: name, avatar, job title, phone number.
- Personal timezone and locale/language preference.
- Personal notification preference mapping (e.g. email/push/digest options).
- Default organization selection when the user belongs to multiple companies.
- Verify backend endpoints, models, and DB fields match the UI.
- Report any missing endpoints, broken forms, or fields that exist in DB but not exposed in the UI (or vice versa).

Checklist Findings (Updated)

- ✅ **SPA profile route/pages exist.** `app-routes.tsx` serves `/app/settings/profile`, `settings/settings-page.tsx` links to it, and `resources/js/pages/settings/profile-settings-page.tsx` renders the full form backed by React Hook Form + React Query.
- ✅ **User storage and validation cover all profile fields.** Migration `2025_11_21_120000_add_profile_fields_to_users_table.php`, `ProfileUpdateRequest`, the `User` model, auth payload (`AuthResponseFactory`), and API resource now expose avatar path/URL, job title, phone, locale, and timezone.
- ✅ **Personal timezone/locale preferences surfaced.** The profile form includes select inputs wired to `/api/me/profile`, refreshing auth state on save so formatting contexts can react.
- ✅ **Notification preferences usable end-to-end.** `/api/notification-preferences` is consumed via `use-notification-preferences` + `use-update-notification-preference`, and `/app/settings/notifications` renders configurable cards with channel/digest controls.
- ✅ **Default organization selection implemented.** `company_user` now stores `is_default`/`last_used_at`, `UserCompanyController` exposes `/api/me/companies` + `/api/me/companies/switch`, the top bar dropdown allows instant switching, and the profile page ships a “Default organization” manager tied to the same API.
- ✅ **Auth payload matches UI expectations.** `AuthResponseFactory::transformUser()` returns avatar URLs and the new profile attributes, so `useAuth` consumers (top bar, user info panels, etc.) receive consistent data.