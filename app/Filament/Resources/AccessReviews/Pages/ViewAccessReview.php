<?php

namespace App\Filament\Resources\AccessReviews\Pages;

use App\Domain\Access\Actions\CloseAccessReview;
use App\Domain\Access\Models\AccessReviewCampaign;
use App\Filament\Resources\AccessReviews\AccessReviewResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property AccessReviewCampaign $record
 */
class ViewAccessReview extends ViewRecord
{
    protected static string $resource = AccessReviewResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => $this->exportCsv()),
            Action::make('close')
                ->label('Close review')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('danger')
                ->visible(fn () => $this->record->isOpen() && auth()->user()?->hasRole('super-admin'))
                ->requiresConfirmation()
                ->modalDescription(fn () => $this->record->auto_revoke
                    ? 'Access that nobody confirmed will be removed now.'
                    : 'Access that nobody confirmed will be kept and reported as “not reviewed in time”.')
                ->action(function (CloseAccessReview $close): void {
                    $close->handle($this->record, auth()->user());
                    Notification::make()->title('Review closed')->success()->send();
                    $this->refreshFormData(['status', 'closed_at']);
                }),
        ];
    }

    /**
     * Evidence for auditors: one row per person and application.
     */
    private function exportCsv(): StreamedResponse
    {
        $campaign = $this->record;
        $filename = 'access-review-'.str($campaign->name)->slug().'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($campaign) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Application', 'Username', 'Name', 'Employee ID', 'Roles', 'Access since', 'Last used', 'Reviewer', 'Decision', 'Decided by', 'Decided at', 'Comment']);

            $campaign->items()->with(['application', 'user', 'reviewer', 'decider'])->orderBy('application_id')->chunk(500, function ($items) use ($out) {
                foreach ($items as $item) {
                    fputcsv($out, [
                        $item->application->name,
                        $item->user->username,
                        $item->user->name,
                        $item->user->emp_id,
                        implode(', ', $item->roles_snapshot ?? []),
                        $item->access_since?->format('Y-m-d'),
                        $item->last_used_at?->format('Y-m-d') ?? 'never',
                        $item->reviewer?->name ?? 'Administrators',
                        $item->decision->getLabel(),
                        $item->decider?->name ?? ($item->decided_at ? 'System' : ''),
                        $item->decided_at?->format('Y-m-d H:i'),
                        $item->comment,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
