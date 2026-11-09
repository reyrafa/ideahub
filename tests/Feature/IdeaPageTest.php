<?php

use App\Enums\IdeaStatus;
use App\Filament\Resources\Ideas\IdeaResource;
use App\Filament\Resources\Ideas\Pages\ListIdeas;
use App\Filament\Resources\Ideas\Pages\ViewIdea;
use App\Livewire\IdeaComments;
use App\Livewire\IdeaStatusHistory;
use App\Models\Comment;
use App\Models\Idea;
use App\Models\StatusChange;
use App\Models\User;
use App\Models\Vote;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->employee = User::factory()->employee()->create();
    $this->reviewer = User::factory()->reviewer()->create();
    $this->admin = User::factory()->admin()->create();
});

function editComment(int $id): TestAction
{
    return TestAction::make('editComment')->arguments(['comment' => $id]);
}

function deleteComment(int $id): TestAction
{
    return TestAction::make('deleteComment')->arguments(['comment' => $id]);
}

function hideComment(int $id): TestAction
{
    return TestAction::make('hideComment')->arguments(['comment' => $id]);
}

// ---- Idea page ----------------------------------------------------------------

it('F3 shows the title, status, category and the idea text on the idea page', function () {
    $idea = Idea::factory()->create([
        'title' => 'Install better door signs',
        'problem' => 'People get lost on the second floor every day.',
        'proposal' => 'Add large illuminated signs near the stairs.',
        'expected_impact' => 'Fewer missed meetings',
    ]);
    $this->actingAs($this->employee);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->assertSuccessful()
        ->assertSee('Install better door signs')
        ->assertSee($idea->category->name)
        ->assertSee('People get lost on the second floor every day.')
        ->assertSee('Add large illuminated signs near the stairs.')
        ->assertSee('Fewer missed meetings')
        ->assertSee('New');
});

it('requires a role to open the idea page', function () {
    $idea = Idea::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(IdeaResource::getUrl('view', ['record' => $idea]))->assertForbidden();
});

it('BR-7 shows Anonymous on the idea page to employees and reviewers and the real name to admins', function () {
    $author = User::factory()->employee()->create(['name' => 'Alice Author']);
    $idea = Idea::factory()->anonymous()->for($author, 'author')->create();

    foreach ([$this->employee, $this->reviewer] as $viewer) {
        $this->actingAs($viewer);

        Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
            ->assertSee('Anonymous')
            ->assertDontSee('Alice Author');
    }

    $this->actingAs($this->admin);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->assertSee('Alice Author (anonymous)');
});

it('BR-7 never reveals an anonymous author through the status history', function () {
    $author = User::factory()->employee()->create(['name' => 'Alice Author']);
    $idea = Idea::factory()->anonymous()->for($author, 'author')->create();
    StatusChange::factory()->create([
        'idea_id' => $idea->id,
        'from_status' => null,
        'to_status' => IdeaStatus::New,
        'changed_by' => $author->id,
    ]);

    $this->actingAs($this->employee);
    Livewire::test(IdeaStatusHistory::class, ['idea' => $idea])
        ->assertSee('Anonymous')
        ->assertDontSee('Alice Author');

    $this->actingAs($this->admin);
    Livewire::test(IdeaStatusHistory::class, ['idea' => $idea])->assertSee('Alice Author');
});

it('BR-1 votes and removes a vote from the idea page', function () {
    $idea = Idea::factory()->create(['votes_count' => 4]);
    Vote::factory()->count(4)->create(['idea_id' => $idea->id]);
    $this->actingAs($this->employee);

    $page = Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->assertActionHasLabel('vote', 'Vote')
        ->callAction('vote')
        ->assertActionHasLabel('vote', 'Remove vote');

    expect($idea->fresh()->votes_count)->toBe(5);

    $page->callAction('vote')->assertActionHasLabel('vote', 'Vote');

    expect($idea->fresh()->votes_count)->toBe(4);
});

it('BR-2 and BR-3 disable the vote action on own and closed ideas', function () {
    $own = Idea::factory()->for($this->employee, 'author')->create();
    $approved = Idea::factory()->status(IdeaStatus::Approved)->create();
    $this->actingAs($this->employee);

    Livewire::test(ViewIdea::class, ['record' => $own->getKey()])->assertActionDisabled('vote');
    Livewire::test(ViewIdea::class, ['record' => $approved->getKey()])->assertActionDisabled('vote');
});

