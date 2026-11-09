<?php

namespace Database\Seeders;

use App\Enums\IdeaStatus;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Idea;
use App\Models\StatusChange;
use App\Models\User;
use App\Models\Vote;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class IdeaSeeder extends Seeder
{
    /**
     * Demo ideas: title, category, author, final status, anonymous, days since submission, vote count.
     *
     * Vote counts are targets only; `votes_count` is always recounted from the real Vote rows.
     * "Weekly standup bot" and "Shared team calendar" tie on 12 votes to show BR-8.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: IdeaStatus, 4: bool, 5: int, 6: int}>
     */
    private function ideas(): array
    {
        return [
            ['Replace paper timesheets with a mobile form', 'Digital', 'liam@demo.test', IdeaStatus::Implemented, false, 40, 16],
            ['Install motion-sensor lighting in meeting rooms', 'Cost Saving', 'olivia@demo.test', IdeaStatus::Approved, false, 30, 14],
            ['Weekly standup bot for distributed teams', 'Digital', 'noah@demo.test', IdeaStatus::UnderReview, false, 20, 12],
            ['Shared team calendar for every project', 'Process', 'ava@demo.test', IdeaStatus::UnderReview, false, 12, 12],
            ['Add anti-slip mats at the loading dock', 'Safety', 'lucas@demo.test', IdeaStatus::Approved, true, 25, 10],
            ['Standardise the onboarding checklist', 'Process', 'mia@demo.test', IdeaStatus::Implemented, false, 45, 9],
            ['Switch to double-sided printing by default', 'Cost Saving', 'ethan@demo.test', IdeaStatus::Implemented, false, 50, 8],
            ['First-aid refresher course every year', 'Safety', 'sofia@demo.test', IdeaStatus::UnderReview, false, 9, 7],
            ['Quiet focus room on the second floor', 'Workplace', 'jack@demo.test', IdeaStatus::Rejected, false, 35, 6],
            ['Single sign-on for all internal tools', 'Digital', 'zoe@demo.test', IdeaStatus::Approved, false, 22, 5],
            ['Reduce the number of recurring meetings', 'Process', 'owen@demo.test', IdeaStatus::UnderReview, true, 8, 5],
            ['Bulk purchase of office supplies', 'Cost Saving', 'chloe@demo.test', IdeaStatus::New, false, 6, 4],
            ['Emergency exit signs in the warehouse', 'Safety', 'leo@demo.test', IdeaStatus::New, false, 5, 4],
            ['Ergonomic chairs for the support team', 'Workplace', 'nina@demo.test', IdeaStatus::Rejected, false, 28, 3],
            ['Internal wiki for common customer answers', 'Digital', 'employee@demo.test', IdeaStatus::New, false, 4, 3],
            ['Monthly knowledge-sharing lunch sessions', 'Process', 'liam@demo.test', IdeaStatus::New, false, 3, 2],
            ['Car-sharing board for the commute', 'Cost Saving', 'olivia@demo.test', IdeaStatus::New, true, 2, 2],
            ['Automatic reminders for expense reports', 'Digital', 'noah@demo.test', IdeaStatus::New, false, 2, 1],
            ['Clearer labels on the recycling bins', 'Workplace', 'ava@demo.test', IdeaStatus::New, false, 1, 1],
            ['Dashboard for open support tickets', 'Digital', 'employee@demo.test', IdeaStatus::New, false, 0, 0],
        ];
    }

    /**
     * Comments: idea title, commenter, body, hours after submission (null = just now), hidden.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: int|null, 4: bool}>
     */
    private function comments(): array
    {
        return [
            ['Replace paper timesheets with a mobile form', 'ethan@demo.test', 'We piloted this in Sales and it saved hours every week.', 5, false],
            ['Replace paper timesheets with a mobile form', 'sofia@demo.test', 'Great result, thanks to everyone who helped roll it out.', 600, false],
            ['Install motion-sensor lighting in meeting rooms', 'jack@demo.test', 'Facilities estimates the payback at about two years.', 3, false],
            ['Install motion-sensor lighting in meeting rooms', 'owen@demo.test', 'Can we start with the rooms on the top floor?', 20, false],
            ['Weekly standup bot for distributed teams', 'mia@demo.test', 'Would this work with our existing chat tool?', 10, false],
            ['Weekly standup bot for distributed teams', 'chloe@demo.test', 'Please keep the questions short, long forms get ignored.', 30, false],
            ['Shared team calendar for every project', 'leo@demo.test', 'Spam, buy cheap watches at example.invalid', 8, true],
            ['Shared team calendar for every project', 'nina@demo.test', 'This would also help with holiday planning.', 15, false],
            ['Add anti-slip mats at the loading dock', 'lucas@demo.test', 'I wrote this one after seeing a near miss last winter.', 2, false],
            ['Add anti-slip mats at the loading dock', 'zoe@demo.test', 'Strongly agree, the dock is slippery when it rains.', 6, false],
            ['Quiet focus room on the second floor', 'liam@demo.test', 'We could book it like a normal meeting room.', 4, false],
            ['Reduce the number of recurring meetings', 'olivia@demo.test', 'A "no meeting Wednesday" would be a good first step.', 7, false],
            ['Reduce the number of recurring meetings', 'employee@demo.test', 'Happy to share the meeting audit I did last month.', null, false],
            ['Internal wiki for common customer answers', 'ava@demo.test', 'Support could seed it with our top 20 macros.', 12, false],
        ];
    }

    /**
     * Seed 20 ideas with votes, comments and a consistent status history (BR-4, BR-11).
     */
    public function run(): void
    {
        if (Idea::query()->exists()) {
            return;
        }

        $users = User::query()->orderBy('id')->get();
        $reviewers = User::role('reviewer')->orderBy('id')->get();
        $ideasByTitle = [];

        foreach ($this->ideas() as $index => [$title, $categoryName, $authorEmail, $status, $isAnonymous, $daysAgo, $voteTarget]) {
            $author = $users->firstWhere('email', $authorEmail);
            $submittedAt = now()->subDays($daysAgo)->subHours(2);

            $idea = Idea::factory()->create([
                'user_id' => $author->id,
                'category_id' => Category::query()->where('name', $categoryName)->value('id'),
                'title' => $title,
                'problem' => "Today this is a recurring pain point: {$title}.",
                'proposal' => "We propose a small, low-risk pilot first: {$title}.",
                'expected_impact' => 'Saves time and effort for the whole team.',
                'is_anonymous' => $isAnonymous,
                'status' => $status,
                'submitted_at' => $submittedAt,
            ]);

            $this->seedHistory($idea, $author, $reviewers->get($index % $reviewers->count()), $submittedAt);
            $this->seedVotes($idea, $users, $index, $voteTarget, $submittedAt);

            $ideasByTitle[$title] = $idea;
        }

        $this->seedComments($ideasByTitle, $users, $reviewers->first());
    }

    private function seedHistory(Idea $idea, User $author, User $reviewer, CarbonInterface $submittedAt): void
    {
        $this->recordChange($idea, null, IdeaStatus::New, null, $author, $submittedAt);

        $path = match ($idea->status) {
            IdeaStatus::New => [],
            IdeaStatus::UnderReview => [IdeaStatus::UnderReview],
            IdeaStatus::Approved => [IdeaStatus::UnderReview, IdeaStatus::Approved],
            IdeaStatus::Rejected => [IdeaStatus::UnderReview, IdeaStatus::Rejected],
            IdeaStatus::Implemented => [IdeaStatus::UnderReview, IdeaStatus::Approved, IdeaStatus::Implemented],
        };

        $from = IdeaStatus::New;

        foreach ($path as $step => $to) {
            $changedAt = $submittedAt->copy()->addDays($step + 1);

            $this->recordChange($idea, $from, $to, $this->noteFor($to), $reviewer, $changedAt->isFuture() ? now() : $changedAt);
            $from = $to;
        }
    }

    private function recordChange(Idea $idea, ?IdeaStatus $from, IdeaStatus $to, ?string $note, User $by, CarbonInterface $at): void
    {
        StatusChange::create([
            'idea_id' => $idea->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'changed_by' => $by->id,
            'changed_at' => $at,
        ]);
    }

    private function noteFor(IdeaStatus $to): ?string
    {
        return match ($to) {
            IdeaStatus::Approved => 'Fits our priorities for this quarter.',
            IdeaStatus::Rejected => 'Not feasible with the current budget and space constraints.',
            IdeaStatus::Implemented => 'Rolled out to all teams and the old process was retired.',
            default => null,
        };
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function seedVotes(Idea $idea, $users, int $offset, int $target, CarbonInterface $submittedAt): void
    {
        $voters = $users->reject(fn (User $user): bool => $user->id === $idea->user_id)->values();
        $count = min($target, $voters->count());

        for ($i = 0; $i < $count; $i++) {
            $voter = $voters[($offset + $i) % $voters->count()];

            $vote = Vote::create(['idea_id' => $idea->id, 'user_id' => $voter->id]);
            $vote->forceFill(['created_at' => $submittedAt->copy()->addHours($i + 1)->min(now())])->save();
        }

        $idea->forceFill(['votes_count' => $idea->votes()->count()])->save();
    }

    /**
     * @param  array<string, Idea>  $ideasByTitle
     * @param  Collection<int, User>  $users
     */
    private function seedComments(array $ideasByTitle, $users, User $moderator): void
    {
        foreach ($this->comments() as [$title, $email, $body, $hoursAfter, $isHidden]) {
            $idea = $ideasByTitle[$title];
            $createdAt = $hoursAfter === null ? now() : $idea->submitted_at->copy()->addHours($hoursAfter)->min(now()->subHour());

            $comment = Comment::create([
                'idea_id' => $idea->id,
                'user_id' => $users->firstWhere('email', $email)->id,
                'body' => $body,
                'is_hidden' => $isHidden,
                'hidden_by' => $isHidden ? $moderator->id : null,
                'hidden_at' => $isHidden ? $createdAt->copy()->addHour() : null,
            ]);

            $comment->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        $this->seedAnonymousAuthorComment($ideasByTitle, $users);
    }

    /**
     * The anonymous author replies on their own idea, which must stay anonymous too (BR-7).
     *
     * @param  array<string, Idea>  $ideasByTitle
     * @param  Collection<int, User>  $users
     */
    private function seedAnonymousAuthorComment(array $ideasByTitle, $users): void
    {
        $idea = $ideasByTitle['Reduce the number of recurring meetings'];

        $comment = Comment::create([
            'idea_id' => $idea->id,
            'user_id' => $idea->user_id,
            'body' => 'Thanks for the feedback, I will add numbers soon.',
        ]);

        $at = $idea->submitted_at->copy()->addHours(30)->min(now()->subHour());
        $comment->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }
}
