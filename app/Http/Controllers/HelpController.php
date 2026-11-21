<?php

namespace App\Http\Controllers;

use App\Domain\Settings\GeneralSettings;
use App\Support\Help\Faq;
use Illuminate\Contracts\View\View;

/**
 * Public help page (/help): common questions about signing in, passwords,
 * two-step verification and application access. Linked from the sign-in
 * screen, so it needs no sign-in itself.
 */
class HelpController
{
    public function __invoke(Faq $faq, GeneralSettings $settings): View
    {
        return view('help', [
            'sections' => $faq->sections(),
            'helpdesk' => $faq->helpdesk(),
            'settings' => $settings,
        ]);
    }
}
