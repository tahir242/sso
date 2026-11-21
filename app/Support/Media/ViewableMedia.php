<?php

namespace App\Support\Media;

use App\Domain\Identity\Models\User;

/**
 * Models that own media decide who may see it.
 */
interface ViewableMedia
{
    public function canViewMedia(?User $user): bool;
}
