---
paths:
  - 'tests/**'
---

# Tests

## Strict TDD with Pest for new tests
This project practices strict TDD: write the test before the implementation, for every change. A change is not done until it has test coverage and the tests pass.

New tests must be written in Pest (function-style `it()`/`test()`), not PHPUnit classes — this supersedes the Boost-generated "convert Pest to PHPUnit" guidance in CLAUDE.md, which predates Pest's addition (installed 2026-08-11 at user request). Existing PHPUnit class-based tests in tests/Feature and tests/Unit are left as-is; do not proactively convert them.

Drive tests from user experience: prioritize Feature tests that exercise the actual user-facing flow (HTTP requests, Livewire/Volt component interaction) over Unit tests. Only add Unit tests for critical or genuinely complex logic (non-trivial calculations, edge-case-heavy business rules) — not for every method.

tests/Pest.php binds Tests\TestCase + RefreshDatabase to everything under tests/Feature, and Tests\TestCase (no RefreshDatabase) to tests/Unit.

Always run tests in compact mode to minimize output: `php artisan test --compact` (auto-detects Pest) or `vendor/bin/pest --compact`, scoped with `--filter=` when possible.
