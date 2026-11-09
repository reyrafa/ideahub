# Phase 6: Should-have features

**Status:** not started (after Phases 1 to 5 are merged)
**Features:** F8 Notifications, F9 Duplicate warning, F10 Leaderboard
**Branch / worktree:** `feature/phase-6-should-have`
**Depends on:** Phases 4 and 5
**Wireframes needed:** yes for F9 (hint on the create form) and F10 (leaderboard widget)
**Agents:** `fullstack-developer`, `qa-engineer`

## Features
| Feature | Work |
|---|---|
| F8 | `IdeaStatusChanged` queued mail notification, sent from `ChangeIdeaStatus` after commit. No author name when the idea is anonymous (BR-7). |
| F9 | While typing a title on the create form, show existing ideas with similar titles (significant-word matching, soft-deleted excluded) so people vote instead of reposting. |
| F10 | `LeaderboardWidget`: top contributors this quarter by Approved + Implemented ideas. Anonymous ideas do not count (BR-7). |

## Tests
- `Notification::fake()`: sent on each valid change, not sent on a blocked one, content hides the anonymous author.
- Similar titles matched, unrelated ones not, trashed ones not.
- Leaderboard counts only Approved and Implemented, excludes anonymous ideas and ideas from other quarters.

## Done when
Features match the wireframes and `composer test` is green.