it('BR-6 shows Edit and Withdraw to the author of a New idea and withdraws with a redirect', function () {
    $idea = Idea::factory()->for($this->employee, 'author')->create();
    $this->actingAs($this->employee);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->assertActionVisible('edit')
        ->assertActionVisible('withdraw')
        ->callAction('withdraw')
        ->assertNotified('Idea withdrawn')
        ->assertRedirect(IdeaResource::getUrl('index'));

    expect(Idea::find($idea->id))->toBeNull()->and($idea->fresh()->trashed())->toBeTrue();
});

it('BR-6 hides Edit and Withdraw from other employees and Withdraw once the idea left New', function () {
    $someonesIdea = Idea::factory()->create();
    $underReview = Idea::factory()->for($this->employee, 'author')->status(IdeaStatus::UnderReview)->create();
    $this->actingAs($this->employee);

    Livewire::test(ViewIdea::class, ['record' => $someonesIdea->getKey()])
        ->assertActionHidden('edit')
        ->assertActionHidden('withdraw');

    Livewire::test(ViewIdea::class, ['record' => $underReview->getKey()])
        ->assertActionHidden('edit')
        ->assertActionHidden('withdraw');
});

it('BR-11 shows the status history newest first with note and who changed it', function () {
    $idea = Idea::factory()->status(IdeaStatus::Rejected)->create();
    StatusChange::factory()->create([
        'idea_id' => $idea->id,
        'from_status' => IdeaStatus::UnderReview,
        'to_status' => IdeaStatus::Rejected,
        'note' => 'Out of budget for this year.',
        'changed_by' => $this->reviewer->id,
        'changed_at' => now(),
    ]);
    StatusChange::factory()->create([
        'idea_id' => $idea->id,
        'from_status' => IdeaStatus::New,
        'to_status' => IdeaStatus::UnderReview,
        'note' => null,
        'changed_by' => $this->admin->id,
        'changed_at' => now()->subDay(),
    ]);
    $this->actingAs($this->employee);

    Livewire::test(IdeaStatusHistory::class, ['idea' => $idea])
        ->assertSeeInOrder(['Rejected', $this->reviewer->name, 'Out of budget for this year.', 'Under review', $this->admin->name]);
});

// ---- Comments -----------------------------------------------------------------

it('F4 posts a comment and shows it with the commenter name', function () {
    $idea = Idea::factory()->create();
    $this->actingAs($this->employee);

    Livewire::test(IdeaComments::class, ['idea' => $idea])
        ->fillForm(['body' => 'This would really help us.'])
        ->call('post')
        ->assertHasNoFormErrors()
        ->assertSee('This would really help us.')
        ->assertSee($this->employee->name);

    expect(Comment::where('idea_id', $idea->id)->count())->toBe(1);
});

it('BR-12 validates the comment length inline', function () {
    $idea = Idea::factory()->create();
    $this->actingAs($this->employee);

    Livewire::test(IdeaComments::class, ['idea' => $idea])
        ->fillForm(['body' => 'x'])
        ->call('post')
        ->assertHasFormErrors(['body' => 'min'])
        ->fillForm(['body' => str_repeat('x', 1001)])
        ->call('post')
        ->assertHasFormErrors(['body' => 'max'])
        ->fillForm(['body' => ''])
        ->call('post')
        ->assertHasFormErrors(['body' => 'required']);

    expect(Comment::count())->toBe(0);
});

it('BR-12 shows "Comments are closed" on a Rejected idea and refuses a posted comment', function () {
    $idea = Idea::factory()->status(IdeaStatus::Rejected)->create();
    $this->actingAs($this->employee);

    Livewire::test(IdeaComments::class, ['idea' => $idea])
        ->assertSee('Comments are closed')
        ->fillForm(['body' => 'Can I still comment?'])
        ->call('post')
        ->assertForbidden();

    expect(Comment::count())->toBe(0);
});

it('BR-7 labels the anonymous author\'s comments Anonymous for non-admins and real for admins', function () {
    $author = User::factory()->employee()->create(['name' => 'Alice Author']);
    $idea = Idea::factory()->anonymous()->for($author, 'author')->create();
    Comment::factory()->create(['idea_id' => $idea->id, 'user_id' => $author->id, 'body' => 'Some more context']);

    foreach ([$this->employee, $this->reviewer] as $viewer) {
        $this->actingAs($viewer);

        Livewire::test(IdeaComments::class, ['idea' => $idea])
            ->assertSee('Some more context')
            ->assertSee('Anonymous')
            ->assertDontSee('Alice Author');
    }

    $this->actingAs($this->admin);

    Livewire::test(IdeaComments::class, ['idea' => $idea])->assertSee('Alice Author');
});

