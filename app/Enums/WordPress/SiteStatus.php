<?php

namespace App\Enums\WordPress;

enum SiteStatus: string
{
    case Connected = 'connected';
    case Error = 'error';
    case Disconnected = 'disconnected';
}
