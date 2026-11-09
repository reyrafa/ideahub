# Phase 2: Domain rules (backend, test first)

**Status:** not started
**Features:** server-side behaviour behind F1, F3, F4, F5
**Branch / worktree:** `feature/phase-2-domain-rules`
**Depends on:** Phase 1
**Wireframes needed:** no
**Agents:** `qa-engineer` writes the tests first; `fullstack-developer` makes them pass.

## Goal
Every business rule BR-1 to BR-14 enforced on the server in policies and action classes, independent of any UI.

## Tasks
1. Policies: `IdeaPolicy` (`update`, `delete`, `vote`, `changeStatus`, `viewAuthor`, `comment`), `CommentPolicy` (`view`, `update`, `delete`, `hide`), `CategoryPolicy`, `UserPolicy`.
2. `IdeaRules::rules()` shared validation (title 10 to 120, problem/proposal min 30, active category).
3. Actions in `app/Actions/`: `SubmitIdea`, `UpdateIdea`, `WithdrawIdea` (soft delete), `ToggleVote`, `ChangeIdeaStatus`, `PostComment`, `EditComment`, `DeleteComment`, `HideComment`.
4. Model helpers: `Idea::mostVoted()`, `newest()`, `authorNameFor(?User)`, `Comment::visibleTo(User)`.
5. `ToggleVote`: transaction + `lockForUpdate()`; only move `votes_count` when a row was really inserted (`insertOrIgnore`) or deleted. No observers touch the counter.

## Rule map
| Rule | Where |
|---|---|
| BR-1, BR-4 | unique index + `ToggleVote` |
| BR-2, BR-3 | `IdeaPolicy::vote` |
| BR-5 | `IdeaRules` |
| BR-6 | `IdeaPolicy::update/delete` (admin bypass for edit) |
| BR-7 | `IdeaPolicy::viewAuthor`, `Idea::authorNameFor` |
| BR-8 | `Idea::mostVoted()` |
| BR-9, BR-10, BR-11 | `ChangeIdeaStatus` + `IdeaPolicy::changeStatus` + `IdeaStatus` |
| BR-12 | `PostComment` + `IdeaPolicy::comment` |
| BR-13 | `CommentPolicy::update/delete` |
| BR-14 | `CommentPolicy::hide`, `HideComment`, `visibleTo` |

## Tests (Pest, `tests/Feature`)
Plan section 8, tests 1 to 12, 16 to 21 and 23 to 27, at action and policy level. Use `$this->travel(16)->minutes()` for BR-13. Includes at least 2 authorization tests (employee cannot change status; reviewer cannot review own idea).

## Done when
All listed tests pass, `votes_count` equals the vote row count after mixed add/remove sequences, `composer test` green.
