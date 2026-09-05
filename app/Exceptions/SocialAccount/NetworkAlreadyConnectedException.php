<?php

declare(strict_types=1);

namespace App\Exceptions\SocialAccount;

use App\Enums\SocialAccount\Platform;
use RuntimeException;

class NetworkAlreadyConnectedException extends RuntimeException
{
    public function __construct(public readonly Platform $platform)
    {
        parent::__construct("This workspace already has a {$platform->network()} account connected.");
    }
}
