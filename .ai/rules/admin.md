---
paths:
  - 'resources/views/layouts/app.blade.php,resources/views/livewire/pages/admin/**'
---

# Admin

## Platform-admin UI gates on currentOrganization, not isSuperAdmin() alone
The distinct platform-admin sidebar/nav (dark theme, "Platform Admin" badge, Organizations/Users links) shown in `layouts/app.blade.php` is gated on `auth()->user()->currentOrganization?->is_super_admin`, i.e. whether the user has *switched into* the super-admin org via the org switcher — not on `User::isSuperAdmin()` alone (which is true regardless of active org). A super admin viewing a regular customer org still gets the normal layout.

The `/admin/organizations` and `/admin/users` routes/pages (`routes/admin.php`, `resources/views/livewire/pages/admin/*.blade.php`) are authorized differently: they guard with `abort_unless(auth()->user()->isSuperAdmin(), 403)` in `mount()` (independent of active org), since `Gate::before` in `AppServiceProvider` already grants super admins every ability and there's no single `Organization` instance to `Gate::authorize` against for a platform-wide index.

The organization switcher (`livewire/layout/organization-switcher.blade.php`) always lists the super-admin org first (`orderByDesc('is_super_admin')`).

## Sidebar org-scoped nav follows the route organization for super admins, not currentOrganization
`layouts/app.blade.php` computes `$navOrganization` (used for the People/Products/Transactions/Events links) as: for a super admin, the `{organization}` route-model-bound parameter of the current request if present, else `currentOrganization`; for everyone else, always `currentOrganization`. This lets a super admin click into any organization from `/admin/organizations` (via `organizations.people.index`, which the org name links to) and get full org-scoped navigation for it, even though they aren't a member and their `currentOrganization` is unrelated (e.g. still the super-admin org, or their own org). Don't key this off `$isPlatformAdmin` (`currentOrganization?->is_super_admin`) — a super admin who has switched into a *regular* org can still browse into a different org by URL, and `$isPlatformAdmin` would be false in that case even though the route organization should still win.
