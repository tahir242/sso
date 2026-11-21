<?php

namespace App\Filament\Resources\NewStaffRequests\Pages;

use App\Filament\Resources\NewStaffRequests\NewStaffRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListNewStaffRequests extends ListRecords
{
    protected static string $resource = NewStaffRequestResource::class;
}
