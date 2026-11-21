<?php

namespace App\Domain\Settings\Rules;

use App\Domain\Settings\Branding;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Checks what an uploaded logo really is, not what the browser says:
 * PNG/JPEG/WebP must decode as that image type with sensible dimensions,
 * SVG must be a plain drawing without scripts or external references.
 */
final class LogoImage implements ValidationRule
{
    private const array RASTER = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_WEBP => 'webp'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('The logo could not be uploaded.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension() ?: (string) $value->guessExtension());

        if (! array_key_exists($extension, Branding::TYPES)) {
            $fail('The logo must be a PNG, JPG, WebP or SVG image.');

            return;
        }

        if ($extension === 'svg') {
            if (! Branding::isSafeSvg((string) file_get_contents($value->getRealPath()))) {
                $fail('This SVG contains scripts, links or other content that is not allowed in a logo. Export it again as a plain SVG, or use PNG.');
            }

            return;
        }

        $info = @getimagesize($value->getRealPath());

        if ($info === false || ! isset(self::RASTER[$info[2]])) {
            $fail('The logo file is not a valid PNG, JPG or WebP image.');

            return;
        }

        [$width, $height] = $info;

        if ($width < 32 || $height < 32 || $width > 4000 || $height > 4000) {
            $fail('The logo must be between 32 and 4000 pixels wide and high.');
        }
    }
}
