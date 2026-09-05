<script setup lang="ts">
import {
    IconBrandDiscord,
    IconBrandFacebook,
    IconBrandInstagram,
    IconBrandLinkedin,
    IconBrandMastodon,
    IconBrandPinterest,
    IconBrandTelegram,
    IconBrandThreads,
    IconBrandTiktok,
    IconBrandWhatsapp,
    IconBrandX,
    IconBrandYoutube,
    IconMessage,
} from '@tabler/icons-vue';
import { computed } from 'vue';

type Props = {
    provider: string;
    class?: string;
};

const props = withDefaults(defineProps<Props>(), {
    class: 'size-4',
});

const logoSource = computed(() => {
    const logos: Record<string, string> = {
        shopee: '/images/accounts/shopee.svg',
        lazada: '/images/accounts/lazada.png',
        'zalo-oa': '/images/accounts/zalo-oa.svg',
    };

    return logos[props.provider.toLowerCase()] ?? null;
});

const iconComponent = computed(() => {
    const map: Record<string, any> = {
        facebook: IconBrandFacebook,
        instagram: IconBrandInstagram,
        x: IconBrandX,
        linkedin: IconBrandLinkedin,
        youtube: IconBrandYoutube,
        tiktok: IconBrandTiktok,
        threads: IconBrandThreads,
        pinterest: IconBrandPinterest,
        telegram: IconBrandTelegram,
        whatsapp: IconBrandWhatsapp,
        mastodon: IconBrandMastodon,
        discord: IconBrandDiscord,
    };
    return map[props.provider.toLowerCase()] || IconMessage;
});
</script>

<template>
    <img
        v-if="logoSource"
        :src="logoSource"
        :alt="provider"
        :class="props.class"
        class="object-contain"
    />
    <component v-else :is="iconComponent" :class="props.class" />
</template>
