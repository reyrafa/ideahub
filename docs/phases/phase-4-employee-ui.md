# Phase 4: Employee UI (`/app` panel)

**Status:** blocked, waiting for wireframes
**Features:** F1 Submit, F2 Idea board, F3 Vote, F4 Comments, F5 Review workflow, F6 My ideas
**Branch / worktree:** `feature/phase-4-employee-ui` (can run in parallel with Phase 5)
**Depends on:** Phase 2 (Phase 3 for demo data)
**Wireframes needed:** **yes**: board, submit/edit form, idea page (vote, comments, status history, Change status modal), My ideas
**Agent:** `claude-dream-team:fullstack-developer`, skills `livewire-development`, `tailwindcss-development`, `laravel-best-practices`

## Goal
The employee-facing screens, thin wrappers over the Phase 2 actions. No business logic in the UI.

## Features
| Feature | Work |
|---|---|
| F1 | Create form with the anonymous checkbox; validation via `IdeaRules`; edit page hidden or blocked by policy |
| F2 | Board list: title, category, votes, status badge, author via `authorNameFor`; sort most voted / newest; filters category and status |
| F3 | Vote toggle on the idea page and board, count updates immediately |
| F4 | Comment thread, post, edit/delete within 15 minutes, Hide action for reviewers |
| F5 | Change status action (only allowed targets, required note), status history section |
| F6 | My ideas: own ideas with status and votes |

## Tests (Livewire/Filament)
Plan section 8, tests 13, 14, 20 (URL access) and 22, plus render and action tests for each screen above.

## Done when
Matches the wireframes, acceptance criteria for Ideas, Voting, Review and Comments (requirements section 8) pass by hand and by test, `composer test` green.

## Open
Wireframes not received yet. Do not start before they arrive.
