<?php

namespace App\Support\Media;

use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

/**
 * Media URLs point to /media/{uuid}[/{conversion}], served by MediaController
 * after a permission check. Files themselves live on the private "media" disk.
 */
class RouteUrlGenerator extends DefaultUrlGenerator
{
    public function getUrl(): string
    {
        return route('media.show', array_filter([
            'uuid' => $this->media->uuid,
            'conversion' => $this->conversion?->getName(),
        ]));
    }
}
