<?php

namespace App\Support\Help;

use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\MfaSettings;
use App\Domain\Settings\SecuritySettings;
use App\Domain\Settings\SessionSettings;

/**
 * Questions and answers for the public help page (/help).
 *
 * Numbers (lockout, time-outs, password length) and the helpdesk contacts come
 * from the admin settings, so the answers stay true when an administrator
 * changes them. Answers are trusted HTML written here; settings values are
 * escaped.
 */
final readonly class Faq
{
    public function __construct(
        private GeneralSettings $general,
        private SecuritySettings $security,
        private SessionSettings $session,
        private MfaSettings $mfa,
    ) {}

    /**
     * @return list<array{id: string, title: string, icon: string, items: list<array{id: string, q: string, a: string}>}>
     */
    public function sections(): array
    {
        $helpdesk = $this->helpdesk();
        $attempts = $this->security->lockout_max_attempts;
        $lockMinutes = $this->minutes($this->security->lockout_decay_minutes);
        $idle = $this->minutes($this->session->idle_timeout_minutes);
        $hours = $this->session->absolute_timeout_hours;
        $trustDays = $this->mfa->trusted_device_days;
        $minLength = $this->security->password_min_length;
        $host = e((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $keys = '<kbd>Ctrl</kbd> + <kbd>Alt</kbd> + <kbd>Del</kbd>';

        return [
            [
                'id' => 'signing-in',
                'title' => 'Signing in',
                'icon' => 'heroicon-o-arrow-right-end-on-rectangle',
                'items' => [
                    [
                        'id' => 'which-username',
                        'q' => 'Which username and password do I use?',
                        'a' => '<p>Use the <strong>same username and password you use to log in to a hospital computer</strong> (your Windows account). Instead of the username you can also type your <strong>Employee ID</strong>.</p>'
                            .'<p>A few accounts made by IT for people without a Windows account have their own password; IT gives it to you when the account is created.</p>',
                    ],
                    [
                        'id' => 'one-sign-in',
                        'q' => 'Do I have to sign in to every application separately?',
                        'a' => '<p>No. Once you are signed in here, every application that uses this sign-in opens without asking again. Signing out here also signs you out of those applications.</p>',
                    ],
                    [
                        'id' => 'wrong-password',
                        'q' => 'It says my username or password is incorrect. What should I check?',
                        'a' => '<ul>'
                            .'<li>Is <strong>Caps Lock</strong> on, or is the keyboard set to another language (e.g. Urdu)?</li>'
                            .'<li>Did you <strong>change your Windows password</strong> recently? Use the new one.</li>'
                            .'<li>Try your Employee ID instead of the username.</li>'
                            .'<li>Can you log in to a hospital computer with the same password? If not, the password itself is the problem: contact the IT helpdesk'.$helpdesk.'.</li>'
                            .'</ul>'
                            ."<p>Be careful not to try too many times: after {$attempts} wrong attempts the account is paused (see the next question).</p>",
                    ],
                    [
                        'id' => 'locked',
                        'q' => 'My account is locked. What now?',
                        'a' => "<p>After <strong>{$attempts} wrong passwords</strong> in a row, signing in to that account is paused for <strong>{$lockMinutes}</strong>. This protects your account from someone guessing the password.</p>"
                            ."<p>Wait {$lockMinutes} and try again with the correct password. If it still fails, or you think someone else tried to use your account, contact the IT helpdesk{$helpdesk}.</p>",
                    ],
                    [
                        'id' => 'recent-accounts',
                        'q' => 'Why does the sign-in page show my name, and how do I remove it?',
                        'a' => '<p>The sign-in page remembers the accounts recently used <strong>on this computer</strong>, so you can sign in again with one click. Only your name is remembered, never your password.</p>'
                            .'<p>To sign in as someone else, choose <strong>Other user</strong>. To remove an account, use the <strong>×</strong> next to it or <em>Remove this account from this computer</em>. Always remove your account on shared computers.</p>',
                    ],
                    [
                        'id' => 'email-address',
                        'q' => 'Why am I asked to add my email address?',
                        'a' => '<p>The first time you sign in without an email address on your account, you are asked for one. It is used for notifications such as approved access requests and helpdesk replies.</p>',
                    ],
                    [
                        'id' => 'signed-out',
                        'q' => 'Why was I signed out?',
                        'a' => "<p>For safety you are signed out after <strong>{$idle} without activity</strong>, and in any case <strong>{$hours} hours</strong> after signing in. Just sign in again.</p>"
                            .'<p>On a shared ward computer, always click <strong>Sign out</strong> yourself when you finish.</p>',
                    ],
                ],
            ],
            [
                'id' => 'password',
                'title' => 'Forgotten or changing your password',
                'icon' => 'heroicon-o-key',
                'items' => [
                    [
                        'id' => 'forgot-password',
                        'q' => 'I forgot my password. How do I reset it?',
                        'a' => '<p>Your password here is your <strong>Windows password</strong>, so it is reset the same way: contact the <strong>IT helpdesk</strong>'.$helpdesk.'. After checking who you are, they reset it and tell you what to do next. The new password then works both on hospital computers and here.</p>'
                            .'<p>For security there is no “reset by email” link on this site. Accounts with their own (non-Windows) password are also reset by the IT helpdesk.</p>',
                    ],
                    [
                        'id' => 'change-password',
                        'q' => 'How do I change my password?',
                        'a' => "<p><strong>Windows account</strong> (most staff): on a hospital computer press {$keys} and choose <strong>Change a password</strong>. The new password works here straight away.</p>"
                            ."<p><strong>Account with its own password</strong>: sign in, open <strong>My account → Security</strong> and use <em>Change password</em>. It must be at least {$minLength} characters.</p>",
                    ],
                    [
                        'id' => 'password-safety',
                        'q' => 'Will IT ever ask me for my password?',
                        'a' => '<p><strong>No.</strong> Nobody from IT will ask for your password, by phone, email or WhatsApp. Never share it, and never sign in on a page whose address is not <strong>'.$host.'</strong> (check the address bar).</p>',
                    ],
                ],
            ],
            [
                'id' => 'two-step',
                'title' => 'Two-step verification',
                'icon' => 'heroicon-o-device-phone-mobile',
                'items' => [
                    [
                        'id' => 'what-is-two-step',
                        'q' => 'What is two-step verification, and do I need it?',
                        'a' => '<p>After your password you also enter a <strong>6-digit code</strong> from an authenticator app on your phone (Microsoft Authenticator or Google Authenticator). Someone who learns your password still cannot sign in without your phone.</p>'
                            .'<p>It is <strong>required for administrators</strong> and <strong>when you sign in from outside the hospital network</strong> (for example from home). Everyone else can turn it on under <strong>My account → Security</strong>.</p>',
                    ],
                    [
                        'id' => 'set-up-two-step',
                        'q' => 'How do I set it up?',
                        'a' => '<ol>'
                            .'<li>Install Microsoft Authenticator or Google Authenticator on your phone.</li>'
                            .'<li>When asked (or under <strong>My account → Security</strong>), scan the QR code with the app.</li>'
                            .'<li>Type the 6-digit code the app shows.</li>'
                            .'<li><strong>Save the recovery codes</strong> you are shown (print them or keep them somewhere safe). They let you in if you lose your phone.</li>'
                            .'</ol>',
                    ],
                    [
                        'id' => 'lost-phone',
                        'q' => 'I lost or changed my phone. How do I sign in?',
                        'a' => '<p>On the code screen, enter one of your <strong>recovery codes</strong> instead of the app code. Each recovery code works once. Then set up the app again on your new phone under <strong>My account → Security</strong>.</p>'
                            .'<p>No recovery codes? Contact the IT helpdesk'.$helpdesk.'. After checking who you are they can reset your two-step verification so you can sign in and set it up again.</p>',
                    ],
                    [
                        'id' => 'code-rejected',
                        'q' => 'The code from the app is not accepted.',
                        'a' => '<ul>'
                            .'<li>Codes change every 30 seconds: type the one shown <strong>now</strong>.</li>'
                            .'<li>The <strong>time on your phone</strong> must be set automatically (Settings → Date &amp; time → automatic). A phone clock that is a few minutes off gives wrong codes.</li>'
                            .'<li>Make sure you use the entry named <strong>'.e($this->mfa->issuer).'</strong> in the app if you have several.</li>'
                            .'</ul>',
                    ],
                    [
                        'id' => 'trusted-computer',
                        'q' => 'What does “Don’t ask again on this computer” do?',
                        'a' => "<p>It skips the code on that computer and browser for <strong>{$trustDays} days</strong>. Only tick it on a computer that only you use, <strong>never on shared ward computers</strong>.</p>"
                            .'<p>You can see and remove these computers under <strong>My account → Security</strong>.</p>',
                    ],
                ],
            ],
            [
                'id' => 'applications',
                'title' => 'Applications and access',
                'icon' => 'heroicon-o-squares-2x2',
                'items' => [
                    [
                        'id' => 'find-applications',
                        'q' => 'Where do I find my applications?',
                        'a' => '<p>After signing in you see <strong>My applications</strong>: every application you can open. Click one to open it; you are signed in to it automatically.</p>',
                    ],
                    [
                        'id' => 'no-access',
                        'q' => 'It says “You don’t have access” to an application. How do I get access?',
                        'a' => '<p>Click <strong>Request access</strong> on that page, or go to <strong>My requests → New request → I need access</strong>. Say briefly why you need it.</p>'
                            .'<p>Your <strong>head of department</strong> and the <strong>application owner</strong> are asked to approve (for some applications also IT security). You are notified when it is decided, and the application then appears under My applications.</p>',
                    ],
                    [
                        'id' => 'request-status',
                        'q' => 'How do I see what happened to my request?',
                        'a' => '<p>Open <strong>My requests</strong>. The <em>Access</em> tab shows each request and who it is waiting for. Approvers get reminders automatically if they don’t respond.</p>',
                    ],
                    [
                        'id' => 'wrong-role',
                        'q' => 'I can open an application but some features are missing.',
                        'a' => '<p>What you can do inside an application depends on your role in it, set by the application owner. Ask your head of department or the application owner to change your role, or contact the IT helpdesk'.$helpdesk.'.</p>',
                    ],
                    [
                        'id' => 'new-colleague',
                        'q' => 'A new colleague needs an account.',
                        'a' => '<p>Sign in and go to <strong>My requests → New request → A new colleague needs an account</strong>. After approval, IT creates the account and the colleague signs in with their Windows username and password.</p>',
                    ],
                    [
                        'id' => 'not-working',
                        'q' => 'An application is not working properly.',
                        'a' => '<p>Sign in and go to <strong>My requests → New request → Something isn’t working</strong> to open an IT help ticket. You can follow the replies under <em>My requests → IT help</em>.</p>',
                    ],
                ],
            ],
        ];
    }

    /** " at 1234 or help@example.com" (with links), or "" when no contact is set. */
    public function helpdesk(): string
    {
        $contacts = array_filter([
            $this->general->support_phone ? '<strong>'.e($this->general->support_phone).'</strong>' : null,
            $this->general->support_email ? '<a href="mailto:'.e($this->general->support_email).'">'.e($this->general->support_email).'</a>' : null,
        ]);

        return $contacts ? ' at '.implode(' or ', $contacts) : '';
    }

    private function minutes(int $minutes): string
    {
        return $minutes === 1 ? '1 minute' : "{$minutes} minutes";
    }
}
