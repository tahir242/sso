<?php

namespace App\Domain\Settings;

use Illuminate\Support\Facades\Storage;

/**
 * The application logo chosen under Settings → General.
 *
 * Files live on the private "local" disk (storage/app/private/branding) and
 * are served by BrandingLogoController, so no storage symlink is needed and
 * SVGs get a sandboxing CSP. Without an upload the built-in mark is shown
 * (resources/views/components/brand-mark.blade.php).
 */
final class Branding
{
    public const string DISK = 'local';

    public const string DIRECTORY = 'branding';

    public const int MAX_KILOBYTES = 1024;

    /** extension => content type the logo is served with (never the client's claim). */
    public const array TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
    ];

    /** Stored path of the custom logo, when one is configured and still on disk. */
    public static function logoPath(): ?string
    {
        $path = app(GeneralSettings::class)->logo_path;

        if (blank($path) || ! self::isBrandingPath($path)) {
            return null;
        }

        return once(fn () => Storage::disk(self::DISK)->exists($path) ? $path : null);
    }

    /** Public, cache-friendly URL of the custom logo (the version changes with every upload). */
    public static function logoUrl(): ?string
    {
        $path = self::logoPath();

        return $path ? self::urlFor($path) : null;
    }

    public static function urlFor(string $path): string
    {
        return route('branding.logo', ['version' => substr(sha1($path), 0, 12)]);
    }

    public static function contentType(string $path): ?string
    {
        return self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
    }

    /** Only files the settings page stored: branding/<name>.<allowed extension>. */
    public static function isBrandingPath(string $path): bool
    {
        return (bool) preg_match('#^'.self::DIRECTORY.'/[A-Za-z0-9._-]+$#', $path)
            && ! str_contains($path, '..')
            && self::contentType($path) !== null;
    }

    public static function delete(?string $path): void
    {
        if (filled($path) && self::isBrandingPath($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /**
     * An SVG is accepted only if it is a plain drawing: well-formed XML with an
     * <svg> root and nothing that can run code or load other resources.
     */
    public static function isSafeSvg(string $svg): bool
    {
        if ($svg === '' || preg_match('/<!DOCTYPE|<!ENTITY/i', $svg)) {
            return false;
        }

        $unsafe = [
            '/<\s*(script|foreignObject|iframe|object|embed|audio|video|handler|listener)\b/i',
            '/\son[a-z]+\s*=/i',                                   // event handlers
            '/(javascript|vbscript)\s*:/i',
            '/(href|src)\s*=\s*["\']\s*(?!#|data:image\/(png|jpe?g|gif|webp);)/i', // external or non-image references
            '/@import|url\(\s*["\']?\s*(?!#)/i',                   // CSS loading other resources
        ];

        foreach ($unsafe as $pattern) {
            if (preg_match($pattern, $svg)) {
                return false;
            }
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($svg, options: LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml !== false && strtolower($xml->getName()) === 'svg';
    }
}
