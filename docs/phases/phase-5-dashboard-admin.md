# Phase 5: Dashboard and Admin (`/app` widgets, `/admin` panel)

**Status:** blocked, waiting for wireframes
**Features:** F7 Dashboard, plus admin management of users, roles and categories
**Branch / worktree:** `feature/phase-5-dashboard-admin` (can run in parallel with Phase 4)
**Depends on:** Phases 2 and 3
**Wireframes needed:** **yes**: dashboard, admin categories, admin users
**Agents:** `fullstack-developer`; `security-analyst` does a read-only pass before the merge

## Goal
Dashboard widgets and the admin-only management screens.

## Tasks
1. Three widgets: top 5 ideas by votes (uses `mostVoted()`, author via `authorNameFor`), ideas per status, new ideas this week.
2. `/admin` `CategoryResource` (name, active toggle) behind `CategoryPolicy`.
3. `/admin` `UserResource` (name, email, department, role) behind `UserPolicy`.
4. Trashed (withdrawn) ideas visible and restorable by admin via a trashed filter.
5. Admin sees the author of anonymous ideas everywhere.

## Tests
- Plan test 28 (panel access, admin-only resources).
- Widget data tests: top 5 order including the tie, per-status counts, this-week count, soft-deleted ideas excluded.

## Security pass (read-only)
Direct URL and Livewire calls cannot bypass policies; no anonymous author leaks in widgets; dependency CVE check.

## Done when
Matches the wireframes, `composer test` green, security findings addressed.
