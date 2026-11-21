<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\EndSsoSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutController
{
    public function __invoke(Request $request, EndSsoSession $endSession): RedirectResponse
    {
        $endSession->handle($request);

        return redirect()->route('login');
    }
}
