<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every account must have an email address (password recovery, security
 * alerts). Signed-in users without one are sent to add it first; JSON and
 * Livewire requests pass so open pages never break mid-action.
 */
class EnsureEmailAddress
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && blank($user->email) && $request->isMethod('GET') && ! $request->expectsJson()
            && ! $request->routeIs('account.email', 'logout')) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('account.email');
        }

        return $next($request);
    }
}
