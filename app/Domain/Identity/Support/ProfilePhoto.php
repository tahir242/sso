<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * A user's profile photo (OpenID Connect `picture` claim).
 *
 * Files live on the private "local" disk (storage/app/private/profile-photos)
 * and are served by ProfilePhotoController, so no storage symlink is needed.
 * Only raster images are accepted (no SVG, which could carry script).
 */
final class ProfilePhoto
{
    public const string DISK = 'local';

    public const string DIRECTORY = 'profile-photos';

    public const int MAX_KILOBYTES = 2048;

    /** Photos are resized in the browser to this square size before upload. */
    public const int SIZE = 256;

    /** extension => content type the photo is served with (never the client's claim). */
    public const array TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    /** Stored path of the user's photo, when set and still on disk. */
    public static function path(User $user): ?string
    {
        $path = $user->picture;

        if (blank($path) || ! self::isPhotoPath($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->exists($path) ? $path : null;
    }

    /**
     * Absolute, cache-friendly URL of the photo (the version changes with every
     * upload). Absolute because client applications show it on their own pages.
     */
    public static function url(User $user): ?string
    {
        return filled($user->picture) && self::isPhotoPath($user->picture)
            ? route('users.picture', ['user' => $user->ulid, 'version' => self::version($user->picture)])
            : null;
    }

    public static function version(string $path): string
    {
        return substr(sha1($path), 0, 12);
    }

    public static function contentType(string $path): ?string
    {
        return self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
    }

    /** Only files the user form stored: profile-photos/<name>.<allowed extension>. */
    public static function isPhotoPath(string $path): bool
    {
        return (bool) preg_match('#^'.self::DIRECTORY.'/[A-Za-z0-9._-]+$#', $path)
            && ! str_contains($path, '..')
            && self::contentType($path) !== null;
    }

    public static function delete(?string $path): void
    {
        if (filled($path) && self::isPhotoPath($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
