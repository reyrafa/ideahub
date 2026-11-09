<?php

namespace App\Notifications;

use App\Enums\IdeaStatus;
use App\Filament\Resources\Ideas\IdeaResource;
use App\Models\Idea;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * F8: tells an idea's author that its status changed.
 *
 * BR-7: the mail never names the author of an anonymous idea and never names the reviewer.
 */
class IdeaStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Idea $idea,
        public IdeaStatus $from,
        public IdeaStatus $to,
        public ?string $note = null,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $greeting = $this->idea->is_anonymous || ! $notifiable instanceof User
            ? 'Hello,'
            : "Hello {$notifiable->name},";

        $message = (new MailMessage)
            ->subject("Your idea is now {$this->to->getLabel()}")
            ->greeting($greeting)
            ->line("The status of your idea \"{$this->idea->title}\" changed from {$this->from->getLabel()} to {$this->to->getLabel()}.");

        if ($this->note !== null && $this->note !== '') {
            $message->line('Reviewer note:')->line($this->note);
        }

        return $message->action('View your idea', IdeaResource::getUrl('view', ['record' => $this->idea]));
    }
}
