<?php

namespace App\Domain\Access\Notifications;

use App\Domain\Access\Models\ApprovalStep;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\BaseNotification;
use App\Domain\Notifications\NotificationCategory;
use Illuminate\Notifications\Messages\MailMessage;

class ApprovalNeeded extends BaseNotification
{
    public function __construct(public readonly ApprovalStep $step) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::Access;
    }

    public function title(User $notifiable): string
    {
        return __('Approval needed: :name → :app', [
            'name' => $this->step->request->user->name,
            'app' => $this->step->request->application->name,
        ]);
    }

    public function body(User $notifiable): string
    {
        return __(':name asked for access to :app. Please decide by :due.', [
            'name' => $this->step->request->user->name,
            'app' => $this->step->request->application->name,
            'due' => $this->step->due_at?->format('D j M, H:i'),
        ]);
    }

    public function url(User $notifiable): string
    {
        return route('approvals');
    }

    public function toMail(User $notifiable): MailMessage
    {
        $request = $this->step->request;

        return (new MailMessage)
            ->subject($this->title($notifiable))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':name has asked for access to :app.', ['name' => $request->user->name, 'app' => $request->application->name]))
            ->line(__('Reason: :reason', ['reason' => $request->justification]))
            ->line(__('You are asked to approve as :role. Please decide by :due.', [
                'role' => mb_strtolower($this->step->type->getLabel()),
                'due' => $this->step->due_at?->format('D j M, H:i'),
            ]))
            ->action(__('Review request'), $this->url($notifiable));
    }
}
