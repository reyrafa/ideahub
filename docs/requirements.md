# Project 3: Suggestion Box & Idea Voting

> **One-liner:** Employees submit improvement ideas, colleagues vote and comment, and a review panel moves the best ideas from “New” to “Implemented”.
>
> **Analogy:** A digital suggestion box with a scoreboard: the best ideas rise to the top, and everyone can see what happened to them.

---

## 1. Background

Good ideas get lost in emails and meetings. People stop sharing because they never hear back.

Your app makes ideas visible, lets the crowd show what matters most, and shows the status of every idea so nobody is left wondering.

---

## 2. Tech requirements

| Item | Requirement |
|---|---|
| Framework | Laravel 13 (Livewire starter kit for login) |
| Admin panel | Filament 5 |
| Database | SQLite on your laptop (MySQL optional) |
| Roles | Spatie Laravel Permission |
| Tests | Pest |
| AI tooling | Claude Code + Laravel Boost, with a `CLAUDE.md` for the project |
| Data | Factories and seeders that create a realistic demo dataset |
| Bonus | Deployed live on Laravel Cloud |

---

## 3. Users and roles

| Role | Can do |
|---|---|
| **Employee** | Submit ideas (optionally anonymous). Vote on and comment on other people’s ideas. Edit their own idea while it is New. |
| **Reviewer** | Everything an Employee can do, plus change an idea’s status and hide inappropriate comments. |
| **Admin** | Manage users, roles and categories. See everything, including who wrote anonymous ideas. |

**Permission matrix**

| Action | Employee | Reviewer | Admin |
|---|:-:|:-:|:-:|
| Submit an idea | ✅ | ✅ | ✅ |
| Vote / comment | ✅ | ✅ | ✅ |
| Edit own idea | While New | While New | ✅ |
| Change idea status | ❌ | ✅ | ✅ |
| Hide a comment | ❌ | ✅ | ✅ |
| See the author of an anonymous idea | ❌ | ❌ | ✅ |
| Manage categories and users | ❌ | ❌ | ✅ |

---

## 4. Data model (minimum)

| Model | Key fields |
|---|---|
| **User** | name, email, department, role |
| **Category** | name (e.g. Cost Saving, Safety, Process, Workplace, Digital), is_active |
| **Idea** | user_id, category_id, title, problem, proposal, expected_impact, is_anonymous, status, votes_count, submitted_at |
| **Vote** | idea_id, user_id, created_at, with a **unique index on (idea_id, user_id)** |
| **Comment** | idea_id, user_id, body, is_hidden, hidden_by |
| **StatusChange** | idea_id, from_status, to_status, note, changed_by, changed_at |

---

## 5. Features

### Must have (MVP)

- **F1 · Submit an idea.** Enter a title, category, the problem, the proposal and the expected impact, with an optional “Submit anonymously” checkbox.
- **F2 · Idea board.** A list of all ideas with the title, category, vote count, status badge and author (or “Anonymous”). It can be sorted by most votes or newest, and filtered by category and status.
- **F3 · Vote.** Upvote an idea with one click, and click again to remove the vote. The count updates immediately.
- **F4 · Comments.** Discuss an idea in a comment thread on its page.
- **F5 · Review workflow.** Reviewers move ideas through the statuses in section 6 with a **Change status** action. The idea page shows its status history.
- **F6 · My ideas.** Each user sees their own ideas, their status and their votes.
- **F7 · Dashboard.** At least 3 widgets:
  - top 5 ideas by votes
  - ideas per status
  - new ideas this week

### Should have

- **F8 · Notifications.** Email the author when their idea changes status.
- **F9 · Duplicate warning.** When submitting, show existing ideas with similar titles so people vote instead of reposting.
- **F10 · Leaderboard.** Top contributors this quarter, counted by their Approved and Implemented ideas.

### Level-up (stretch)

- **F11 · AI weekly summary.** A scheduled command sends the past week’s new ideas to the Claude API and saves a short summary of themes for reviewers to read on the dashboard. Only idea text is sent: no names and no emails.
- **F12 · Impact tracking.** For Implemented ideas, record actual results (e.g. hours or money saved) and show the total on the dashboard.
- **F13 · Export.** Admin exports all ideas with votes and status to CSV.

---

## 6. Business rules and logic

Each rule must be enforced on the server side (Form Request, model, action or policy), not only in the form. **Each rule should have at least one test.**

### Voting

- **BR-1 · One vote per person.** A user can vote on an idea only once. This is enforced by the unique database index **and** by the code.
  - Clicking twice (or two fast requests) must never create two votes.
- **BR-2 · No self-voting.** Authors can't vote on their own ideas, even anonymous ones.
- **BR-3 · Voting window.** Votes can be added or removed only while the idea is **New** or **Under Review**.
  - Votes on ideas that are Approved, Rejected or Implemented are frozen and stay counted.
