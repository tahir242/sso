<?php

namespace App\Http\Controllers;

use App\Support\Media\ViewableMedia;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a media file (or one of its conversions) after asking the owning
 * model whether the visitor may see it.
 */
class MediaController
{
    public function __invoke(Request $request, string $uuid, ?string $conversion = null): BinaryFileResponse
    {
        $media = Media::where('uuid', $uuid)->firstOrFail();
        $owner = $media->model;

        abort_unless($owner instanceof ViewableMedia && $owner->canViewMedia($request->user()), 404);

        if ($conversion !== null) {
            abort_unless($media->hasGeneratedConversion($conversion), 404);
        }

        $path = $media->getPath($conversion ?? '');
        abort_unless(is_file($path), 404);

        $inline = str_starts_with((string) $media->mime_type, 'image/') || $media->mime_type === 'application/pdf';

        return response()->file($path, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.addcslashes($media->file_name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
