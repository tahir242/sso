<?php

namespace App\Filament\Resources\AccessReviews\Pages;

use App\Filament\Resources\AccessReviews\AccessReviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccessReviews extends ListRecords
{
    protected static string $resource = AccessReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Start access review')];
    }
}
