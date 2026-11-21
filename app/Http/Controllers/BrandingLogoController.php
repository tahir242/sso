<?php

namespace App\Http\Controllers;

use App\Domain\Settings\Branding;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the configured application logo (public: the sign-in screen shows
 * it). The URL carries a version that changes with every upload, so the
 * current version may be cached for a long time.
 */
class BrandingLogoController
{
    public function __invoke(string $version): BinaryFileResponse
    {
        $path = Branding::logoPath();
        abort_if($path === null, 404);

        $current = hash_equals(substr(sha1($path), 0, 12), $version);

        return response()->file(Storage::disk(Branding::DISK)->path($path), [
            'Content-Type' => Branding::contentType($path),
            'Content-Disposition' => 'inline; filename="logo.'.pathinfo($path, PATHINFO_EXTENSION).'"',
            // An older version (page cached before a replacement) gets the new file, briefly cached.
            'Cache-Control' => $current ? 'public, max-age=31536000, immutable' : 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            // An SVG opened on its own can never run script or load anything.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox",
        ]);
    }
}