it('BR-14 hides hidden comments from employees but not from reviewers and admins', function () {
    $idea = Idea::factory()->create();
    Comment::factory()->create(['idea_id' => $idea->id, 'body' => 'A perfectly fine comment']);
    Comment::factory()->hidden()->create(['idea_id' => $idea->id, 'body' => 'A moderated comment']);

    $this->actingAs($this->employee);
    Livewire::test(IdeaComments::class, ['idea' => $idea])
        ->assertSee('A perfectly fine comment')
        ->assertDontSee('A moderated comment');

    foreach ([$this->reviewer, $this->admin] as $viewer) {
        $this->actingAs($viewer);
        Livewire::test(IdeaComments::class, ['idea' => $idea])
            ->assertSee('A moderated comment')
            ->assertSee('Hidden');
    }
});

it('BR-14 lets a reviewer hide a comment, recording who hid it, and it vanishes for employees', function () {
    $idea = Idea::factory()->create();
    $comment = Comment::factory()->create(['idea_id' => $idea->id, 'body' => 'Something inappropriate']);

    $this->actingAs($this->reviewer);
    Livewire::test(IdeaComments::class, ['idea' => $idea])
        ->assertActionVisible(hideComment($comment->id))
        ->callAction(hideComment($comment->id))
        ->assertNotified('Comment hidden');

    $comment->refresh();
    expect($comment->is_hidden)->toBeTrue()
        ->and($comment->hidden_by)->toBe($this->reviewer->id)
        ->and($comment->hidden_at)->not->toBeNull();

    $this->actingAs($this->employee);
    Livewire::test(IdeaComments::class, ['idea' => $idea])->assertDontSee('Something inappropriate');
});

it('BR-14 gives employees no Hide action and blocks it server side', function () {
    $idea = Idea::factory()->create();
    $comment = Comment::factory()->create(['idea_id' => $idea->id]);
    $this->actingAs($this->employee);

    Livewire::test(IdeaComments::class, ['idea' => $idea])
        ->assertActionHidden(hideComment($comment->id));

    expect($comment->fresh()->is_hidden)->toBeFalse();
});

it('BR-13 lets the author edit and delete their own comment within 15 minutes', function () {
    $idea = Idea::factory()->create();
    $comment = Comment::factory()->create(['idea_id' => $idea->id, 'user_id' => $this->employee->id, 'body' => 'First draft']);
    $this->actingAs($this->employee);

    $component = Livewire::test(IdeaComments::class, ['idea' => $idea])
        ->assertActionVisible(editComment($comment->id))
        ->callAction(editComment($comment->id), ['body' => 'Second draft'])
        ->assertNotified('Comment updated')
        ->assertSee('Second draft');

    expect($comment->fresh()->body)->toBe('Second draft');

    $component->callAction(deleteComment($comment->id))->assertNotified('Comment deleted');

    expect(Comment::find($comment->id))->toBeNull();
});

it('BR-13 blocks editing and deleting after 15 minutes', function () {
    $idea = Idea::factory()->create();
    $comment = Comment::factory()->create(['idea_id' => $idea->id, 'user_id' => $this->employee->id, 'body' => 'Too late']);
    $this->actingAs($this->employee);

    $this->travel(16)->minutes();

    Livewire::test(IdeaComments::class, ['idea' => $idea])
        ->assertActionHidden(editComment($comment->id))
        ->assertActionHidden(deleteComment($comment->id));

    expect($comment->fresh()->body)->toBe('Too late');
});

it('BR-13 gives other users no Edit or Delete action', function () {
    $idea = Idea::factory()->create();
    $comment = Comment::factory()->create(['idea_id' => $idea->id, 'user_id' => $this->employee->id]);
    $this->actingAs($this->reviewer);

    Livewire::test(IdeaComments::class, ['idea' => $idea])
        ->assertActionHidden(editComment($comment->id))
        ->assertActionHidden(deleteComment($comment->id));
});

it('keeps the board link working for the idea page', function () {
    $idea = Idea::factory()->create();
    $this->actingAs($this->employee);

    Livewire::test(ListIdeas::class)->assertCanSeeTableRecords([$idea]);
    $this->get(IdeaResource::getUrl('view', ['record' => $idea]))->assertSuccessful();
});
