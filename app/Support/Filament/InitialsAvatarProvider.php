<?php

namespace App\Support\Filament;

use App\Domain\Identity\Auth\RecentAccounts;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Initials avatars rendered locally as an SVG data URI. Filament's default
 * loads them from ui-avatars.com, which the CSP (img-src 'self' data:)
 * blocks and which the hospital network may not reach anyway.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = e(RecentAccounts::initials((string) Filament::getNameForDefaultAvatar($record)));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#d10f0f"/><stop offset="1" stop-color="#7e0505"/></linearGradient></defs>'
            .'<rect width="64" height="64" fill="url(#g)"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" font-family="Inter,Segoe UI,Arial,sans-serif" font-size="26" font-weight="600" fill="#fff">'.$initials.'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
