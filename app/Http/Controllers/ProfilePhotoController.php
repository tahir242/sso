<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\ProfilePhoto;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a user's profile photo: the URL sent as the OpenID Connect
 * `picture` claim, so client applications can show it. Addressed by the
 * user's ULID (not guessable from the numeric ID) plus a version that
 * changes with every upload, so the current version may be cached.
 */
class ProfilePhotoController
{
    public function __invoke(string $user, string $version): BinaryFileResponse
    {
        $record = User::query()->where('ulid', $user)->first(['id', 'ulid', 'picture']);
        $path = $record ? ProfilePhoto::path($record) : null;
        abort_if($path === null, 404);

        $current = hash_equals(ProfilePhoto::version($path), $version);

        return response()->file(Storage::disk(ProfilePhoto::DISK)->path($path), [
            'Content-Type' => ProfilePhoto::contentType($path),
            'Content-Disposition' => 'inline; filename="photo.'.pathinfo($path, PATHINFO_EXTENSION).'"',
            'Cache-Control' => $current ? 'public, max-age=31536000, immutable' : 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
