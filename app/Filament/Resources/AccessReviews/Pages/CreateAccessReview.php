<?php

namespace App\Filament\Resources\AccessReviews\Pages;

use App\Domain\Access\Actions\StartAccessReview;
use App\Domain\Federation\Models\Application;
use App\Filament\Resources\AccessReviews\AccessReviewResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAccessReview extends CreateRecord
{
    protected static string $resource = AccessReviewResource::class;

    protected static ?string $title = 'Start access review';

    protected function handleRecordCreation(array $data): Model
    {
        return app(StartAccessReview::class)->handle(
            $data['name'],
            isset($data['application_id']) ? Application::find($data['application_id']) : null,
            now()->parse($data['due_at']),
            (bool) ($data['auto_revoke'] ?? false),
            auth()->user(),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
