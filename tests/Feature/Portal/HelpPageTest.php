<?php

use App\Domain\Identity\Models\User;
use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\SecuritySettings;
use App\Domain\Settings\SessionSettings;

it('links the sign-in screen to the help page', function () {
    $this->get(route('login'))->assertOk()->assertSee(route('help'), false)->assertSee('Help &amp; FAQ', false);
});

it('is public and covers signing in, passwords, two-step verification and access', function () {
    $this->get(route('help'))
        ->assertOk()
        ->assertSee('Back to sign in')
        ->assertSee('Which username and password do I use?')
        ->assertSee('I forgot my password. How do I reset it?')
        ->assertSee('My account is locked. What now?')
        ->assertSee('I lost or changed my phone. How do I sign in?')
        ->assertSee('How do I get access?', false)
        ->assertSee('A new colleague needs an account.');
});

it('quotes the current settings and helpdesk contacts', function () {
    $security = app(SecuritySettings::class);
    $security->lockout_max_attempts = 7;
    $security->lockout_decay_minutes = 20;
    $security->save();

    $session = app(SessionSettings::class);
    $session->idle_timeout_minutes = 45;
    $session->save();

    $general = app(GeneralSettings::class);
    $general->support_phone = 'Ext. 4321';
    $general->support_email = 'helpdesk@example.test';
    $general->save();

    $this->get(route('help'))
        ->assertSee('After <strong>7 wrong passwords</strong>', false)
        ->assertSee('<strong>20 minutes</strong>', false)
        ->assertSee('45 minutes without activity')
        ->assertSee('Ext. 4321')
        ->assertSee('mailto:helpdesk@example.test', false);
});

it('sends signed-in staff back to their applications and offers a help ticket', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('help'))
        ->assertOk()
        ->assertSee('Back to my applications')
        ->assertSee(route('support.new'), false);
});
