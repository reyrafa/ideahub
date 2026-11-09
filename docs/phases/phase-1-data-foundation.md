# Phase 1: Data foundation

**Status:** not started
**Features:** groundwork for F1 to F7
**Branch / worktree:** `feature/phase-1-data-foundation`
**Depends on:** Phase 0
**Wireframes needed:** no
**Agent:** `claude-dream-team:fullstack-developer`

## Goal
Schema, models, enum, factories, roles and panel access, so later phases only add behaviour.

## Tasks
1. Publish the Spatie permission migration (`vendor:publish --tag=permission-migrations`). Never edit migrations that already ran.
2. New migrations:
   - `add_department_to_users_table` (`department` nullable string).
   - `categories` (`name` unique, `is_active` default true).
   - `ideas` (FKs to users and categories, `title` 120, `problem`, `proposal`, `expected_impact` nullable, `is_anonymous`, `status` default `new` + index, `votes_count` unsigned default 0, `submitted_at`, `softDeletes`, composite index `(votes_count, submitted_at)`).
   - `votes` (`idea_id`, `user_id`, `created_at` only, **unique `(idea_id, user_id)`**).
   - `comments` (`body`, `is_hidden`, `hidden_by` nullable, `hidden_at` nullable).
   - `status_changes` (`from_status` nullable, `to_status`, `note`, `changed_by`, `changed_at`).
3. `App\Enums\IdeaStatus` with `allowedTransitions()`, `canTransitionTo()`, `requiresNote()`, `isVotingOpen()`, `allowsComments()`, Filament `HasLabel`/`HasColor`.
4. Models with relations and casts: `Category`, `Idea`, `Vote`, `Comment`, `StatusChange`; update `User` (fillable `department`, relations).
5. Factories for every model, with states: `IdeaFactory::anonymous()`, `->status()`, `CategoryFactory::inactive()`, `UserFactory::employee()/reviewer()/admin()`, `CommentFactory::hidden()`.
6. `RolesAndPermissionsSeeder` (roles `employee`, `reviewer`, `admin`; permissions per plan section 3).
7. `User` implements `FilamentUser::canAccessPanel()`: `/admin` is admin only, `/app` needs any role. Add `->login()` to `AdminPanelProvider`.

## Tests (smoke level)
- Factories build valid rows.
- Unique `(idea_id, user_id)` index rejects a duplicate vote.
- `IdeaStatus` transition table (every allowed and one blocked pair per status).
- Employee gets 403 on `/admin`; admin gets in.

## Done when
`php artisan migrate:fresh` works, `composer test` is green, Pint clean.
