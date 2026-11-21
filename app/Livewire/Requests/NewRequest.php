<?php

namespace App\Livewire\Requests;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "What do you need?" — the single starting point for anything a member of
 * staff asks IT for, in plain words: access, help with a problem, an idea.
 */
#[Layout('layouts::app')]
#[Title('New request')]
class NewRequest extends Component
{
    public function render(): View
    {
        return view('livewire.requests.new-request', [
            'choices' => [
                [
                    'url' => route('support.new'),
                    'icon' => 'heroicon-o-lifebuoy',
                    'tone' => 'rose',
                    'title' => 'Something isn’t working',
                    'text' => 'An error, you can’t sign in, a screen is slow or shows wrong data, a printer or computer problem — or a question for IT.',
                    'who' => 'The IT helpdesk replies, usually the same working day.',
                ],
                [
                    'url' => route('access.request'),
                    'icon' => 'heroicon-o-key',
                    'tone' => 'primary',
                    'title' => 'I need access to an application',
                    'text' => 'You (or someone in your team) need to use an application that is not in My applications yet.',
                    'who' => 'Your head of department and the application owner approve it.',
                ],
                [
                    'url' => route('suggestions.new'),
                    'icon' => 'heroicon-o-light-bulb',
                    'tone' => 'amber',
                    'title' => 'I have an idea to improve an application',
                    'text' => 'A new report or field, a change to how a screen works, something that would save you time.',
                    'who' => 'Goes to the team that builds the application.',
                ],
                [
                    'url' => route('requests.new-staff'),
                    'icon' => 'heroicon-o-user-plus',
                    'tone' => 'emerald',
                    'title' => 'A new colleague needs an account',
                    'text' => 'Someone joined your department and needs a username for the hospital systems.',
                    'who' => 'The head of department confirms, then IT creates the account.',
                ],
            ],
        ]);
    }
}
