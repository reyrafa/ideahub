# Phase 7: Stretch features

**Status:** not started (after Phase 6)
**Features:** F12 Impact tracking, F13 Export, F11 AI weekly summary
**Branch / worktree:** `feature/phase-7-stretch`
**Depends on:** Phase 6
**Wireframes needed:** yes for F12 (impact fields and dashboard total)
**Agents:** `fullstack-developer`, `qa-engineer`, `security-analyst` (for F11 payload review)

## Features
| Feature | Work |
|---|---|
| F12 | For Implemented ideas, record actual results (hours or money saved); new migration; dashboard total widget. |
| F13 | Admin exports all ideas with votes and status to CSV. The export respects BR-7 policy rules. |
| F11 | Scheduled command sends the past week's new ideas to the Claude API and stores a short themes summary for reviewers on the dashboard. **Idea text only: no names, no emails.** |

## Constraints
- F11 is built **only after the trainer approves** use of a real API key. Until then, build against `Http::fake()`.
- Adding any package needs the user's approval first.

## Tests
- F12: only Implemented ideas accept results; total is correct.
- F13: CSV has the right columns and rows; only Admin can export; anonymous handling matches BR-7.
- F11: `Http::fake()` asserts the outgoing payload contains no names or emails; summary is saved.

## Done when
`composer test` is green and the user has reviewed the F11 payload.
