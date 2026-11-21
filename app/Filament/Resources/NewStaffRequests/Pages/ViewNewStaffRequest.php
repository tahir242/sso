<?php

namespace App\Filament\Resources\NewStaffRequests\Pages;

use App\Filament\Resources\NewStaffRequests\NewStaffRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewNewStaffRequest extends ViewRecord
{
    protected static string $resource = NewStaffRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [NewStaffRequestResource::completeAction()];
    }
}