- **BR-4 · Accurate count.** `votes_count` always equals the number of Vote rows for that idea, after adding and after removing.

### Ideas

- **BR-5 · Required content.** The title must be 10 to 120 characters. Problem and proposal are required, with at least 30 characters each. The category must be active.
- **BR-6 · Editing.** The author can edit an idea only while it is **New** and has **zero votes**, so nobody changes an idea after people have voted on it.
  - The author can withdraw (delete) their idea only while it is New.
- **BR-7 · Anonymous means anonymous.** For an anonymous idea:
  - Employees and Reviewers see “Anonymous” everywhere: board, idea page, comments by the author, exports and notifications.
  - Only Admin can see who wrote it.
  - It does **not** count toward the leaderboard.
- **BR-8 · Ranking.** The “Most voted” sort orders by `votes_count` descending, then by `submitted_at` ascending (the older idea wins a tie).

### Review workflow

- **BR-9 · Who reviews.** Only Reviewers and Admin can change status. A reviewer can't change the status of **their own** idea.
- **BR-10 · Notes are required** when moving to Rejected (the reason, at least 20 characters) and to Implemented (what was done, at least 20 characters).
- **BR-11 · History.** Every status change is saved in StatusChange with from, to, note, who and when, and the history is shown on the idea page.

### Comments

- **BR-12 · Comment length.** Comments are 2 to 1,000 characters, and only on ideas that aren't Rejected.
- **BR-13 · Edit window.** Authors can edit or delete their own comment for 15 minutes after posting.
- **BR-14 · Moderation.** Reviewers can hide a comment, which records who hid it. Hidden comments are not shown to Employees but stay in the database.

### Status workflow

| From | To | Who | Condition |
|---|---|---|---|
| — | New | Author | BR-5 passes |
| New | Under Review | Reviewer / Admin | Not their own idea (BR-9) |
| Under Review | Approved | Reviewer / Admin | Not their own idea |
| Under Review | Rejected | Reviewer / Admin | Reason note ≥ 20 characters (BR-10) |
| Approved | Implemented | Reviewer / Admin | Note ≥ 20 characters (BR-10) |
| New | Withdrawn (deleted) | Author | Only while New (BR-6) |

Any other transition must be blocked (e.g. New → Implemented, Rejected → Approved).

---

## 7. Seed data for the demo

- 5 categories (one inactive), 15 employees, 2 reviewers, 1 admin
- 20 ideas spread across every status, 3 of them anonymous
- Votes and comments spread so the top 5 is clear, including a tie to show the ranking rule
- Demo logins written in your README, e.g. `employee@demo.test`, `reviewer@demo.test`, `admin@demo.test` (password: `password`)

---

## 8. Acceptance criteria

Tick each one during your demo or show its test.

**Ideas**
- [ ] When I submit an idea with a 5-character title, then I see a validation error.
- [ ] When I submit anonymously, then the board and idea page show “Anonymous” to employees and reviewers, and Admin sees my name.
- [ ] I can edit my New idea before anyone votes. After the first vote, the Edit button is gone and a direct edit request is blocked.

**Voting**
- [ ] When I vote, the count goes from 4 to 5. When I click again, it goes back to 4.
- [ ] Two vote requests sent at the same time create only one vote.
- [ ] I can't vote on my own idea.
- [ ] I can't vote on an Approved idea, and its count stays the same.
- [ ] Two ideas with 10 votes each are ordered oldest first.

**Review**
- [ ] As a reviewer, I move an idea New → Under Review → Approved → Implemented, and each step appears in its history with my name and the time.
- [ ] Rejecting without a 20-character reason is blocked.
- [ ] New → Implemented directly is blocked.
- [ ] As a reviewer, I can't change the status of my own idea.
- [ ] An employee can't change status, even by typing the URL.

**Comments**
- [ ] I can edit my comment within 15 minutes, but not after.
- [ ] A hidden comment disappears for employees, and Admin can still see it.

---

## 9. Testing requirements

- At least **12 Pest tests** covering BR-1 to BR-14. The most important ones are one-vote-per-person, no self-voting, the voting window, anonymous privacy and invalid status transitions.
- At least **2 authorization tests** (an employee blocked from changing status; a reviewer blocked from reviewing their own idea).
- Use `$this->travel(16)->minutes()` to test the comment edit window.
- `php artisan test` passes during your presentation.

---

## 10. Out of scope

Rewards or points shops, budget approval workflows, and integration with chat tools. Don't build these. Mention them as “future work” if you like.

**F11 note:** never send employee names, emails or confidential details to an external AI API. Send idea text only, and ask your trainer before using a real API key.

---
