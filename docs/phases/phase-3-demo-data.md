# Phase 3: Demo data

**Status:** not started
**Features:** supports every demo and acceptance check
**Branch / worktree:** `feature/phase-3-demo-data`
**Depends on:** Phases 1 and 2
**Wireframes needed:** no
**Agent:** `claude-dream-team:fullstack-developer`

## Goal
`php artisan migrate:fresh --seed` produces the realistic dataset from requirements section 7.

## Seed content
- Roles and permissions seeder.
- 5 categories, one inactive (Cost Saving, Safety, Process, Workplace, Digital).
- 15 employees, 2 reviewers, 1 admin. Demo logins: `employee@demo.test`, `reviewer@demo.test`, `admin@demo.test`, password `password`.
- 20 ideas across every status, 3 anonymous, each with consistent `StatusChange` history.
- Votes spread so the top 5 is clear, including one tie (same `votes_count`, different `submitted_at`) to show BR-8.
- Comments, including one hidden, one older than 15 minutes.
- `votes_count` is set from the real vote rows, never typed by hand.

## Tasks
1. `CategorySeeder`, `UserSeeder`, `IdeaSeeder` (also votes, comments, history), wire into `DatabaseSeeder`.
2. Add the demo logins to the README (only the README section the requirements ask for).

## Tests
- Seeding runs, counts match (5/18/20/3), every idea's `votes_count` equals its vote rows, a tie exists.

## Done when
`migrate:fresh --seed` is clean and `composer test` is green.
