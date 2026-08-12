---
paths:
  - 'resources/views/layouts/app.blade.php,resources/views/livewire/pages/admin/**'
---

# Admin

## Platform-admin UI gates on currentOrganization, not isSuperAdmin() alone
The distinct platform-admin sidebar/nav (dark theme, "Platform Admin" badge, Organizations/Members links) shown in `layouts/app.blade.php` is gated on `auth()->user()->currentOrganization?->is_super_admin`, i.e. whether the user has *switched into* the super-admin org via the org switcher — not on `User::isSuperAdmin()` alone (which is true regardless of active org). A super admin viewing a regular customer org still gets the normal layout.

The `/admin/organizations` and `/admin/members` routes/pages (`routes/admin.php`, `resources/views/livewire/pages/admin/*.blade.php`) are authorized differently: they guard with `abort_unless(auth()->user()->isSuperAdmin(), 403)` in `mount()` (independent of active org), since `Gate::before` in `AppServiceProvider` already grants super admins every ability and there's no single `Organization` instance to `Gate::authorize` against for a platform-wide index.

The organization switcher (`livewire/layout/organization-switcher.blade.php`) always lists the super-admin org first (`orderByDesc('is_super_admin')`).
