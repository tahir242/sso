<?php

namespace App\Domain\Notifications;

use App\Domain\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Base for every SSO notification: one title/body/link, delivered in-app
 * plus whichever channels the recipient chose for its category (and can
 * actually receive). Subclasses may override toMail() for richer emails.
 */
abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function category(): NotificationCategory;

    abstract public function title(User $notifiable): string;

    abstract public function body(User $notifiable): string;

    abstract public function url(User $notifiable): string;

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return [
            'database',
            ...array_map(fn (Channel $channel) => $channel->driver(), $notifiable->notificationChannels($this->category())),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title($notifiable))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($this->body($notifiable))
            ->action(__('Open'), $this->url($notifiable));
    }

    public function toWhatsApp(User $notifiable): string
    {
        return '*'.$this->title($notifiable)."*\n".$this->body($notifiable)."\n".$this->url($notifiable);
    }

    public function toWebPush(User $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title($notifiable))
            ->body($this->body($notifiable))
            ->icon('/favicon.ico')
            ->data(['url' => $this->url($notifiable)])
            ->options(['TTL' => 3600]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => $this->category()->value,
            'title' => $this->title($notifiable),
            'body' => $this->body($notifiable),
            'url' => $this->url($notifiable),
        ];
    }
}
