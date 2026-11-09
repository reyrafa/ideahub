# Phase 0: Setup

**Status:** done (repo already existed)
**Features:** none (project plumbing)
**Branch / worktree:** `master`
**Depends on:** none
**Wireframes needed:** no

## State
- Git repo exists, default branch is `master` (not `main`). Commits: `initial commit`, `agents`.
- Uncommitted changes: `.claude/settings.json`, `.gitignore`.
- `/docs` is listed in `.gitignore`, so `docs/requirements.md` and `docs/phases/` are not tracked and will not appear in new worktrees.

## Conventions for later phases
- Phase branches start from `master`.
- Merge back into `master` only after review and with the user's approval.

## Remaining check
- Run `composer test` once as a baseline before Phase 1.
