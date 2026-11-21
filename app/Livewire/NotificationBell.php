<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * In-app notifications (approvals needed, decisions) in the portal header.
 * Polls every minute.
 */
class NotificationBell extends Component
{
    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('home'));
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
            // notifications() is already newest first (SQL Server rejects ordering by a column twice).
            'items' => $user->notifications()->limit(8)->get(),
        ]);
    }
}
