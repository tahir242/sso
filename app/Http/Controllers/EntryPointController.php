<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\CompleteLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "/" has no page of its own: SSO starts at the sign-in screen.
 * Signed-in users go to the admin console when their roles allow it,
 * otherwise to the staff portal.
 */
class EntryPointController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        return $user
            ? redirect()->to(CompleteLogin::homeFor($user))
            : redirect()->route('login');
    }
}
