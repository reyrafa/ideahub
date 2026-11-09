<?php

namespace App\Filament\Resources\Ideas\Pages;

use App\Actions\UpdateIdea;
use App\Filament\Resources\Ideas\IdeaResource;
use App\Models\Idea;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditIdea extends EditRecord
{
    protected static string $resource = IdeaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        /** @var Idea $record */
        return app(UpdateIdea::class)->handle($user, $record, $data);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Idea updated';
    }
}
