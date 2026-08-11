---
paths:
  - 'resources/views/**'
---

# Views

## UI must be built from TailKit Application UI, not hand-rolled Tailwind
This app strictly uses TailKit.com's Application UI component library for all UI. Do not hand-author Tailwind markup or bring back the old Breeze/Jetstream components — use the TailKit MCP server tools instead:
- `search_components` / `browse_catalog` (package `application-ui`) to find a component
- `get_component_suggestions` for the right set when building a new page type (dashboard, auth_page, settings_page, etc.)
- `get_component_code` to fetch markup, preferring `tech: "alpine"` (matches this TALL-stack app's existing Livewire+Alpine interactivity); `html` is fine for static, non-interactive markup

Layout conventions already established: authenticated app shell = Light Sidebar family (`a-l-light-sidebar-*`, see `resources/views/layouts/app.blade.php`); auth pages = Boxed Simple (`a-p-*-04`, see `resources/views/layouts/guest.blade.php`).

Reuse/extend the existing Blade component wrappers in `resources/views/components/` (`x-primary-button`, `x-text-input`, `x-dropdown`, `x-modal`, etc.) instead of inlining TailKit markup ad hoc in views. New reusable pieces should follow the same pattern: TailKit visuals behind a small Blade component API (`@props`, slots), so call sites stay framework-idiomatic and don't duplicate TailKit's raw HTML everywhere.

## Icons must be Heroicons, matching TailKit's hi-* conventions
Any icon used in a view must be a Heroicon, matching the icon conventions already baked into TailKit's component markup (classes like `hi-outline hi-*`, `hi-mini hi-*`, `hi-solid hi-*` — these classes are just naming metadata, the actual icon is the inline SVG `<path>`). Do not pull in another icon library (Font Awesome, Lucide, Bootstrap Icons, freehand SVGs, etc.).

Conventions to match (see `resources/views/components/application-logo.blade.php` and `resources/views/layouts/app.blade.php` sidebar/header for examples):
- Outline (`hi-outline`): `viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke="currentColor"`, sized with `size-*` utilities. Used for nav items, most inline icons.
- Mini (`hi-mini`): `viewBox="0 0 20 20" fill="currentColor"`. Used for small chevrons/status icons in buttons, dropdowns.
- Solid (`hi-solid`): `viewBox="0 0 20 20" fill="currentColor"`, bolder shapes. Used sparingly (e.g. toggle/menu icons).

Prefer pulling the icon straight from a TailKit component via `get_component_code` (most components already embed the right icon+size+color pairing) over hand-picking a Heroicon and guessing the styling.
