<?php

namespace App\Livewire\Account;

use App\Domain\Audit\SecurityAlerts;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Channel;
use App\Domain\Notifications\NotificationCategory;
use App\Domain\Notifications\TestNotification;
use App\Domain\Settings\NotificationSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts::app')]
#[Title('Notifications')]
class Notifications extends Component
{
    /** @var array<string, array<int, string>> category => channels */
    public array $preferences = [];

    public function mount(): void
    {
        foreach ($this->categories() as $category) {
            $this->preferences[$category->value] = array_key_exists($category->value, $this->user()->notification_preferences ?? [])
                ? array_values((array) $this->user()->notification_preferences[$category->value])
                : array_map(fn (Channel $c) => $c->value, $category->defaultChannels());
        }
    }

    public function save(): void
    {
        $channels = array_map(fn (Channel $c) => $c->value, Channel::cases());

        $this->validate([
            'preferences' => ['array'],
            'preferences.*' => ['array'],
            'preferences.*.*' => [Rule::in($channels)],
        ]);

        $available = array_map(fn (Channel $c) => $c->value, $this->user()->availableChannels());
        $security = NotificationCategory::Security->value;

        if ($available !== [] && array_intersect($this->preferences[$security] ?? [], $available) === []) {
            throw ValidationException::withMessages([
                "preferences.{$security}" => __('Keep at least one way to receive security alerts about your account.'),
            ]);
        }

        $allowed = array_map(fn (NotificationCategory $c) => $c->value, $this->categories());

        $this->user()->forceFill([
            'notification_preferences' => array_intersect_key($this->preferences, array_flip($allowed)),
        ])->save();

        session()->flash('status', __('Notification settings saved.'));
    }

    public function sendTest(): void
    {
        try {
            $this->user()->notifyNow(new TestNotification);
            session()->flash('status', __('Test sent to: in-app:channels.', [
                'channels' => collect($this->user()->availableChannels())->map(fn (Channel $c) => ', '.$c->label())->implode(''),
            ]));
        } catch (Throwable $e) {
            report($e);
            session()->flash('error', __('Some channels could not be reached: :reason', ['reason' => $e->getMessage()]));
        }
    }

    #[On('push-subscribed')]
    public function refreshDevices(): void
    {
        // Re-render so "Browser" becomes available.
    }

    public function render(NotificationSettings $settings): View
    {
        return view('livewire.account.notifications', [
            'categories' => $this->categories(),
            'channels' => Channel::cases(),
            'available' => $this->user()->availableChannels(),
            'user' => $this->user(),
            'webpushReady' => $settings->webpushReady(),
            'whatsappReady' => $settings->whatsappReady(),
            'vapidPublicKey' => $settings->vapid_public_key,
            'pushDevices' => $this->user()->pushSubscriptions()->count(),
        ]);
    }

    /**
     * @return array<int, NotificationCategory>
     */
    private function categories(): array
    {
        return array_values(array_filter(NotificationCategory::cases(), fn (NotificationCategory $c) => $c !== NotificationCategory::SecurityTeam
            || $this->user()->hasAnyRole(SecurityAlerts::TEAM_ROLES)));
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
