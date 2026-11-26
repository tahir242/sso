<?php

namespace App\Domain\Identity\Mfa;

enum LoginStep
{
    case Complete;
    case ChallengeMfa;
}
