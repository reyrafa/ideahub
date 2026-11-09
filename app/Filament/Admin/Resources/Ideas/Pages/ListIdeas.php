<?php

namespace App\Filament\Admin\Resources\Ideas\Pages;

use App\Actions\ExportIdeas;
use App\Filament\Admin\Resources\Ideas\IdeaResource;
use App\Models\Idea;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListIdeas extends ListRecords
{
    protected static string $resource = IdeaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => $this->user()->can('export', Idea::class))
                ->action(fn (): StreamedResponse => app(ExportIdeas::class)->handle($this->user())),
        ];
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
