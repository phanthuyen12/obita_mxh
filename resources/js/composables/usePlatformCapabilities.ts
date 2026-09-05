export type PlatformCapability = 'omnichat' | 'publish';

const platformCapabilities: Record<string, PlatformCapability[]> = {
    facebook: ['omnichat', 'publish'],
    instagram: ['publish'],
    'instagram-facebook': ['publish'],
    'zalo-oa': ['omnichat'],
    lazada: ['omnichat'],
    shopee: ['omnichat'],
    youtube: ['publish'],
    tiktok: ['publish'],
    wordpress: ['publish'],
    linkedin: ['publish'],
    'linkedin-page': ['publish'],
};

export const getPlatformCapabilities = (
    platform: string,
): PlatformCapability[] => platformCapabilities[platform] ?? [];
