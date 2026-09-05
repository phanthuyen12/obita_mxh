<?php

namespace App\Enums\Omnichat;

enum ChannelStatus: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case TokenExpired = 'token_expired';
    case Disabled = 'disabled';
}
