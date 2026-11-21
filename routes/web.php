<?php

use App\Http\Controllers\Account\PushSubscriptionController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BrandingLogoController;
use App\Http\Controllers\EntryPointController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\MediaController;
use App\Http\Middleware\EnsureEmailAddress;
use App\Livewire\Access\Approvals;
use App\Livewire\Access\MyRequests;
use App\Livewire\Access\NewStaffApprovals;
use App\Livewire\Access\RequestAccess;
use App\Livewire\Access\Reviews;
use App\Livewire\Account\EmailAddress;
use App\Livewire\Account\Notifications;
use App\Livewire\Account\Security;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\StepUpChallenge;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Auth\TwoFactorSetup;
use App\Livewire\Docs\IntegrationGuide;
use App\Livewire\MyApplications;
use App\Livewire\Requests\ChangeRequestForm;
use App\Livewire\Requests\NewRequest;
use App\Livewire\Requests\NewStaffForm;
use App\Livewire\Support\MyTickets;
use App\Livewire\Support\NewTicket;
use App\Livewire\Support\TicketShow;
use Illuminate\Support\Facades\Route;

// Integration guides for developers and the IT team (same audience as the API reference):
// connecting in-house applications, and third-party software such as Moodle or Koha.
Route::livewire('/docs/integration/{page?}', IntegrationGuide::class)
    ->middleware(['auth', 'can:viewApiDocs'])
    ->name('docs.integration');

// No public homepage: the sign-in screen is the entry point.
Route::get('/', EntryPointController::class)->name('entry');

// Help & FAQ: linked from the sign-in screen, so public (signed in or not).
Route::get('/help', HelpController::class)->name('help');

// Application logo from Settings → General (shown on the sign-in screen, so public).
Route::get('/branding/logo/{version}', BrandingLogoController::class)
    ->where('version', '[a-f0-9]{1,40}')
    ->name('branding.logo');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/login/two-factor', TwoFactorChallenge::class)->name('login.two-factor');
    Route::livewire('/login/two-factor/setup', TwoFactorSetup::class)->name('login.two-factor.setup');
});

Route::middleware(['auth', EnsureEmailAddress::class])->group(function () {
    Route::livewire('/account/email', EmailAddress::class)->name('account.email');
    Route::livewire('/home', MyApplications::class)->name('home');
    Route::livewire('/requests/new', NewRequest::class)->name('requests.new');
    Route::livewire('/suggest', ChangeRequestForm::class)->name('suggestions.new');
    Route::livewire('/requests/new-staff', NewStaffForm::class)->name('requests.new-staff');
    // Helpdesk and suggestion attachments, after the owning record's permission check.
    Route::get('/media/{uuid}/{conversion?}', MediaController::class)->name('media.show');
    Route::livewire('/verify', StepUpChallenge::class)->name('verify');
    Route::livewire('/account/security', Security::class)->name('account.security');
    Route::livewire('/account/notifications', Notifications::class)->name('account.notifications');
    Route::post('/account/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('account.push.store');
    Route::delete('/account/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('account.push.destroy');
    Route::livewire('/access/request', RequestAccess::class)->name('access.request');
    Route::livewire('/access/requests', MyRequests::class)->name('access.requests');
    Route::livewire('/approvals', Approvals::class)->name('approvals');
    Route::livewire('/approvals/reviews', Reviews::class)->name('approvals.reviews');
    Route::livewire('/approvals/new-staff', NewStaffApprovals::class)->name('approvals.new-staff');
    Route::livewire('/support', MyTickets::class)->name('support');
    Route::livewire('/support/new', NewTicket::class)->name('support.new');
    Route::livewire('/support/{ulid}', TicketShow::class)->name('support.show');
    Route::post('/logout', LogoutController::class)->name('logout');
});
