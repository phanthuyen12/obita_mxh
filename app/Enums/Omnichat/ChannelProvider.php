<?php

declare(strict_types=1);

namespace App\Enums\Omnichat;

enum ChannelProvider: string
{
    case ZaloOa = 'zalo-oa';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case Shopee = 'shopee';
    case Lazada = 'lazada';
    case Facebook = 'facebook';
    case Website = 'website';
}
