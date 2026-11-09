<?php

namespace App\Filament\Admin\Resources\Ideas\Pages;

use App\Filament\Admin\Resources\Ideas\IdeaResource;
use Filament\Resources\Pages\ListRecords;

class ListIdeas extends ListRecords
{
    protected static string $resource = IdeaResource::class;
}
