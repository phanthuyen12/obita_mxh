export const Platform = {
    // LinkedIn: 'linkedin',
    // LinkedInPage: 'linkedin-page',
    // X: 'x',
    TikTok: 'tiktok',
    YouTube: 'youtube',
    Facebook: 'facebook',
    ZaloOa: 'zalo-oa',
    Lazada: 'lazada',
    Shopee: 'shopee',
    WordPress: 'wordpress',
    Instagram: 'instagram',
    InstagramFacebook: 'instagram-facebook',
    // Threads: 'threads',
    // Pinterest: 'pinterest',
    // Bluesky: 'bluesky',
    // Mastodon: 'mastodon',
    // Telegram: 'telegram',
    // Discord: 'discord',
} as const;

export type PlatformValue = (typeof Platform)[keyof typeof Platform];
