<?php

namespace App\Domain\Identity\Mfa;

enum SecondFactorResult
{
    case Valid;
    case Invalid;
    case TooManyAttempts;
}
