<?php

declare(strict_types=1);

namespace App\Enums\Folder;

enum Type: string
{
    case Master = 'master';
    case Personal = 'personal';
}
