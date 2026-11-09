<?php

namespace App\Filament\Resources\Ideas\Pages;

use App\Actions\SubmitIdea;
use App\Filament\Resources\Ideas\IdeaResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateIdea extends CreateRecord
{
    protected static string $resource = IdeaResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(SubmitIdea::class)->handle($user, $data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Idea submitted';
    }
}
