<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    IconArrowsMaximize,
    IconCalendar,
    IconCheck,
    IconChevronLeft,
    IconChevronRight,
    IconCopy,
    IconDeviceDesktop,
    IconDeviceMobile,
    IconDownload,
    IconExternalLink,
    IconEye,
    IconFilter,
    IconGripVertical,
    IconHistory,
    IconInfoCircle,
    IconLayersLinked,
    IconLoader,
    IconMicrophone,
    IconMovie,
    IconMusic,
    IconPhoto,
    IconPlayerPause,
    IconPlayerPlay,
    IconPlayerStop,
    IconPlus,
    IconSearch,
    IconSparkles,
    IconTrash,
    IconUpload,
    IconUser,
    IconVideo,
    IconVolume2,
    IconVolumeOff,
    IconX,
} from '@tabler/icons-vue';
import { computed, onUnmounted, reactive, ref, watch } from 'vue';

import { edit as editPost } from '@/actions/App/Http/Controllers/App/PostController';
import PageHeader from '@/components/PageHeader.vue';
import MediaPickerDialog from '@/components/posts/MediaPickerDialog.vue';
import PlatformPreview from '@/components/posts/previews/PlatformPreview.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { destroy, preview, store } from '@/routes/app/content-clones';
import type { MediaItem } from '@/types/media';

interface SourcePostPlatform {
    id: string;
    social_account: {
        id: string;
        display_name: string;
        platform: string;
    } | null;
}

interface SourcePost {
    id: string;
    content: string;
    media: MediaItem[];
    created_at: string;
    post_platforms?: SourcePostPlatform[];
}

interface SocialAccount {
    id: string;
    display_name: string | null;
    username: string | null;
    platform: string;
    display_label?: string;
    handle_label?: string;
    avatar_url?: string | null;
}

interface PreviewSocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    display_label: string;
    handle_label: string;
    avatar_url: string | null;
}

interface Workflow {
    id: string;
    name: string;
}

interface ClonePostPlatform {
    id: string;
    platform_name: string | null;
    social_account: SocialAccount | null;
}

interface ClonePost {
    id: string;
    content: string;
    status: string;
    workflow_status: string;
    scheduled_at: string | null;
    published_at: string | null;
    post_platforms: ClonePostPlatform[];
}

interface Campaign {
    id: string;
    theme: string | null;
    total_posts: number;
    generated_posts: number;
    interval_days: number;
    start_at: string;
    next_run_at: string;
    is_active: boolean;
    require_approval: boolean;
    source_post: SourcePost | null;
    content_workflow: Workflow | null;
    posts: ClonePost[];
}

interface PreviewSuggestion {
    content: string;
    media: MediaItem[];
    provider: string;
    ai_images_failed?: boolean;
}

const props = defineProps<{
    campaigns: Campaign[];
    sourcePosts: SourcePost[];
    socialAccounts: SocialAccount[];
    contentWorkflows: Workflow[];
}>();

const activeTab = ref('create');
const submitting = ref(false);
const previewing = ref(false);
const previewError = ref('');
const previewStatusMsg = ref('');
const logoUploading = ref(false);
const suggestions = ref<PreviewSuggestion[]>([]);
const selectedSuggestion = ref<PreviewSuggestion | null>(null);
const previewPlatform = ref('');
const previewSocialAccount = ref<PreviewSocialAccount | null>(null);

const videoStartImageUploading = ref(false);
const videoEndImageUploading = ref(false);

const isPreviewModalOpen = ref(false);
const previewDeviceMode = ref<'mobile' | 'desktop'>('mobile');

// Scene Video Preview Modal
const isSceneVideoModalOpen = ref(false);
const activeSceneVideoModalIndex = ref<number | null>(null);
const activeSceneVideoModalScene = computed(() => {
    if (activeSceneVideoModalIndex.value === null || !form.video_scenes) return null;
    return form.video_scenes[activeSceneVideoModalIndex.value] || null;
});
const openSceneVideoModal = (index: number) => {
    activeSceneVideoModalIndex.value = index;
    isSceneVideoModalOpen.value = true;
};

// Media Library Dialog Integration
const mediaPickerDialog = ref<InstanceType<typeof MediaPickerDialog> | null>(null);
const mediaPickerTarget = ref<'reference_product' | 'scene_image' | null>(null);
const mediaPickerSceneIndex = ref<number | null>(null);

const openMediaPickerForReference = () => {
    mediaPickerTarget.value = 'reference_product';
    mediaPickerSceneIndex.value = null;
    mediaPickerDialog.value?.open();
};

const openMediaPickerForCharacter = () => {
    mediaPickerTarget.value = 'character_avatar';
    mediaPickerSceneIndex.value = null;
    mediaPickerDialog.value?.open();
};

const openMediaPickerForScene = (index: number) => {
    mediaPickerTarget.value = 'scene_image';
    mediaPickerSceneIndex.value = index;
    mediaPickerDialog.value?.open();
};

const showCharacterDnaDetails = ref(false);

const handlePickedMedia = (picked: any[]) => {
    if (!picked || picked.length === 0) return;

    if (mediaPickerTarget.value === 'reference_product') {
        const existingUrls = new Set(manualSourceMedia.value.map((m) => m.url));
        for (const item of picked) {
            if (!existingUrls.has(item.url)) {
                manualSourceMedia.value.push({
                    id: String(item.id || Date.now() + Math.random()),
                    url: item.url,
                    path: item.path || item.url,
                    type: item.type || 'image',
                    mime_type: item.mime_type || 'image/jpeg',
                    original_filename: item.original_filename || '',
                });
            }
        }
    } else if (mediaPickerTarget.value === 'character_avatar') {
        form.character_avatar = picked[0].url;
        form.character_id = 'custom';
    } else if (
        mediaPickerTarget.value === 'scene_image' &&
        mediaPickerSceneIndex.value !== null
    ) {
        const targetIndex = mediaPickerSceneIndex.value;
        if (form.video_scenes && form.video_scenes[targetIndex]) {
            form.video_scenes[targetIndex].start_image = picked[0].url;
        }
    }
};

const downloadMedia = async (url: string, filename?: string) => {
    if (!url) return;
    try {
        const name =
            filename ||
            url.split('/').pop()?.split('?')[0] ||
            'king-coffee-media.jpg';
        const response = await fetch(url, { mode: 'cors' });
        if (!response.ok) throw new Error('Network response was not ok');
        const blob = await response.blob();
        const blobUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = name;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(blobUrl);
    } catch (e) {
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.download = filename || 'king-coffee-media.jpg';
        link.click();
    }
};

const downloadAllSuggestionMedia = async () => {
    const mediaList = selectedSuggestion.value?.media || [];
    for (let i = 0; i < mediaList.length; i++) {
        const m = mediaList[i];
        if (m?.url) {
            await downloadMedia(m.url, `king-coffee-${i + 1}.jpg`);
            await new Promise((r) => setTimeout(r, 400));
        }
    }
};

// Wizard Steps & Filter
const currentStep = ref<1 | 2 | 3>(1);
const targetAccountSearch = ref('');

// Source post configuration mode
const sourceInputMode = ref<'select' | 'manual'>('select');
const manualSourceContent = ref('');
const manualSourceMedia = ref<MediaItem[]>([]);
const manualMediaUploading = ref(false);
const sourcePlatformFilter = ref('all');
const sourcePageFilter = ref('all');
const aiContentMode = ref<'text_image' | 'video_ai'>('text_image');

const filteredTargetSocialAccounts = computed(() => {
    if (!targetAccountSearch.value.trim()) {
        return props.socialAccounts;
    }
    const q = targetAccountSearch.value.toLowerCase();
    return props.socialAccounts.filter(
        (a) =>
            (a.display_name || '').toLowerCase().includes(q) ||
            (a.username || '').toLowerCase().includes(q) ||
            (a.platform || '').toLowerCase().includes(q),
    );
});

// Consistent Character Presets (King Coffee Brand)
const characterPresets = [
    {
        id: 'barista_nam',
        name: 'Hoàng Nam',
        role: 'Barista Chuyên Nghiệp',
        age: 28,
        avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80',
        dna: 'Chàng trai Barista người Việt 28 tuổi, tóc đen cắt gọn gàng, nụ cười thân thiện tận tâm, mặc sơ mi trắng thắt cà vạt phối tạp dề barista King Coffee màu đen sang trọng. Phong thái am hiểu cà phê nghệ thuật.',
    },
    {
        id: 'office_mai_anh',
        name: 'Mai Anh',
        role: 'Nữ Văn Phòng Năng Động',
        age: 25,
        avatar: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=400&q=80',
        dna: 'Cô gái văn phòng người Việt 25 tuổi, thanh lịch, tóc dài buông nhẹ, trang phục công sở hiện đại màu be hoặc trắng, nụ cười rạng rỡ, tràn đầy năng lượng tươi mới mỗi sáng cùng ly cà phê King Coffee.',
    },
    {
        id: 'business_ha_linh',
        name: 'Hà Linh',
        role: 'Nữ Doanh Nhân Bản Lĩnh',
        age: 32,
        avatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=400&q=80',
        dna: 'Nữ doanh nhân người Việt 32 tuổi, quý phái, trang phục vest cao cấp màu xanh navy/đen, ánh mắt tự tin, phong thái đẳng cấp, thưởng thức tách King Coffee Espresso đậm đà.',
    },
    {
        id: 'creator_minh_tri',
        name: 'Minh Trí',
        role: 'Content Creator Trẻ',
        age: 24,
        avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=400&q=80',
        dna: 'Chàng trai sáng tạo nội dung người Việt 24 tuổi, phong cách trẻ trung năng động, áo thun tối giản hiện đại, nụ cười tươi sáng, trải nghiệm cà phê tiện lợi tràn đầy cảm hứng.',
    },
];

// Consistent Voiceover Voices
const voiceoverVoiceOptions = [
    {
        id: 'vi_vn_female_warm',
        name: 'Nữ Miền Bắc (Ấm áp, truyền cảm)',
        desc: 'Phù hợp video thương hiệu cao cấp, kể chuyện di sản King Coffee',
        gender: 'Nữ',
        accent: 'Bắc',
    },
    {
        id: 'vi_vn_male_deep',
        name: 'Nam Miền Bắc (Trầm ấm, bản lĩnh)',
        desc: 'Phù hợp video doanh nhân, câu chuyện khởi nghiệp, di sản hạt cà phê',
        gender: 'Nam',
        accent: 'Bắc',
    },
    {
        id: 'vi_vn_female_sweet',
        name: 'Nữ Miền Nam (Ngọt ngào, năng động)',
        desc: 'Phù hợp video giới thiệu sản phẩm mới, phong cách văn phòng và giới trẻ',
        gender: 'Nữ',
        accent: 'Nam',
    },
    {
        id: 'vi_vn_male_friendly',
        name: 'Nam Miền Nam (Thân thiện, gần gũi)',
        desc: 'Phù hợp vlog trải nghiệm quán cà phê, video gần gũi đời thường',
        gender: 'Nam',
        accent: 'Nam',
    },
];

const form = reactive({
    source_post_id: props.sourcePosts[0]?.id ?? '',
    manual_source_content: '',
    target_social_account_ids: [] as string[],
    content_workflow_id: props.contentWorkflows[0]?.id ?? '',
    theme: '',
    prompt: '',
    image_prompt: '',
    ai_image_count: 1,
    ai_image_style: 'cinematic',
    ai_image_resolution: '2K',
    ai_image_aspect_ratio: '1:1',
    ai_logo_path: null as string | null,
    ai_logo_url: '',
    // Character DNA
    character_enabled: true,
    character_id: 'barista_nam',
    character_name: 'Hoàng Nam',
    character_avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80',
    character_dna_prompt: 'Chàng trai Barista người Việt 28 tuổi, tóc đen cắt gọn gàng, nụ cười thân thiện tận tâm, mặc sơ mi trắng thắt cà vạt phối tạp dề barista King Coffee màu đen sang trọng. Phong thái am hiểu cà phê nghệ thuật.',
    video_hook: '',
    video_target_duration: 32,
    video_scenes: [] as any[],
    video_bgm_track: 'king_coffee_luxury',
    video_bgm_url: '',
    video_bgm_volume: 80,
    video_voiceover_voice: 'vi_vn_female_warm',
    video_auto_subtitles: true,
    video_resolution: '1080p',
    video_aspect_ratio: '9:16',
    initial_content: '',
    initial_media: [] as MediaItem[],
    total_posts: 7,
    interval_days: 1,
    start_at: (() => {
        const d = new Date();
        d.setDate(d.getDate() + 1);
        d.setMinutes(0);
        const pad = (n: number) => n.toString().padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    })(),
    require_approval: true,
    diff_content_per_page: false, // Option for different content per target account
});

// Computed options for filtering source posts
const sourceSocialAccounts = computed(() => {
    const map = new Map<string, any>();
    props.sourcePosts.forEach((post) => {
        post.post_platforms?.forEach((p) => {
            if (p.social_account) {
                map.set(p.social_account.id, p.social_account);
            }
        });
    });
    return Array.from(map.values());
});

const sourcePlatforms = computed(() => {
    const set = new Set<string>();
    props.sourcePosts.forEach((post) => {
        post.post_platforms?.forEach((p) => {
            if (p.social_account?.platform) {
                set.add(p.social_account.platform);
            }
        });
    });
    return Array.from(set);
});

interface ImageStyleOption {
    id: string;
    title: string;
    description: string;
    badge: string;
    icon: string;
}

const imageStyles: ImageStyleOption[] = [
    {
        id: 'cinematic',
        title: 'Cinematic Studio',
        description:
            'Ánh sáng điện ảnh ấm áp, hiệu ứng xoá phông bokeh tôn vinh sản phẩm',
        badge: 'Phổ biến',
        icon: '',
    },
    {
        id: 'poster',
        title: 'E-Commerce Poster',
        description:
            'Chụp studio nền sạch, ánh sáng sản phẩm nổi khối chuẩn catalogue',
        badge: 'Thương mại',
        icon: '',
    },
    {
        id: 'mockup',
        title: 'Luxury 3D Mockup',
        description:
            'Bao bì sắc nét, góc chụp 45 độ cao cấp, đổ bóng và phản chiếu thực',
        badge: 'Cao cấp',
        icon: '',
    },
    {
        id: 'minimalist',
        title: 'Lifestyle Tối Giản',
        description:
            'Bối cảnh đời sống hiện đại, ánh sáng tự nhiên tinh tế và thanh lịch',
        badge: 'Tự nhiên',
        icon: '',
    },
    {
        id: '',
        title: 'King Coffee Signature',
        description:
            'Không gian ấm áp, tone nâu vàng quý phái bám sát nhận diện thương hiệu',
        badge: 'Chuẩn Brand',
        icon: '',
    },
];

const imageAspectRatios = [
    {
        value: '1:1',
        label: '1:1 Vuông',
        desc: 'Feed Facebook / IG',
        ratioClass: 'w-5 h-5',
    },
    {
        value: '4:3',
        label: '4:3 Ngang',
        desc: 'Bài viết / Fanpage',
        ratioClass: 'w-6 h-4.5',
    },
    {
        value: '16:9',
        label: '16:9 Rộng',
        desc: 'Banner / YouTube',
        ratioClass: 'w-7 h-4',
    },
    {
        value: '9:16',
        label: '9:16 Dọc',
        desc: 'Reels / TikTok',
        ratioClass: 'w-4 h-7',
    },
];

const imageResolutions = [
    { value: '', label: '1K HD', desc: 'Tạo siêu tốc · Chuẩn web' },
    { value: '2K', label: '2K Quad HD', desc: 'Khuyên dùng · Sắc nét cao' },
    { value: '4K', label: '4K Ultra HD', desc: 'Chất lượng in ấn · Studio' },
];

const visualPromptPresets = [
    'Cận cảnh ly cà phê sữa đá đọng hơi nước mát lạnh',
    'Bàn gỗ sồi sang trọng phong cách tối giản',
    'Ánh nắng sớm ấm áp chiếu xiên qua khung cửa',
    'Khói cà phê bốc lên mềm mại thơm lừng',
    'Bao bì King Coffee nổi bật ở góc chụp 45 độ',
    'Hạt cà phê rang vàng óng ả rơi nhẹ xung quanh',
    'Studio chụp ảnh quảng cáo đơn sắc sang trọng',
];

const addVisualPromptTag = (tag: string) => {
    if (!form.image_prompt) {
        form.image_prompt = tag;
    } else if (!form.image_prompt.includes(tag)) {
        form.image_prompt += `, ${tag}`;
    }
};

const targetDurationOptions = [
    {
        value: 16,
        duration: '16s',
        scenes: '2 cảnh',
        label: 'TikTok / Reels',
        desc: 'Giật tít ngắn, nhịp nhanh',
    },
    {
        value: 32,
        duration: '32s',
        scenes: '4 cảnh',
        label: 'Viral Ads',
        desc: 'Hook + Vấn đề + Giải pháp',
        recommended: true,
    },
    {
        value: 48,
        duration: '48s',
        scenes: '6 cảnh',
        label: 'Giới thiệu SP',
        desc: 'Chi tiết tính năng, giá trị',
    },
    {
        value: 64,
        duration: '64s',
        scenes: '8 cảnh',
        label: 'Storytelling',
        desc: 'Kể chuyện thương hiệu dài',
    },
];

const quickHookPresets = [
    {
        label: '🔥 Năng lượng sáng',
        text: '3 giây bừng tỉnh năng lượng - Bí quyết của dân văn phòng mỗi sáng!',
    },
    {
        label: '👑 Cà phê của Vua',
        text: 'Cà phê của Vua - Đỉnh cao hương vị đánh thức mọi giác quan!',
    },
    {
        label: '☕ Đừng mua nếu...',
        text: 'Đừng mua King Coffee nếu bạn chưa biết bí mật này!',
    },
    {
        label: '⚡ Uống đúng cách?',
        text: 'Bạn có đang uống cà phê đúng cách mỗi sáng không?',
    },
    {
        label: '✨ Bí quyết Espresso',
        text: 'Bí quyết tạo nên tách espresso sánh đậm chuẩn hoàng gia!',
    },
];

const addHookPreset = (hook: string) => {
    form.video_hook = hook;
};

// Video Generation Pipeline States
const generatingSceneImageIndex = ref<number | null>(null);
const generatingAllSceneImages = ref(false);
const generatingSceneVideoIndex = ref<number | null>(null);
const generatingAllSceneVideos = ref(false);
const stitchingFullVideo = ref(false);
const stitchedVideoUrl = ref('');
const pipelineNotification = ref('');

// Helper to poll async background task status
const pollTaskResult = async (
    taskId: string,
    field: 'url' | 'video_url',
): Promise<string | null> => {
    const interval = 2000;
    const maxAttempts = 150; // up to 5 minutes
    for (let attempts = 0; attempts < maxAttempts; attempts++) {
        await new Promise((resolve) => setTimeout(resolve, interval));
        try {
            const res = await fetch(
                `/content-clones/preview-status/${taskId}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );
            if (!res.ok) continue;
            const data = await res.json();
            if (data.status === 'completed') {
                return (
                    data[field] ||
                    data.suggestions?.[field] ||
                    data.result?.[field] ||
                    null
                );
            }
            if (data.status === 'failed') {
                throw new Error(data.error || 'Tác vụ thất bại');
            }
        } catch (err) {
            console.warn('Lỗi kiểm tra trạng thái tác vụ:', err);
        }
    }
    return null;
};

// Step 1: Generate AI Image for a specific scene
const generateSceneImage = async (index: number) => {
    const scene = form.video_scenes[index];
    if (!scene) return;
    generatingSceneImageIndex.value = index;
    try {
        const res = await fetch('/content-clones/generate-scene-image', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                prompt: scene.context_prompt,
                theme: form.theme,
                logo_path: form.ai_logo_path,
                style: form.ai_image_style,
                aspect_ratio: form.video_aspect_ratio,
                resolution: form.video_resolution,
                character_name: form.character_enabled ? form.character_name : '',
                character_dna: form.character_enabled ? form.character_dna_prompt : '',
                character_avatar: form.character_enabled ? form.character_avatar : '',
            }),
        });
        const data = await res.json();
        let url = data.url;
        if (data.task_id) {
            url = await pollTaskResult(data.task_id, 'url');
        }
        if (url) {
            scene.start_image = url;
            scene.end_image = url;
            pipelineNotification.value = `Đã vẽ xong ảnh AI cho Scene ${index + 1}!`;
            setTimeout(() => {
                pipelineNotification.value = '';
            }, 4000);
        }
    } catch (e: any) {
        console.error('Lỗi khi vẽ ảnh phân cảnh:', e);
    } finally {
        generatingSceneImageIndex.value = null;
    }
};

// Step 1 Batch: Generate AI Images for all scenes
const generateAllSceneImages = async () => {
    generatingAllSceneImages.value = true;
    try {
        for (let i = 0; i < form.video_scenes.length; i++) {
            await generateSceneImage(i);
        }
        pipelineNotification.value =
            'Đã hoàn thành vẽ ảnh AI cho tất cả các phân cảnh!';
        setTimeout(() => {
            pipelineNotification.value = '';
        }, 4000);
    } finally {
        generatingAllSceneImages.value = false;
    }
};

const selectCharacterPreset = (preset: any) => {
    form.character_id = preset.id;
    if (preset.id !== 'custom') {
        form.character_name = preset.name;
        form.character_avatar = preset.avatar;
        form.character_dna_prompt = preset.dna;
    }
};

const generatingAiVideoScript = ref(false);

const generateAiVideoScript = async () => {
    generatingAiVideoScript.value = true;
    pipelineNotification.value = 'AI đang phân tích sản phẩm và biên kịch chi tiết từng cảnh quay...';
    try {
        const res = await fetch('/content-clones/generate-video-scenes', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                video_hook: form.video_hook,
                video_target_duration: form.video_target_duration,
                source_post_id: form.source_post_id,
                manual_source_content: sourceInputMode.value === 'manual' ? manualSourceContent.value : '',
                theme: form.theme,
                prompt: form.prompt,
                character_name: form.character_enabled ? form.character_name : '',
                character_dna: form.character_enabled ? form.character_dna_prompt : '',
                character_avatar: form.character_enabled ? form.character_avatar : '',
            }),
        });
        const data = await res.json();
        if (data.success && Array.isArray(data.video_scenes) && data.video_scenes.length > 0) {
            form.video_scenes = data.video_scenes;
            activePreviewSceneIndex.value = 0;
            pipelineNotification.value = `✨ AI đã biên kịch thành công ${data.video_scenes.length} phân cảnh (${form.video_target_duration}s) với hành động 8s và lời thoại chi tiết!`;
            setTimeout(() => {
                pipelineNotification.value = '';
            }, 5000);
        } else {
            pipelineNotification.value = data.message || 'Không thể tạo kịch bản AI lúc này.';
            setTimeout(() => {
                pipelineNotification.value = '';
            }, 4000);
        }
    } catch (err: any) {
        pipelineNotification.value = err.message || 'Lỗi kết nối khi gọi AI tạo kịch bản.';
        setTimeout(() => {
            pipelineNotification.value = '';
        }, 4000);
    } finally {
        generatingAiVideoScript.value = false;
    }
};

const generatingSceneVoiceIndex = ref<number | null>(null);
const playingVoiceSceneIndex = ref<number | null>(null);
const sceneAudioPlayer = ref<HTMLAudioElement | null>(null);

const generateSceneVoiceover = async (index: number) => {
    const scene = form.video_scenes[index];
    if (!scene || !scene.voiceover_text) {
        pipelineNotification.value = 'Vui lòng nhập lời bình Voiceover trước khi tạo giọng đọc!';
        setTimeout(() => {
            pipelineNotification.value = '';
        }, 3000);
        return;
    }

    generatingSceneVoiceIndex.value = index;
    try {
        const res = await fetch('/content-clones/generate-scene-voiceover', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                voiceover_text: scene.voiceover_text,
                voice: form.video_voiceover_voice || 'vi_vn_female_warm',
                character_name: form.character_enabled ? form.character_name : 'King Coffee AI',
                prompt: form.prompt,
            }),
        });
        const data = await res.json();
        if (data.audio_url) {
            scene.voiceover_audio_url = data.audio_url;
            pipelineNotification.value = `Đã tạo giọng đọc AI cho Cảnh ${index + 1}! Bấm để nghe thử.`;
            setTimeout(() => {
                pipelineNotification.value = '';
            }, 4000);
            playSceneVoiceover(index);
        }
    } catch (e) {
        console.error('Lỗi khi tạo giọng đọc AI:', e);
    } finally {
        generatingSceneVoiceIndex.value = null;
    }
};

const playSceneVoiceover = (index: number) => {
    const scene = form.video_scenes[index];
    if (!scene?.voiceover_audio_url) return;

    if (playingVoiceSceneIndex.value === index && sceneAudioPlayer.value) {
        sceneAudioPlayer.value.pause();
        playingVoiceSceneIndex.value = null;
        return;
    }

    if (!sceneAudioPlayer.value) {
        sceneAudioPlayer.value = new Audio();
    }
    sceneAudioPlayer.value.src = scene.voiceover_audio_url;
    sceneAudioPlayer.value.play();
    playingVoiceSceneIndex.value = index;
    sceneAudioPlayer.value.onended = () => {
        playingVoiceSceneIndex.value = null;
    };
};

// Step 2: Render motion video for a specific scene
const generateSceneVideo = async (index: number, force = false) => {
    const scene = form.video_scenes[index];
    if (!scene) return;
    if (scene.video_url && !force) {
        openSceneVideoModal(index);
        return;
    }
    generatingSceneVideoIndex.value = index;
    try {
        // Resolve best reference product image (preserve 100% product appearance)
        const targetImageUrl =
            scene.start_image ||
            scene.end_image ||
            manualSourceMedia.value?.[0]?.url ||
            form.initial_media?.[0]?.url ||
            sourcePost.value?.media?.[0]?.url ||
            '';

        if (!scene.start_image && targetImageUrl) {
            scene.start_image = targetImageUrl;
        }

        const res = await fetch('/content-clones/generate-scene-video', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                image_url: targetImageUrl,
                action_prompt: scene.action_prompt,
                context_prompt: scene.context_prompt,
                duration: scene.duration,
                theme: form.theme,
                prompt: form.prompt,
                video_hook: form.video_hook,
                platform: previewPlatform.value || 'facebook',
                source_content:
                    sourceInputMode.value === 'manual'
                        ? manualSourceContent.value
                        : form.initial_content || '',
                voiceover_text: scene.voiceover_text || '',
                voice: form.video_voiceover_voice || 'vi_vn_female_warm',
                character_name: form.character_enabled ? form.character_name : '',
                character_dna: form.character_enabled ? form.character_dna_prompt : '',
                character_avatar: form.character_enabled ? form.character_avatar : '',
            }),
        });
        const data = await res.json();
        let videoUrl = data.video_url;
        if (data.task_id) {
            videoUrl = await pollTaskResult(data.task_id, 'video_url');
        }
        if (videoUrl) {
            scene.video_url = videoUrl;
            pipelineNotification.value = `Đã tạo video chuyển động cho Cảnh ${index + 1}! Bấm để xem.`;
            setTimeout(() => {
                pipelineNotification.value = '';
            }, 4000);
            openSceneVideoModal(index);
        }
    } catch (e: any) {
        console.error('Lỗi khi render video cảnh:', e);
    } finally {
        generatingSceneVideoIndex.value = null;
    }
};

// Step 2 Batch: Render motion video for all scenes
const generateAllSceneVideos = async () => {
    generatingAllSceneVideos.value = true;
    try {
        let renderedCount = 0;
        for (let i = 0; i < form.video_scenes.length; i++) {
            if (form.video_scenes[i]?.video_url) {
                continue; // Đã có video thì bỏ qua, tuyệt đối không gọi lại API
            }
            await generateSceneVideo(i);
            renderedCount++;
        }
        pipelineNotification.value =
            renderedCount > 0
                ? 'Đã render video chuyển động cho các phân cảnh còn lại!'
                : 'Tất cả các phân cảnh đều đã có video!';
        setTimeout(() => {
            pipelineNotification.value = '';
        }, 4000);
    } finally {
        generatingAllSceneVideos.value = false;
    }
};

// Step 3: Stitch & Render Full Video
const stitchFullVideo = async () => {
    stitchingFullVideo.value = true;
    try {
        const res = await fetch('/content-clones/stitch-video', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                scenes: form.video_scenes,
                bgm_track: form.video_bgm_track,
                auto_subtitles: form.video_auto_subtitles,
            }),
        });
        const data = await res.json();
        if (data.video_url) {
            stitchedVideoUrl.value = data.video_url;
            activePreviewSceneIndex.value = 0;
            isPlayingVideo.value = true;
            pipelineNotification.value =
                '🎉 Đã ghép hoàn chỉnh toàn bộ video kèm phụ đề và âm nhạc!';
            setTimeout(() => {
                pipelineNotification.value = '';
            }, 5000);
        }
    } catch (e: any) {
        console.error('Lỗi khi ghép video:', e);
    } finally {
        stitchingFullVideo.value = false;
    }
};

const filteredSourcePosts = computed(() => {
    return props.sourcePosts.filter((post) => {
        if (sourcePlatformFilter.value !== 'all') {
            const hasPlatform = post.post_platforms?.some(
                (p) =>
                    p.social_account?.platform === sourcePlatformFilter.value,
            );
            if (!hasPlatform) return false;
        }
        if (sourcePageFilter.value !== 'all') {
            const hasPage = post.post_platforms?.some(
                (p) => p.social_account?.id === sourcePageFilter.value,
            );
            if (!hasPage) return false;
        }
        return true;
    });
});

const selectedSourcePost = ref<SourcePost | null>(props.sourcePosts[0] ?? null);
const selectedMediaUrls = ref<string[]>([]);

watch(
    () => form.source_post_id,
    (sourcePostId) => {
        selectedSourcePost.value =
            props.sourcePosts.find((post) => post.id === sourcePostId) ?? null;
    },
);

// Sync selected media when selectedSourcePost changes
watch(selectedSourcePost, (newPost) => {
    if (newPost && newPost.media) {
        selectedMediaUrls.value = newPost.media.map((m) => m.url);
    } else {
        selectedMediaUrls.value = [];
    }
});

const toggleMediaSelection = (url: string) => {
    const idx = selectedMediaUrls.value.indexOf(url);
    if (idx > -1) {
        selectedMediaUrls.value.splice(idx, 1);
    } else {
        selectedMediaUrls.value.push(url);
    }
};

const submit = () => {
    // 1. Check target accounts
    if (!form.target_social_account_ids.length) {
        currentStep.value = 1;
        alert(
            'Vui lòng chọn ít nhất một Page / Kênh mạng xã hội đích để đăng bài.',
        );
        return;
    }

    // 2. Check source content
    if (
        sourceInputMode.value === 'manual' &&
        !manualSourceContent.value.trim()
    ) {
        currentStep.value = 1;
        alert('Vui lòng nhập nội dung bài viết nguồn tại Bước 1.');
        return;
    } else if (sourceInputMode.value === 'select' && !form.source_post_id) {
        currentStep.value = 1;
        alert('Vui lòng chọn bài viết nguồn từ danh sách hệ thống tại Bước 1.');
        return;
    }

    // 3. Fallback workflow if empty
    if (!form.content_workflow_id && props.contentWorkflows[0]?.id) {
        form.content_workflow_id = props.contentWorkflows[0].id;
    }

    // 4. Fallback start_at if empty
    if (!form.start_at) {
        const d = new Date();
        d.setDate(d.getDate() + 1);
        d.setMinutes(0);
        const pad = (n: number) => n.toString().padStart(2, '0');
        form.start_at = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    // 5. Fallback initial_content if empty
    if (!form.initial_content.trim()) {
        form.initial_content =
            selectedSuggestion.value?.content ||
            (sourceInputMode.value === 'manual'
                ? manualSourceContent.value
                : selectedSourcePost.value?.content || '');
    }

    submitting.value = true;

    // Filter media items based on user selection or manual source
    const finalMedia =
        sourceInputMode.value === 'manual'
            ? form.initial_media.length > 0
                ? form.initial_media
                : manualSourceMedia.value
            : form.initial_media.length > 0
              ? form.initial_media
              : selectedSourcePost.value?.media.filter((m) =>
                    selectedMediaUrls.value.includes(m.url),
                ) || [];

    const payload = {
        ...form,
        ai_content_mode: aiContentMode.value,
        source_post_id:
            sourceInputMode.value === 'select' ? form.source_post_id : '',
        manual_source_content:
            sourceInputMode.value === 'manual' ? manualSourceContent.value : '',
        manual_source_media:
            sourceInputMode.value === 'manual' ? manualSourceMedia.value : [],
        initial_media: finalMedia,
    };

    router.post(store.url(), payload, {
        onSuccess: () => {
            activeTab.value = 'campaigns';
        },
        onError: (errors) => {
            console.error('Campaign creation error:', errors);
            const firstError = Object.values(errors)[0];
            if (firstError) {
                alert(`Không tạo được chiến dịch: ${firstError}`);
            }
        },
        onFinish: () => (submitting.value = false),
    });
};

const csrfToken = (): string =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const generatePreview = async () => {
    previewing.value = true;
    previewError.value = '';
    previewStatusMsg.value = 'Đang gửi yêu cầu...';
    suggestions.value = [];
    selectedSuggestion.value = null;
    previewPlatform.value = '';
    previewSocialAccount.value = null;
    form.initial_content = '';
    form.initial_media = [];

    const payload = {
        ai_content_mode: aiContentMode.value,
        source_post_id:
            sourceInputMode.value === 'select' ? form.source_post_id : '',
        manual_source_content:
            sourceInputMode.value === 'manual' ? manualSourceContent.value : '',
        manual_source_media:
            sourceInputMode.value === 'manual' ? manualSourceMedia.value : [],
        target_social_account_ids: form.target_social_account_ids,
        theme: form.theme,
        prompt: form.prompt,
        image_prompt: form.image_prompt,
        ai_image_count: form.ai_image_count,
        ai_image_style: form.ai_image_style,
        ai_image_resolution: form.ai_image_resolution,
        ai_image_aspect_ratio: form.ai_image_aspect_ratio,
        ai_logo_path: form.ai_logo_path,
        video_scenes: null,
        video_hook: form.video_hook,
        video_target_duration: form.video_target_duration,
        character_enabled: form.character_enabled,
        character_id: form.character_id,
        character_name: form.character_enabled ? form.character_name : '',
        character_dna: form.character_enabled ? form.character_dna_prompt : '',
        character_avatar: form.character_enabled ? form.character_avatar : '',
    };

    try {
        const response = await fetch(preview.url(), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        const payloadJson = await response.json();

        if (!response.ok) {
            previewError.value =
                payloadJson.message ||
                'Không tạo được gợi ý. Vui lòng thử lại.';
            return;
        }

        previewPlatform.value = payloadJson.platform ?? '';
        previewSocialAccount.value = payloadJson.social_account ?? null;

        const taskId = payloadJson.task_id;
        await pollPreviewStatus(taskId);
    } catch (err: any) {
        previewError.value =
            err.message || 'Không kết nối được AI để tạo gợi ý.';
    } finally {
        previewing.value = false;
    }
};

const pollPreviewStatus = async (taskId: string) => {
    previewStatusMsg.value = 'Đang khởi tạo tác vụ vẽ ảnh và viết bài ngầm...';
    const interval = 2000;
    const maxAttempts = 60; // 2 minutes
    let attempts = 0;

    return new Promise<void>((resolve, reject) => {
        const checkStatus = async () => {
            attempts++;
            if (attempts > maxAttempts) {
                previewError.value =
                    'Quá thời gian chờ tạo gợi ý. Vui lòng thử lại.';
                reject(new Error('Timeout'));
                return;
            }

            try {
                const response = await fetch(
                    `/content-clones/preview-status/${taskId}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (!response.ok) {
                    previewError.value =
                        'Có lỗi xảy ra khi kiểm tra trạng thái tác vụ.';
                    reject(new Error('Network error'));
                    return;
                }

                const data = await response.json();

                if (data.status === 'processing') {
                    previewStatusMsg.value =
                        aiContentMode.value === 'video_ai'
                            ? 'AI đang biên kịch phân cảnh và tạo prompt video...'
                            : 'AI đang thiết kế poster và viết nội dung King Coffee...';
                } else if (data.status === 'completed') {
                    suggestions.value = Array.isArray(data.suggestions)
                        ? data.suggestions.map(
                              (suggestion: any): PreviewSuggestion => {
                                  return {
                                      content: suggestion.content,
                                      media: Array.isArray(suggestion.media)
                                          ? suggestion.media
                                          : [],
                                      provider:
                                          suggestion.provider ?? 'default',
                                      ai_images_failed:
                                          suggestion.ai_images_failed,
                                  };
                              },
                          )
                        : [];

                    selectedSuggestion.value = suggestions.value[0] ?? null;
                    form.initial_content =
                        selectedSuggestion.value?.content ?? '';
                    form.initial_media = selectedSuggestion.value?.media ?? [];

                    const firstSuggestion = data.suggestions?.[0];
                    if (
                        firstSuggestion?.video_scenes &&
                        Array.isArray(firstSuggestion.video_scenes) &&
                        firstSuggestion.video_scenes.length > 0
                    ) {
                        form.video_scenes = firstSuggestion.video_scenes;
                    }

                    if (!suggestions.value.length) {
                        previewError.value = 'AI chưa trả về gợi ý hợp lệ.';
                    }
                    resolve();
                    return;
                } else if (data.status === 'failed') {
                    previewError.value = data.error || 'AI tạo gợi ý thất bại.';
                    reject(new Error(data.error));
                    return;
                }

                setTimeout(checkStatus, interval);
            } catch (err) {
                previewError.value =
                    'Không kết nối được để lấy trạng thái tác vụ.';
                reject(err);
            }
        };

        checkStatus();
    });
};

const handleManualMediaUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (!target.files?.length) return;

    manualMediaUploading.value = true;
    try {
        for (let i = 0; i < target.files.length; i++) {
            const file = target.files[i];
            const formData = new FormData();
            formData.append('media', file);

            const response = await fetch('/assets', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            if (response.ok) {
                const data = await response.json();
                const media = data.data || data;
                manualSourceMedia.value.push({
                    id: String(media.id || Date.now() + i),
                    url: media.url,
                    path: media.path || media.url,
                    type: media.type || 'image',
                    mime_type: media.mime_type || 'image/jpeg',
                    original_filename: media.original_filename || file.name,
                });
            }
        }
    } catch (err) {
        alert('Tải ảnh sản phẩm thất bại. Vui lòng thử lại.');
    } finally {
        manualMediaUploading.value = false;
        target.value = '';
    }
};

const removeManualMedia = (index: number) => {
    manualSourceMedia.value.splice(index, 1);
};

const handleLogoUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (!target.files?.length) return;

    const file = target.files[0];
    logoUploading.value = true;

    const formData = new FormData();
    formData.append('media', file);

    try {
        const response = await fetch('/assets', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        if (!response.ok) {
            throw new Error('Upload failed');
        }

        const data = await response.json();
        const media = data.data || data;
        form.ai_logo_path = media.path || media.url;
        form.ai_logo_url = media.url;
    } catch (err) {
        alert('Tải logo lên thất bại. Vui lòng thử lại.');
    } finally {
        logoUploading.value = false;
        target.value = '';
    }
};

const characterAvatarUploading = ref(false);
const handleCharacterAvatarUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (!target.files?.length) return;

    const file = target.files[0];
    characterAvatarUploading.value = true;

    const formData = new FormData();
    formData.append('media', file);

    try {
        const response = await fetch('/assets', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        if (!response.ok) {
            throw new Error('Upload failed');
        }

        const data = await response.json();
        const media = data.data || data;
        form.character_avatar = media.url;
        form.character_id = 'custom';
    } catch (err) {
        alert('Tải ảnh nhân vật lên thất bại. Vui lòng thử lại.');
    } finally {
        characterAvatarUploading.value = false;
        target.value = '';
    }
};

const useCharacterAvatarAsSceneImage = (index: number) => {
    if (!form.character_avatar || !form.video_scenes[index]) return;
    form.video_scenes[index].start_image = form.character_avatar;
    form.video_scenes[index].end_image = form.character_avatar;
};

const applyCharacterAvatarToAllScenes = (overwrite = false) => {
    if (!form.character_avatar) return;
    form.video_scenes.forEach((scene) => {
        if (overwrite || !scene.start_image) {
            scene.start_image = form.character_avatar;
            scene.end_image = form.character_avatar;
        }
    });
};

const sceneImageUploading = ref<Record<string, boolean>>({});

const bgmUploading = ref(false);
const bgmAudioRef = ref<HTMLAudioElement | null>(null);
const isMuted = ref(false);

interface BgmTrackOption {
    id: string;
    title: string;
    artist: string;
    duration: string;
    category: string;
    url: string;
}

const presetBgmTracks: BgmTrackOption[] = [
    {
        id: 'king_coffee_luxury',
        title: 'King Coffee Luxury Anthem',
        artist: 'King Audio Studio',
        duration: '0:30',
        category: 'Sang trọng',
        url: 'https://cdn.pixabay.com/download/audio/2022/05/27/audio_1808fbf07a.mp3?filename=luxury-113271.mp3',
    },
    {
        id: 'acoustic_coffee',
        title: 'Acoustic Morning Chill',
        artist: 'Acoustic Vibes',
        duration: '0:25',
        category: 'Thư giãn',
        url: 'https://cdn.pixabay.com/download/audio/2022/11/06/audio_c36bfb1740.mp3?filename=chill-acoustic-124976.mp3',
    },
    {
        id: 'trending_tiktok',
        title: 'Trending Viral Beat 2026',
        artist: 'TikTok Creator Hub',
        duration: '0:20',
        category: 'Sôi động',
        url: 'https://cdn.pixabay.com/download/audio/2022/01/18/audio_d0a13f69d2.mp3?filename=beat-hip-hop-10777.mp3',
    },
    {
        id: 'inspiring_piano',
        title: 'Inspiring Corporate Piano',
        artist: 'Commercial Maestro',
        duration: '0:30',
        category: 'Cảm hứng',
        url: 'https://cdn.pixabay.com/download/audio/2022/03/15/audio_c8c8a73467.mp3?filename=inspiring-cinematic-piano-10714.mp3',
    },
];

const currentBgmUrl = computed(() => {
    if (form.video_bgm_track === 'custom_upload') {
        return form.video_bgm_url;
    }
    const found = presetBgmTracks.find((t) => t.id === form.video_bgm_track);
    return found?.url || '';
});

const selectBgmTrack = (trackId: string) => {
    form.video_bgm_track = trackId;
    if (bgmAudioRef.value) {
        bgmAudioRef.value.currentTime = 0;
        if (isPlayingVideo.value) {
            bgmAudioRef.value.play().catch(() => {});
        }
    }
};

const toggleMute = () => {
    isMuted.value = !isMuted.value;
    if (bgmAudioRef.value) {
        bgmAudioRef.value.muted = isMuted.value;
    }
};

watch(
    () => form.video_bgm_volume,
    (vol) => {
        if (bgmAudioRef.value) {
            bgmAudioRef.value.volume = isMuted.value
                ? 0
                : Math.max(0, Math.min(1, vol / 100));
        }
    },
);

// Drag and drop timeline state
const draggedSceneIndex = ref<number | null>(null);
const dragOverSceneIndex = ref<number | null>(null);

const onSceneDragStart = (index: number, event: DragEvent) => {
    draggedSceneIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
};

const onSceneDragOver = (index: number, event: DragEvent) => {
    event.preventDefault();
    dragOverSceneIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
};

const onSceneDragLeave = (index: number) => {
    if (dragOverSceneIndex.value === index) {
        dragOverSceneIndex.value = null;
    }
};

const onSceneDrop = (targetIndex: number, event: DragEvent) => {
    event.preventDefault();
    const fromIndex = draggedSceneIndex.value;
    if (
        fromIndex !== null &&
        fromIndex !== targetIndex &&
        fromIndex >= 0 &&
        fromIndex < form.video_scenes.length
    ) {
        const [movedItem] = form.video_scenes.splice(fromIndex, 1);
        form.video_scenes.splice(targetIndex, 0, movedItem);
        activePreviewSceneIndex.value = targetIndex;
    }
    draggedSceneIndex.value = null;
    dragOverSceneIndex.value = null;
};

const onSceneDragEnd = () => {
    draggedSceneIndex.value = null;
    dragOverSceneIndex.value = null;
};

const adjustSceneDuration = (index: number, delta: number) => {
    const current = Number(form.video_scenes[index]?.duration) || 8;
    form.video_scenes[index].duration = Math.min(
        60,
        Math.max(1, current + delta),
    );
};

const duplicateScene = (index: number) => {
    const target = form.video_scenes[index];
    if (!target) return;
    const cloned = {
        ...target,
        context_prompt: `${target.context_prompt} (Bản sao)`,
    };
    form.video_scenes.splice(index + 1, 0, cloned);
    activePreviewSceneIndex.value = index + 1;
};

const activePreviewSceneIndex = ref(0);
const isPlayingVideo = ref(false);
const sceneProgress = ref(0);
let videoPlayTimer: any = null;
let sceneProgressTimer: any = null;

const totalVideoDuration = computed(() => {
    return form.video_scenes.reduce(
        (acc, s) => acc + (Number(s.duration) || 0),
        0,
    );
});

const currentPlaybackSeconds = computed(() => {
    let prev = 0;
    for (let i = 0; i < activePreviewSceneIndex.value; i++) {
        prev += Number(form.video_scenes[i]?.duration) || 0;
    }
    const currentDur =
        Number(form.video_scenes[activePreviewSceneIndex.value]?.duration) || 8;
    return prev + (currentDur * sceneProgress.value) / 100;
});

const formattedTimecode = computed(() => {
    const pad = (n: number) => Math.floor(n).toString().padStart(2, '0');
    const curSec = Math.floor(currentPlaybackSeconds.value);
    const totSec = Math.floor(totalVideoDuration.value);
    return `00:${pad(curSec)} / 00:${pad(totSec)}`;
});

const currentPreviewScene = computed(() => {
    return (
        form.video_scenes[activePreviewSceneIndex.value] ||
        form.video_scenes[0] ||
        null
    );
});

const addVideoScene = () => {
    const reviewer = form.character_name || 'Reviewer King Coffee';
    const avatar = form.character_avatar || '';
    const sceneNum = form.video_scenes.length + 1;
    form.video_scenes.push({
        duration: 8,
        context_prompt: `Cinematic 8K commercial video product review scene ${sceneNum}. Reviewer ${reviewer} holding King Coffee product in modern luxury cafe lounge with dramatic warm lighting.`,
        action_prompt: `0s-3s: Smooth push-in zoom into reviewer ${reviewer} holding King Coffee product. 3s-6s: Reviewer speaks expressively with synchronized lip-sync. 6s-8s: Gentle orbital pan framing crisp packaging details.`,
        start_image: avatar,
        end_image: avatar,
        transition: 'cross_dissolve',
        voiceover_text: `Cùng trải nghiệm hương vị cà phê King Coffee hảo hạng để đánh thức trọn vẹn nguồn năng lượng cho ngày mới!`,
    });
    activePreviewSceneIndex.value = form.video_scenes.length - 1;
};

const removeVideoScene = (index: number) => {
    if (form.video_scenes.length > 1) {
        form.video_scenes.splice(index, 1);
        if (activePreviewSceneIndex.value >= form.video_scenes.length) {
            activePreviewSceneIndex.value = Math.max(
                0,
                form.video_scenes.length - 1,
            );
        }
    }
};

const selectPreviewScene = (index: number) => {
    activePreviewSceneIndex.value = index;
    sceneProgress.value = 0;
    if (isPlayingVideo.value) {
        startScenePlayback();
    }
};

const startScenePlayback = () => {
    if (videoPlayTimer) clearTimeout(videoPlayTimer);
    if (sceneProgressTimer) clearInterval(sceneProgressTimer);

    sceneProgress.value = 0;
    const durSeconds =
        Number(form.video_scenes[activePreviewSceneIndex.value]?.duration) || 4;
    const durMs = durSeconds * 1000;
    const stepInterval = 50;
    const increment = (stepInterval / durMs) * 100;

    sceneProgressTimer = setInterval(() => {
        sceneProgress.value = Math.min(100, sceneProgress.value + increment);
    }, stepInterval);

    videoPlayTimer = setTimeout(() => {
        clearInterval(sceneProgressTimer);
        sceneProgress.value = 100;
        playNextScene();
    }, durMs);
};

const playNextScene = () => {
    if (!isPlayingVideo.value) return;
    if (activePreviewSceneIndex.value < form.video_scenes.length - 1) {
        activePreviewSceneIndex.value++;
    } else {
        activePreviewSceneIndex.value = 0;
        if (bgmAudioRef.value) {
            bgmAudioRef.value.currentTime = 0;
        }
    }
    startScenePlayback();
};

const toggleVideoPlay = () => {
    if (isPlayingVideo.value) {
        isPlayingVideo.value = false;
        if (videoPlayTimer) clearTimeout(videoPlayTimer);
        if (sceneProgressTimer) clearInterval(sceneProgressTimer);
        if (bgmAudioRef.value) {
            bgmAudioRef.value.pause();
        }
    } else {
        isPlayingVideo.value = true;
        if (bgmAudioRef.value && currentBgmUrl.value) {
            bgmAudioRef.value.volume = isMuted.value
                ? 0
                : Math.max(0, Math.min(1, form.video_bgm_volume / 100));
            bgmAudioRef.value.play().catch(() => {});
        }
        startScenePlayback();
    }
};

onUnmounted(() => {
    if (videoPlayTimer) clearTimeout(videoPlayTimer);
    if (sceneProgressTimer) clearInterval(sceneProgressTimer);
    if (bgmAudioRef.value) bgmAudioRef.value.pause();
});

const handleBgmUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (!target.files?.length) return;

    const file = target.files[0];
    bgmUploading.value = true;

    const formData = new FormData();
    formData.append('media', file);

    try {
        const response = await fetch('/assets', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        if (!response.ok) throw new Error('Upload audio failed');

        const data = await response.json();
        const media = data.data || data;
        form.video_bgm_url = media.url;
        form.video_bgm_track = 'custom_upload';
    } catch (err) {
        alert(
            'Tải nhạc nền lên thất bại. Vui lòng kiểm tra định dạng file (MP3, WAV, M4A).',
        );
    } finally {
        bgmUploading.value = false;
        target.value = '';
    }
};

const handleVideoImageUpload = async (
    event: Event,
    index: number,
    type: 'start' | 'end',
) => {
    const target = event.target as HTMLInputElement;
    if (!target.files?.length) return;

    const file = target.files[0];
    const key = `${index}_${type}`;
    sceneImageUploading.value[key] = true;

    const formData = new FormData();
    formData.append('media', file);

    try {
        const response = await fetch('/assets', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        if (!response.ok) {
            throw new Error('Upload failed');
        }

        const data = await response.json();
        const media = data.data || data;

        if (type === 'start') {
            form.video_scenes[index].start_image = media.url;
        } else {
            form.video_scenes[index].end_image = media.url;
        }
    } catch (err) {
        alert('Tải ảnh lên thất bại. Vui lòng thử lại.');
    } finally {
        sceneImageUploading.value[key] = false;
        target.value = '';
    }
};

const removeLogo = () => {
    form.ai_logo_path = '';
    form.ai_logo_url = '';
};

const chooseSuggestion = (suggestion: PreviewSuggestion) => {
    selectedSuggestion.value = suggestion;
    form.initial_content = suggestion.content;
    form.initial_media = suggestion.media;
};

watch(
    () => [
        form.source_post_id,
        manualSourceContent.value,
        sourceInputMode.value,
        form.target_social_account_ids.join(','),
        form.theme,
        form.prompt,
    ],
    () => {
        suggestions.value = [];
        selectedSuggestion.value = null;
        previewPlatform.value = '';
        previewSocialAccount.value = null;
        form.initial_content = '';
        form.initial_media = [];
        previewError.value = '';
    },
);

const stop = (campaign: Campaign) => {
    router.delete(destroy.url(campaign.id));
};

const statusLabel = (post: ClonePost): string => {
    if (post.status === 'published') return 'Đã đăng';
    if (post.status === 'partially_published') return 'Đăng một phần';
    if (post.status === 'publishing') return 'Đang đăng';
    if (post.workflow_status === 'pending_review') return 'Chờ duyệt';
    if (post.workflow_status === 'rejected') return 'Cần chỉnh sửa';
    if (post.status === 'scheduled') return 'Đã lên lịch';
    if (post.status === 'failed') return 'Đăng lỗi';
    return 'Bản nháp';
};

const statusClass = (post: ClonePost): string => {
    if (['published', 'partially_published'].includes(post.status))
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400';
    if (post.workflow_status === 'pending_review')
        return 'bg-amber-100 text-amber-800 dark:bg-amber-950/30 dark:text-amber-400';
    if (post.status === 'failed' || post.workflow_status === 'rejected')
        return 'bg-rose-100 text-rose-800 dark:bg-rose-950/30 dark:text-rose-400';
    return 'bg-muted text-muted-foreground';
};

const formatDate = (value: string | null): string => {
    if (!value) return 'Chưa xác định';
    return new Date(value).toLocaleString('vi-VN', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const targetNames = (post: ClonePost): string =>
    post.post_platforms
        .map(
            (platform) =>
                platform.social_account?.display_name ||
                platform.platform_name ||
                'Page',
        )
        .join(', ');

// --- Campaign Search & Filters (Tab 2) ---
const campaignSearchTerm = ref('');
const campaignStatusFilter = ref('all');

const filteredCampaigns = computed(() => {
    return props.campaigns.filter((campaign) => {
        const name = campaign.theme || 'Clone từ bài nguồn';
        const content = campaign.source_post?.content || '';
        const matchesSearch =
            !campaignSearchTerm.value ||
            name
                .toLowerCase()
                .includes(campaignSearchTerm.value.toLowerCase()) ||
            content
                .toLowerCase()
                .includes(campaignSearchTerm.value.toLowerCase());

        let matchesStatus = true;
        if (campaignStatusFilter.value === 'active') {
            matchesStatus = campaign.is_active;
        } else if (campaignStatusFilter.value === 'inactive') {
            matchesStatus = !campaign.is_active;
        }

        return matchesSearch && matchesStatus;
    });
});

// --- Clone History Search, Filters & Pagination (Tab 3) ---
const searchTerm = ref('');
const statusFilter = ref('all');
const currentPage = ref(1);
const perPage = ref(10);

const allPosts = computed(() => {
    const list: Array<
        ClonePost & {
            campaignTheme: string;
            campaignId: string;
            campaignRequireApproval: boolean;
        }
    > = [];
    props.campaigns.forEach((campaign) => {
        campaign.posts.forEach((post) => {
            list.push({
                ...post,
                campaignTheme: campaign.theme || 'Clone từ bài nguồn',
                campaignId: campaign.id,
                campaignRequireApproval: campaign.require_approval,
            });
        });
    });
    return list;
});

const filteredPosts = computed(() => {
    return allPosts.value.filter((post) => {
        const matchesSearch =
            !searchTerm.value ||
            post.content.toLowerCase().includes(searchTerm.value.toLowerCase());

        let matchesStatus = true;
        if (statusFilter.value !== 'all') {
            if (statusFilter.value === 'published') {
                matchesStatus = ['published', 'partially_published'].includes(
                    post.status,
                );
            } else if (statusFilter.value === 'pending_review') {
                matchesStatus = post.workflow_status === 'pending_review';
            } else if (statusFilter.value === 'rejected') {
                matchesStatus = post.workflow_status === 'rejected';
            } else if (statusFilter.value === 'scheduled') {
                matchesStatus = post.status === 'scheduled';
            } else if (statusFilter.value === 'failed') {
                matchesStatus = post.status === 'failed';
            }
        }

        return matchesSearch && matchesStatus;
    });
});

const paginatedPosts = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    const end = start + perPage.value;
    return filteredPosts.value.slice(start, end);
});

const totalPages = computed(() => {
    return Math.ceil(filteredPosts.value.length / perPage.value) || 1;
});

watch([searchTerm, statusFilter, perPage], () => {
    currentPage.value = 1;
});
</script>

<template>
    <Head title="Clone nội dung" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Clone nội dung theo chiến dịch"
                description="Dùng một bài nguồn để tạo biến thể mới, gửi duyệt và đăng tự động lên nhiều page theo lịch biểu khoa học."
            />

            <!-- Card thông báo đồng bộ hóa chỉ áp dụng cho Facebook -->
            <div
                class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/50 p-4 text-amber-900 shadow-2xs dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300"
            >
                <IconInfoCircle
                    class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400"
                />
                <div class="text-xs leading-relaxed">
                    <p class="text-sm font-bold">
                        Lưu ý về đồng bộ hóa bài viết nguồn
                    </p>
                    <p class="mt-0.5 text-muted-foreground">
                        Chức năng đồng bộ hoá tự động danh sách bài viết nguồn
                        hiện tại
                        <strong>chỉ áp dụng cho nền tảng Facebook</strong>. Với
                        các nền tảng khác, bạn có thể sử dụng tùy chọn
                        <em>"Tự nhập nội dung thủ công"</em> để AI lấy làm dữ
                        liệu tạo chiến dịch clone bài viết.
                    </p>
                </div>
            </div>

            <Tabs v-model="activeTab" class="w-full space-y-6">
                <div
                    class="flex flex-col gap-4 border-b pb-1 sm:flex-row sm:items-center sm:justify-between"
                >
                    <TabsList class="rounded-lg bg-muted p-1">
                        <TabsTrigger
                            value="create"
                            class="flex items-center gap-2"
                        >
                            <IconCopy class="size-4" />
                            <span>Tạo chiến dịch</span>
                        </TabsTrigger>
                        <TabsTrigger
                            value="campaigns"
                            class="flex items-center gap-2"
                        >
                            <IconCalendar class="size-4" />
                            <span>Chiến dịch đang chạy</span>
                            <span
                                v-if="
                                    campaigns.filter((c) => c.is_active).length
                                "
                                class="text-2xs inline-flex items-center justify-center rounded-full bg-primary px-2 py-0.5 font-semibold text-primary-foreground"
                            >
                                {{
                                    campaigns.filter((c) => c.is_active).length
                                }}
                            </span>
                        </TabsTrigger>
                        <TabsTrigger
                            value="history"
                            class="flex items-center gap-2"
                        >
                            <IconHistory class="size-4" />
                            <span>Lịch sử clone</span>
                            <span
                                v-if="allPosts.length"
                                class="text-2xs inline-flex items-center justify-center rounded-full bg-muted-foreground/20 px-2 py-0.5 font-semibold text-foreground"
                            >
                                {{ allPosts.length }}
                            </span>
                        </TabsTrigger>
                    </TabsList>
                </div>

                <!-- TAB 1: TẠO CHIẾN DỊCH -->
                <TabsContent value="create" class="mt-0">
                    <div
                        :class="
                            currentStep === 2 && aiContentMode === 'video_ai'
                                ? 'mx-auto w-full max-w-7xl space-y-6'
                                : 'grid items-start gap-6 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,0.95fr)]'
                        "
                    >
                        <!-- Cột cấu hình (Wizard / Form) -->
                        <form class="space-y-6" @submit.prevent="submit">
                            <!-- Stepper Header Navigation -->
                            <div
                                class="rounded-2xl border bg-card p-4 shadow-2xs"
                            >
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        type="button"
                                        class="flex cursor-pointer items-center gap-2 rounded-xl border p-2.5 text-left transition"
                                        :class="
                                            currentStep === 1
                                                ? 'border-amber-500/40 bg-amber-500/10 text-amber-900 shadow-2xs dark:text-amber-300'
                                                : 'border-transparent text-muted-foreground hover:bg-muted/40'
                                        "
                                        @click="currentStep = 1"
                                    >
                                        <div
                                            class="flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                                            :class="
                                                currentStep === 1
                                                    ? 'bg-amber-500 text-white'
                                                    : 'bg-muted text-foreground'
                                            "
                                        >
                                            1
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-xs font-bold"
                                            >
                                                Nguồn & Kênh
                                            </p>
                                            <p
                                                class="text-3xs hidden truncate text-muted-foreground sm:block"
                                            >
                                                Chọn bài & page đích
                                            </p>
                                        </div>
                                    </button>

                                    <button
                                        type="button"
                                        class="flex cursor-pointer items-center gap-2 rounded-xl border p-2.5 text-left transition"
                                        :class="
                                            currentStep === 2
                                                ? 'border-amber-500/40 bg-amber-500/10 text-amber-900 shadow-2xs dark:text-amber-300'
                                                : 'border-transparent text-muted-foreground hover:bg-muted/40'
                                        "
                                        @click="currentStep = 2"
                                    >
                                        <div
                                            class="flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                                            :class="
                                                currentStep === 2
                                                    ? 'bg-amber-500 text-white'
                                                    : 'bg-muted text-foreground'
                                            "
                                        >
                                            2
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-xs font-bold"
                                            >
                                                AI Creative Suite
                                            </p>
                                            <p
                                                class="text-3xs hidden truncate text-muted-foreground sm:block"
                                            >
                                                Ảnh / Video & Prompt
                                            </p>
                                        </div>
                                    </button>

                                    <button
                                        type="button"
                                        class="flex cursor-pointer items-center gap-2 rounded-xl border p-2.5 text-left transition"
                                        :class="
                                            currentStep === 3
                                                ? 'border-amber-500/40 bg-amber-500/10 text-amber-900 shadow-2xs dark:text-amber-300'
                                                : 'border-transparent text-muted-foreground hover:bg-muted/40'
                                        "
                                        @click="currentStep = 3"
                                    >
                                        <div
                                            class="flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                                            :class="
                                                currentStep === 3
                                                    ? 'bg-amber-500 text-white'
                                                    : 'bg-muted text-foreground'
                                            "
                                        >
                                            3
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-xs font-bold"
                                            >
                                                Lịch trình & Đăng
                                            </p>
                                            <p
                                                class="text-3xs hidden truncate text-muted-foreground sm:block"
                                            >
                                                Số bài & chu kỳ
                                            </p>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- BƯỚC 1: NGUỒN VÀ KÊNH ĐÍCH -->
                            <div
                                v-show="currentStep === 1"
                                class="space-y-6 rounded-2xl border bg-card p-5 shadow-2xs sm:p-6"
                            >
                                <div
                                    class="flex items-center gap-3 border-b pb-4"
                                >
                                    <div
                                        class="flex size-10 items-center justify-center rounded-xl bg-primary font-bold text-primary-foreground shadow-xs"
                                    >
                                        <IconSparkles class="size-5" />
                                    </div>
                                    <div>
                                        <h2
                                            class="text-base font-bold text-foreground"
                                        >
                                            Bước 1: Chọn Chế độ Sáng tạo & Nguồn
                                            nội dung
                                        </h2>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Xác định loại nội dung cần tạo và
                                            nguồn dữ liệu đầu vào cho AI.
                                        </p>
                                    </div>
                                </div>

                                <!-- Segmented Card Switcher cho AI Mode -->
                                <div class="space-y-2">
                                    <span
                                        class="text-xs font-bold text-foreground"
                                        >Loại nội dung AI muốn tạo</span
                                    >
                                    <div
                                        class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                                    >
                                        <div
                                            class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border p-4 transition select-none"
                                            :class="
                                                aiContentMode === 'text_image'
                                                    ? 'border-primary bg-primary/5 shadow-xs ring-1 ring-primary/30'
                                                    : 'border-border/70 bg-card hover:bg-muted/30'
                                            "
                                            @click="
                                                aiContentMode = 'text_image'
                                            "
                                        >
                                            <div class="space-y-1">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <span
                                                        class="text-sm font-bold text-foreground"
                                                        >Bài viết & Ảnh AI</span
                                                    >
                                                    <Badge
                                                        variant="outline"
                                                        class="text-3xs font-medium"
                                                        :class="
                                                            aiContentMode ===
                                                            'text_image'
                                                                ? 'border-primary/40 bg-primary/10 text-primary'
                                                                : 'border-border text-muted-foreground'
                                                        "
                                                        >AIDA Copywriting</Badge
                                                    >
                                                </div>
                                                <p
                                                    class="text-xs leading-relaxed text-muted-foreground"
                                                >
                                                    Viết bài đa nền tảng và vẽ
                                                    bộ ảnh quảng cáo thương mại.
                                                </p>
                                            </div>
                                            <div
                                                class="flex size-5 shrink-0 items-center justify-center rounded-full border"
                                                :class="
                                                    aiContentMode ===
                                                    'text_image'
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-muted-foreground/30'
                                                "
                                            >
                                                <div
                                                    v-if="
                                                        aiContentMode ===
                                                        'text_image'
                                                    "
                                                    class="size-2 rounded-full bg-white"
                                                />
                                            </div>
                                        </div>

                                        <div
                                            class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border p-4 transition select-none"
                                            :class="
                                                aiContentMode === 'video_ai'
                                                    ? 'border-primary bg-primary/5 shadow-xs ring-1 ring-primary/30'
                                                    : 'border-border/70 bg-card hover:bg-muted/30'
                                            "
                                            @click="aiContentMode = 'video_ai'"
                                        >
                                            <div class="space-y-1">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <span
                                                        class="text-sm font-bold text-foreground"
                                                        >Studio Video AI</span
                                                    >
                                                    <Badge
                                                        variant="outline"
                                                        class="text-3xs font-medium"
                                                        :class="
                                                            aiContentMode ===
                                                            'video_ai'
                                                                ? 'border-primary/40 bg-primary/10 text-primary'
                                                                : 'border-border text-muted-foreground'
                                                        "
                                                        >Phim ngắn 8s/cảnh</Badge
                                                    >
                                                </div>
                                                <p
                                                    class="text-xs leading-relaxed text-muted-foreground"
                                                >
                                                    Biên kịch phân cảnh, lồng
                                                    tiếng thoại và chuyển động
                                                    camera.
                                                </p>
                                            </div>
                                            <div
                                                class="flex size-5 shrink-0 items-center justify-center rounded-full border"
                                                :class="
                                                    aiContentMode === 'video_ai'
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-muted-foreground/30'
                                                "
                                            >
                                                <div
                                                    v-if="
                                                        aiContentMode ===
                                                        'video_ai'
                                                    "
                                                    class="size-2 rounded-full bg-white"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cách chọn bài viết nguồn -->
                                <div class="space-y-3 border-t pt-2">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="text-xs font-bold text-foreground"
                                            >Nguồn dữ liệu bài viết</span
                                        >
                                        <div
                                            class="flex gap-2 rounded-lg bg-muted p-1"
                                        >
                                            <button
                                                type="button"
                                                class="cursor-pointer rounded-md px-3 py-1 text-xs font-bold transition"
                                                :class="
                                                    sourceInputMode === 'select'
                                                        ? 'bg-background text-foreground shadow-2xs'
                                                        : 'text-muted-foreground hover:text-foreground'
                                                "
                                                @click="
                                                    sourceInputMode = 'select'
                                                "
                                            >
                                                Bài viết có sẵn
                                            </button>
                                            <button
                                                type="button"
                                                class="cursor-pointer rounded-md px-3 py-1 text-xs font-bold transition"
                                                :class="
                                                    sourceInputMode === 'manual'
                                                        ? 'bg-background text-foreground shadow-2xs'
                                                        : 'text-muted-foreground hover:text-foreground'
                                                "
                                                @click="
                                                    sourceInputMode = 'manual'
                                                "
                                            >
                                                ✍️ Tự nhập thủ công
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Option A: Chọn từ danh sách hệ thống -->
                                    <div
                                        v-if="sourceInputMode === 'select'"
                                        class="space-y-3 rounded-xl border bg-muted/15 p-4"
                                    >
                                        <div
                                            class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                                        >
                                            <label
                                                class="block space-y-1 text-xs font-semibold text-muted-foreground"
                                            >
                                                Lọc nền tảng
                                                <select
                                                    v-model="
                                                        sourcePlatformFilter
                                                    "
                                                    class="h-9 w-full rounded-md border bg-background px-2 text-xs focus:ring-primary"
                                                >
                                                    <option value="all">
                                                        Tất cả nền tảng
                                                    </option>
                                                    <option
                                                        v-for="platform in sourcePlatforms"
                                                        :key="platform"
                                                        :value="platform"
                                                    >
                                                        {{
                                                            platform.toUpperCase()
                                                        }}
                                                    </option>
                                                </select>
                                            </label>

                                            <label
                                                class="block space-y-1 text-xs font-semibold text-muted-foreground"
                                            >
                                                Lọc Page nguồn
                                                <select
                                                    v-model="sourcePageFilter"
                                                    class="h-9 w-full rounded-md border bg-background px-2 text-xs focus:ring-primary"
                                                >
                                                    <option value="all">
                                                        Tất cả các Page
                                                    </option>
                                                    <option
                                                        v-for="account in sourceSocialAccounts"
                                                        :key="account.id"
                                                        :value="account.id"
                                                    >
                                                        {{
                                                            account.display_name ||
                                                            account.username
                                                        }}
                                                    </option>
                                                </select>
                                            </label>
                                        </div>

                                        <label
                                            class="block space-y-1.5 pt-1 text-xs font-semibold"
                                        >
                                            Chọn bài viết mẫu từ hệ thống
                                            <select
                                                v-model="form.source_post_id"
                                                class="h-10 w-full rounded-md border bg-background px-3 text-xs font-medium focus:ring-primary"
                                            >
                                                <option
                                                    v-if="
                                                        !filteredSourcePosts.length
                                                    "
                                                    value=""
                                                    disabled
                                                >
                                                    Không tìm thấy bài viết nào
                                                    khớp bộ lọc
                                                </option>
                                                <option
                                                    v-for="post in filteredSourcePosts"
                                                    :key="post.id"
                                                    :value="post.id"
                                                >
                                                    {{
                                                        post.content.length > 90
                                                            ? post.content.slice(
                                                                  0,
                                                                  90,
                                                              ) + '...'
                                                            : post.content
                                                    }}
                                                </option>
                                            </select>
                                        </label>

                                        <!-- Media đính kèm từ bài gốc -->
                                        <div
                                            v-if="
                                                selectedSourcePost &&
                                                selectedSourcePost.media &&
                                                selectedSourcePost.media.length
                                            "
                                            class="space-y-2 border-t border-border/60 pt-2"
                                        >
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <span
                                                    class="flex items-center gap-1 text-xs font-bold text-muted-foreground"
                                                >
                                                    <IconPhoto
                                                        class="size-3.5"
                                                    />
                                                    Ảnh đính kèm từ bài gốc ({{
                                                        selectedMediaUrls.length
                                                    }}/{{
                                                        selectedSourcePost.media
                                                            .length
                                                    }}
                                                    đã chọn)
                                                </span>
                                                <button
                                                    type="button"
                                                    class="text-3xs font-bold text-primary hover:underline"
                                                    @click="
                                                        selectedMediaUrls =
                                                            selectedSourcePost.media.map(
                                                                (m) => m.url,
                                                            )
                                                    "
                                                >
                                                    Chọn tất cả
                                                </button>
                                            </div>
                                            <div
                                                class="flex max-h-[120px] flex-wrap gap-2 overflow-y-auto rounded-lg border bg-background p-1.5"
                                            >
                                                <div
                                                    v-for="media in selectedSourcePost.media"
                                                    :key="media.url"
                                                    class="group relative size-14 cursor-pointer overflow-hidden rounded-lg border"
                                                    @click="
                                                        toggleMediaSelection(
                                                            media.url,
                                                        )
                                                    "
                                                >
                                                    <img
                                                        :src="media.url"
                                                        class="size-full object-cover"
                                                    />
                                                    <div
                                                        class="absolute inset-0 flex items-center justify-center bg-black/40 transition-opacity"
                                                        :class="
                                                            selectedMediaUrls.includes(
                                                                media.url,
                                                            )
                                                                ? 'opacity-100'
                                                                : 'opacity-0 group-hover:opacity-30'
                                                        "
                                                    >
                                                        <IconCheck
                                                            class="size-4 text-white"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Option B: Nhập thủ công + Upload Sản phẩm & Logo -->
                                    <div
                                        v-else
                                        class="space-y-4 rounded-xl border bg-muted/15 p-4"
                                    >
                                        <label
                                            class="block space-y-2 text-xs font-bold text-foreground"
                                        >
                                            Nội dung bài viết nguồn tự nhập (Ý
                                            tưởng / Sản phẩm / Khuyến mãi)
                                            <textarea
                                                v-model="manualSourceContent"
                                                rows="4"
                                                class="w-full rounded-lg border bg-background p-3 text-xs leading-relaxed font-normal focus:ring-primary"
                                                placeholder="Nhập thông tin sản phẩm King Coffee, chương trình ưu đãi, hoặc thông điệp bạn muốn truyền tải..."
                                            />
                                        </label>

                                        <!-- Upload Ảnh Sản Phẩm (Vision Reference) -->
                                        <div
                                            class="space-y-3 border-t border-border/70 pt-3"
                                        >
                                            <div
                                                class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"
                                            >
                                                <div class="space-y-0.5">
                                                    <span
                                                        class="flex items-center gap-1.5 text-xs font-bold text-foreground"
                                                    >
                                                        <IconPhoto
                                                            class="size-4 text-primary"
                                                        />
                                                        <span
                                                            >Ảnh Sản Phẩm Thực
                                                            Tế (`Product` Vision
                                                            Reference)</span
                                                        >
                                                    </span>
                                                    <p
                                                        class="text-3xs text-muted-foreground"
                                                    >
                                                        AI Vision sẽ phân tích
                                                        chi tiết nhãn mác, góc
                                                        chụp và màu sắc để vẽ
                                                        ảnh mới chuẩn xác 100%.
                                                    </p>
                                                </div>

                                                <label
                                                    class="inline-flex cursor-pointer items-center gap-1.5 self-start rounded-xl border border-primary/20 bg-primary/10 px-3 py-1.5 text-xs font-bold text-primary shadow-2xs transition hover:bg-primary/20 sm:self-auto"
                                                >
                                                    <IconPlus
                                                        class="size-3.5"
                                                    />
                                                    <span>{{
                                                        manualMediaUploading
                                                            ? 'Đang tải...'
                                                            : '+ Thêm ảnh sản phẩm'
                                                    }}</span>
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        multiple
                                                        class="hidden"
                                                        :disabled="
                                                            manualMediaUploading
                                                        "
                                                        @change="
                                                            handleManualMediaUpload
                                                        "
                                                    />
                                                </label>
                                            </div>

                                            <!-- Image Gallery or Dropzone when empty -->
                                            <div
                                                v-if="
                                                    manualSourceMedia.length ===
                                                    0
                                                "
                                                class="space-y-1.5 rounded-xl border-2 border-dashed border-border/80 bg-background/50 p-4 text-center transition hover:border-primary/50"
                                            >
                                                <div
                                                    class="mx-auto flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary"
                                                >
                                                    <IconUpload
                                                        class="size-4.5"
                                                    />
                                                </div>
                                                <p
                                                    class="text-xs font-semibold text-foreground"
                                                >
                                                    Chưa có ảnh sản phẩm thực tế
                                                </p>
                                                <p
                                                    class="text-3xs text-muted-foreground"
                                                >
                                                    Bấm "+ Thêm ảnh sản phẩm" ở
                                                    trên để tải ảnh bao bì /
                                                    chai lọ / ly sản phẩm thực
                                                    tế lên làm mẫu cho AI
                                                </p>
                                            </div>

                                            <div
                                                v-else
                                                class="grid grid-cols-3 gap-2.5 pt-1 sm:grid-cols-4 md:grid-cols-5"
                                            >
                                                <div
                                                    v-for="(
                                                        media, idx
                                                    ) in manualSourceMedia"
                                                    :key="media.url"
                                                    class="group relative aspect-square overflow-hidden rounded-xl border-2 border-border/80 bg-background shadow-xs transition hover:border-primary/60"
                                                >
                                                    <img
                                                        :src="media.url"
                                                        class="size-full object-cover transition duration-300 group-hover:scale-105"
                                                    />
                                                    <div
                                                        class="absolute inset-0 flex flex-col justify-between bg-gradient-to-t from-black/70 via-transparent to-black/30 p-1.5 opacity-0 transition group-hover:opacity-100"
                                                    >
                                                        <span
                                                            class="text-4xs self-start rounded-sm bg-black/60 px-1.5 py-0.5 font-bold text-white"
                                                            >#{{
                                                                idx + 1
                                                            }}
                                                            Ref</span
                                                        >
                                                        <button
                                                            type="button"
                                                            class="flex size-6 cursor-pointer items-center justify-center self-end rounded-lg bg-destructive text-xs text-white shadow-sm transition hover:bg-destructive/80"
                                                            title="Xóa ảnh này"
                                                            @click="
                                                                removeManualMedia(
                                                                    idx,
                                                                )
                                                            "
                                                        >
                                                            <IconTrash
                                                                class="size-3.5"
                                                            />
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Upload Logo (Watermark Branding) -->
                                        <div
                                            class="space-y-2.5 border-t border-border/70 pt-3"
                                        >
                                            <div
                                                class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"
                                            >
                                                <div class="space-y-0.5">
                                                    <span
                                                        class="flex items-center gap-1.5 text-xs font-bold text-foreground"
                                                    >
                                                        <IconSparkles
                                                            class="size-4 text-amber-500"
                                                        />
                                                        <span
                                                            >Logo thương hiệu
                                                            (Đóng dấu Watermark
                                                            tự động)</span
                                                        >
                                                    </span>
                                                    <p
                                                        class="text-3xs text-muted-foreground"
                                                    >
                                                        Tự động gắn logo King
                                                        Coffee lên góc ảnh AI
                                                        khi xuất bài.
                                                    </p>
                                                </div>

                                                <label
                                                    class="inline-flex cursor-pointer items-center gap-1.5 self-start rounded-xl border px-3 py-1.5 text-xs font-bold shadow-2xs transition hover:bg-muted/40 sm:self-auto"
                                                >
                                                    <IconPhoto
                                                        class="size-3.5"
                                                    />
                                                    <span>{{
                                                        logoUploading
                                                            ? 'Đang tải...'
                                                            : form.ai_logo_path
                                                              ? 'Đổi logo khác'
                                                              : '+ Tải logo PNG'
                                                    }}</span>
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        class="hidden"
                                                        :disabled="
                                                            logoUploading
                                                        "
                                                        @change="
                                                            handleLogoUpload
                                                        "
                                                    />
                                                </label>
                                            </div>

                                            <div
                                                v-if="form.ai_logo_path"
                                                class="flex items-center justify-between gap-3 rounded-xl border border-amber-500/30 bg-gradient-to-r from-amber-500/10 to-transparent p-3 text-xs"
                                            >
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <div
                                                        class="flex h-9 w-14 items-center justify-center rounded-lg border border-white/10 bg-black/80 p-1 shadow-xs"
                                                    >
                                                        <img
                                                            :src="
                                                                form.ai_logo_url
                                                            "
                                                            class="max-h-full max-w-full object-contain"
                                                        />
                                                    </div>
                                                    <div>
                                                        <div
                                                            class="flex items-center gap-2"
                                                        >
                                                            <span
                                                                class="text-xs font-bold text-foreground"
                                                                >Logo Watermark
                                                                Sẵn Sàng</span
                                                            >
                                                            <Badge
                                                                class="text-4xs border-emerald-300 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300"
                                                                >Đã kích
                                                                hoạt</Badge
                                                            >
                                                        </div>
                                                        <p
                                                            class="text-3xs max-w-[240px] truncate text-muted-foreground"
                                                        >
                                                            {{
                                                                form.ai_logo_path
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    @click="removeLogo"
                                                    class="cursor-pointer rounded-lg px-2.5 py-1 text-xs font-bold text-destructive transition hover:bg-destructive/10"
                                                >
                                                    Gỡ logo
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Danh sách Page đích -->
                                <div class="space-y-3 border-t pt-2">
                                    <div
                                        class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-xs font-bold text-foreground"
                                                >Kênh đích sẽ xuất bản</span
                                            >
                                            <Badge
                                                variant="secondary"
                                                class="text-xs font-bold"
                                            >
                                                {{
                                                    form
                                                        .target_social_account_ids
                                                        .length
                                                }}
                                                /
                                                {{ socialAccounts.length }} kênh
                                            </Badge>
                                        </div>

                                        <div
                                            v-if="socialAccounts.length > 0"
                                            class="flex items-center gap-3"
                                        >
                                            <input
                                                v-model="targetAccountSearch"
                                                type="text"
                                                placeholder="Tìm nhanh page..."
                                                class="text-3xs h-7 w-32 rounded-md border bg-background px-2 sm:w-40"
                                            />
                                            <button
                                                type="button"
                                                class="cursor-pointer text-xs font-bold text-primary hover:underline"
                                                @click="
                                                    form.target_social_account_ids =
                                                        socialAccounts.map(
                                                            (a) => a.id,
                                                        )
                                                "
                                            >
                                                Chọn hết
                                            </button>
                                            <button
                                                type="button"
                                                class="cursor-pointer text-xs text-muted-foreground hover:underline"
                                                @click="
                                                    form.target_social_account_ids =
                                                        []
                                                "
                                            >
                                                Bỏ chọn
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        class="grid max-h-[260px] grid-cols-1 gap-2.5 overflow-y-auto rounded-xl border bg-muted/15 p-2 sm:grid-cols-2"
                                    >
                                        <label
                                            v-for="account in filteredTargetSocialAccounts"
                                            :key="account.id"
                                            class="flex cursor-pointer items-center gap-3 rounded-xl border bg-card p-2.5 shadow-2xs transition select-none hover:bg-muted/40"
                                            :class="
                                                form.target_social_account_ids.includes(
                                                    account.id,
                                                )
                                                    ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                                                    : 'border-border'
                                            "
                                        >
                                            <input
                                                v-model="
                                                    form.target_social_account_ids
                                                "
                                                type="checkbox"
                                                :value="account.id"
                                                class="size-4 shrink-0 rounded border-gray-300 text-primary focus:ring-primary"
                                            />
                                            <div class="relative shrink-0">
                                                <Avatar
                                                    :src="account.avatar_url"
                                                    :name="
                                                        account.display_name ||
                                                        account.username ||
                                                        'Page'
                                                    "
                                                    class="size-8 rounded-full border border-border"
                                                    fallback-class="rounded-full bg-primary/10 text-primary font-bold text-xs"
                                                />
                                            </div>
                                            <div
                                                class="flex min-w-0 flex-1 flex-col"
                                            >
                                                <span
                                                    class="truncate text-xs font-bold text-foreground"
                                                    :title="
                                                        account.display_name ||
                                                        account.username ||
                                                        ''
                                                    "
                                                >
                                                    {{
                                                        account.display_name ||
                                                        account.username
                                                    }}
                                                </span>
                                                <span
                                                    class="text-3xs font-bold tracking-wider text-muted-foreground uppercase"
                                                >
                                                    {{ account.platform }}
                                                </span>
                                            </div>
                                        </label>

                                        <div
                                            v-if="
                                                filteredTargetSocialAccounts.length ===
                                                0
                                            "
                                            class="col-span-full py-6 text-center text-xs text-muted-foreground"
                                        >
                                            Không tìm thấy trang nào khớp từ
                                            khóa.
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-2">
                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-bold text-primary-foreground shadow-xs transition hover:bg-primary/90"
                                        @click="currentStep = 2"
                                    >
                                        <span
                                            >Tiếp theo: Thiết lập AI
                                            Studio</span
                                        >
                                        <IconChevronRight class="size-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- BƯỚC 2: AI CREATIVE SUITE SETTINGS -->
                            <div
                                v-show="currentStep === 2"
                                class="space-y-7 rounded-2xl border bg-card p-5 shadow-2xs sm:p-7"
                            >
                                <!-- Header Step 2 -->
                                <div
                                    class="flex flex-wrap items-center justify-between gap-4 border-b pb-5"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-11 items-center justify-center rounded-2xl bg-amber-500 font-bold text-white shadow-sm"
                                        >
                                            <IconMovie
                                                v-if="
                                                    aiContentMode === 'video_ai'
                                                "
                                                class="size-6"
                                            />
                                            <IconSparkles
                                                v-else
                                                class="size-6"
                                            />
                                        </div>
                                        <div>
                                            <h2
                                                class="text-lg font-bold text-foreground"
                                            >
                                                {{
                                                    aiContentMode === 'video_ai'
                                                        ? 'Bước 2: Studio Video & Kịch Bản Phân Cảnh (CapCut Pro)'
                                                        : 'Bước 2: Cấu hình Sáng tạo King Coffee AI Studio'
                                                }}
                                            </h2>
                                            <p
                                                class="mt-0.5 text-xs text-muted-foreground"
                                            >
                                                {{
                                                    aiContentMode === 'video_ai'
                                                        ? 'Kéo thả phân cảnh, biên kịch lời bình Voiceover, hiệu ứng chuyển cảnh và âm thanh chất lượng cao.'
                                                        : 'Định hướng phong cách, prompt và thông số kỹ thuật cho bài viết / hình ảnh.'
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="inline-flex h-10 transform cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 px-5 text-xs font-bold text-white shadow-sm transition hover:from-amber-600 hover:to-amber-700 active:scale-95 disabled:opacity-60"
                                        :disabled="
                                            previewing ||
                                            (sourceInputMode === 'select'
                                                ? !form.source_post_id
                                                : !manualSourceContent.trim()) ||
                                            !form.target_social_account_ids
                                                .length
                                        "
                                        @click="generatePreview"
                                    >
                                        <IconLoader
                                            v-if="previewing"
                                            class="size-4 animate-spin"
                                        />
                                        <IconSparkles v-else class="size-4" />
                                        <span>{{
                                            previewing
                                                ? 'AI Đang Xử Lý...'
                                                : aiContentMode === 'video_ai'
                                                  ? 'AI Tạo Kịch Bản & Ảnh Mẫu'
                                                  : '✨ AI Tạo Gợi Ý & Ảnh Mẫu'
                                        }}</span>
                                    </button>
                                </div>

                                <!-- MODE 1: TEXT & IMAGE CREATIVE SETTINGS -->
                                <div
                                    v-if="aiContentMode === 'text_image'"
                                    class="space-y-6"
                                >
                                    <div class="space-y-4">
                                        <label
                                            class="block space-y-1.5 text-xs font-bold text-foreground"
                                        >
                                            Chủ đề / Góc tiếp cận nội dung
                                            (Theme)
                                            <input
                                                v-model="form.theme"
                                                type="text"
                                                class="h-10 w-full rounded-xl border bg-background px-3.5 text-sm font-medium focus:ring-primary"
                                                placeholder="Ví dụ: King Coffee Espresso Hoàng Gia - Bừng tỉnh năng lượng ngày mới..."
                                            />
                                        </label>

                                        <label
                                            class="block space-y-1.5 text-xs font-bold text-foreground"
                                        >
                                            Yêu cầu bổ sung cho AI (Prompt
                                            Briefing)
                                            <textarea
                                                v-model="form.prompt"
                                                rows="3"
                                                class="w-full rounded-xl border bg-background p-3.5 text-xs leading-relaxed focus:ring-primary"
                                                placeholder="Ví dụ: Nhấn mạnh hương vị đậm đà nguyên bản từ hạt cà phê Đắk Lắk, chèn CTA đặt mua trên Shopee..."
                                            />
                                        </label>
                                    </div>

                                    <div
                                        class="space-y-6 rounded-2xl border border-border/80 bg-card p-5 shadow-xs"
                                    >
                                        <!-- Header Studio -->
                                        <div
                                            class="flex flex-col justify-between gap-3 border-b border-border/70 pb-4 sm:flex-row sm:items-center"
                                        >
                                            <div>
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <h3
                                                        class="text-sm font-bold text-foreground"
                                                    >
                                                        Cấu Hình Ảnh AI Thương Mại
                                                    </h3>
                                                    <Badge
                                                        variant="outline"
                                                        class="text-3xs font-medium border-border/80 bg-muted/50 text-muted-foreground"
                                                    >
                                                        Tự động phối cảnh
                                                    </Badge>
                                                </div>
                                                <p
                                                    class="text-xs mt-0.5 text-muted-foreground"
                                                >
                                                    Dựng hình ảnh sản phẩm chuẩn studio thương mại bám sát nội dung bài viết
                                                </p>
                                            </div>

                                            <div
                                                v-if="form.ai_logo_path"
                                                class="text-3xs inline-flex items-center gap-1.5 self-start rounded-full border border-primary/30 bg-primary/10 px-2.5 py-1 font-medium text-primary sm:self-auto"
                                            >
                                                <span>Đóng dấu Logo: Đang bật</span>
                                            </div>
                                        </div>

                                        <!-- 1. SỐ LƯỢNG ẢNH AI CẦN VẼ (Segmented Cards) -->
                                        <div class="space-y-2.5">
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <span
                                                    class="flex items-center gap-1.5 text-xs font-bold text-foreground"
                                                >
                                                    <span
                                                        >Số lượng ảnh AI tự động
                                                        tạo</span
                                                    >
                                                    <span
                                                        class="text-3xs font-normal text-muted-foreground"
                                                        >(Mỗi bài viết sẽ được
                                                        AI vẽ số ảnh tương
                                                        ứng)</span
                                                    >
                                                </span>
                                                <span
                                                    class="text-xs font-bold text-amber-600 dark:text-amber-400"
                                                >
                                                    {{
                                                        form.ai_image_count ===
                                                        0
                                                            ? 'Chỉ dùng ảnh gốc'
                                                            : `Tạo ${form.ai_image_count} ảnh AI mới`
                                                    }}
                                                </span>
                                            </div>

                                            <div class="grid grid-cols-5 gap-2">
                                                <button
                                                    v-for="count in [
                                                        0, 1, 2, 3, 4,
                                                    ]"
                                                    :key="count"
                                                    type="button"
                                                    class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border p-2.5 text-center transition select-none"
                                                    :class="
                                                        form.ai_image_count ===
                                                        count
                                                            ? 'border-primary bg-primary/10 font-bold text-primary shadow-xs ring-2 ring-primary/30'
                                                            : 'border-border bg-card text-muted-foreground hover:bg-muted/40'
                                                    "
                                                    @click="
                                                        form.ai_image_count =
                                                            count
                                                    "
                                                >
                                                    <span
                                                        class="text-base font-extrabold"
                                                        >{{ count }}</span
                                                    >
                                                    <span
                                                        class="text-3xs leading-none"
                                                    >
                                                        {{
                                                            count === 0
                                                                ? 'Dùng ảnh gốc'
                                                                : `${count} ảnh AI`
                                                        }}
                                                    </span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- NẾU CHỌN TẠO ẢNH AI (ai_image_count > 0) -->
                                        <div
                                            v-if="form.ai_image_count > 0"
                                            class="space-y-6 border-t border-border/60 pt-3"
                                        >
                                            <!-- 2. PHONG CÁCH HÌNH ẢNH AI (Style Cards Gallery) -->
                                            <div class="space-y-2.5">
                                                <div
                                                    class="flex items-center justify-between"
                                                >
                                                    <span
                                                        class="text-xs font-bold text-foreground"
                                                        >Phong cách hình ảnh
                                                        nghệ thuật (Art
                                                        Style)</span
                                                    >
                                                    <span
                                                        class="text-3xs text-muted-foreground"
                                                        >Chọn tone màu & bối
                                                        cảnh chủ đạo</span
                                                    >
                                                </div>

                                                <div
                                                    class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-3"
                                                >
                                                    <div
                                                        v-for="style in imageStyles"
                                                        :key="style.id"
                                                        class="flex cursor-pointer flex-col justify-between gap-2 rounded-xl border-2 p-3.5 text-left transition select-none"
                                                        :class="
                                                            form.ai_image_style ===
                                                            style.id
                                                                ? 'border-primary bg-primary/5 shadow-xs ring-1 ring-primary/40'
                                                                : 'border-border bg-card hover:bg-muted/30'
                                                        "
                                                        @click="
                                                            form.ai_image_style =
                                                                style.id
                                                        "
                                                    >
                                                        <div
                                                            class="flex items-center justify-between gap-2"
                                                        >
                                                            <div
                                                                class="flex items-center gap-2"
                                                            >
                                                                <span
                                                                    class="text-xs font-bold text-foreground"
                                                                    >{{
                                                                        style.title
                                                                    }}</span
                                                                >
                                                            </div>
                                                            <span
                                                                class="text-3xs rounded-md px-2 py-0.5 font-semibold"
                                                                :class="
                                                                    form.ai_image_style ===
                                                                    style.id
                                                                        ? 'bg-primary text-primary-foreground'
                                                                        : 'bg-muted text-muted-foreground'
                                                                "
                                                            >
                                                                {{
                                                                    style.badge
                                                                }}
                                                            </span>
                                                        </div>
                                                        <p
                                                            class="text-3xs line-clamp-2 leading-relaxed text-muted-foreground"
                                                        >
                                                            {{
                                                                style.description
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 3. TỈ LỆ KHUNG HÌNH (Aspect Ratio Visual Cards) -->
                                            <div class="space-y-2.5">
                                                <div
                                                    class="flex items-center justify-between"
                                                >
                                                    <span
                                                        class="text-xs font-bold text-foreground"
                                                        >Tỉ lệ khung hình
                                                        (Aspect Ratio)</span
                                                    >
                                                    <span
                                                        class="text-3xs text-muted-foreground"
                                                        >Tối ưu chuẩn cho từng
                                                        nền tảng</span
                                                    >
                                                </div>

                                                <div
                                                    class="grid grid-cols-2 gap-2.5 sm:grid-cols-4"
                                                >
                                                    <div
                                                        v-for="ratio in imageAspectRatios"
                                                        :key="ratio.value"
                                                        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 p-3 text-center transition select-none"
                                                        :class="
                                                            form.ai_image_aspect_ratio ===
                                                            ratio.value
                                                                ? 'border-primary bg-primary/5 shadow-xs ring-1 ring-primary/40'
                                                                : 'border-border bg-card hover:bg-muted/30'
                                                        "
                                                        @click="
                                                            form.ai_image_aspect_ratio =
                                                                ratio.value
                                                        "
                                                    >
                                                        <div
                                                            class="flex size-9 items-center justify-center rounded-lg bg-muted/60"
                                                        >
                                                            <div
                                                                class="rounded-xs border-2 transition"
                                                                :class="[
                                                                    ratio.ratioClass,
                                                                    form.ai_image_aspect_ratio ===
                                                                    ratio.value
                                                                        ? 'border-primary bg-primary/20'
                                                                        : 'border-muted-foreground/60 bg-muted-foreground/10',
                                                                ]"
                                                            />
                                                        </div>
                                                        <div>
                                                            <p
                                                                class="text-xs font-bold text-foreground"
                                                            >
                                                                {{
                                                                    ratio.label
                                                                }}
                                                            </p>
                                                            <p
                                                                class="text-3xs mt-0.5 text-muted-foreground"
                                                            >
                                                                {{ ratio.desc }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 4. ĐỘ PHÂN GIẢI & CHẤT LƯỢNG ẢNH (Resolution) -->
                                            <div class="space-y-2.5">
                                                <span
                                                    class="text-xs font-bold text-foreground"
                                                    >Độ phân giải & Chất lượng
                                                    chi tiết</span
                                                >
                                                <div
                                                    class="grid grid-cols-1 gap-2.5 sm:grid-cols-3"
                                                >
                                                    <div
                                                        v-for="res in imageResolutions"
                                                        :key="res.value"
                                                        class="flex cursor-pointer items-center gap-2.5 rounded-xl border-2 p-3 transition select-none"
                                                        :class="
                                                            form.ai_image_resolution ===
                                                            res.value
                                                                ? 'border-primary bg-primary/5 shadow-xs ring-1 ring-primary/40'
                                                                : 'border-border bg-card hover:bg-muted/30'
                                                        "
                                                        @click="
                                                            form.ai_image_resolution =
                                                                res.value
                                                        "
                                                    >
                                                        <div
                                                            class="flex size-7 items-center justify-center rounded-lg text-xs font-bold"
                                                            :class="
                                                                form.ai_image_resolution ===
                                                                res.value
                                                                    ? 'bg-primary text-primary-foreground'
                                                                    : 'bg-muted text-muted-foreground'
                                                            "
                                                        >
                                                            {{
                                                                res.value ||
                                                                '1K'
                                                            }}
                                                        </div>
                                                        <div>
                                                            <p
                                                                class="text-xs font-bold text-foreground"
                                                            >
                                                                {{ res.label }}
                                                            </p>
                                                            <p
                                                                class="text-3xs text-muted-foreground"
                                                            >
                                                                {{ res.desc }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 5. MÔ TẢ BỐI CẢNH ẢNH AI (Visual Prompt Studio & Quick Tag Chips) -->
                                            <div
                                                class="space-y-2.5 rounded-xl border bg-background/80 p-4"
                                            >
                                                <div
                                                    class="flex items-center justify-between"
                                                >
                                                    <span
                                                        class="flex items-center gap-1.5 text-xs font-bold text-foreground"
                                                    >
                                                        <IconSparkles
                                                            class="size-4 text-amber-500"
                                                        />
                                                        <span
                                                            >Mô tả bối cảnh ảnh
                                                            AI (Visual Prompt
                                                            AI)</span
                                                        >
                                                    </span>
                                                    <span
                                                        class="text-3xs text-muted-foreground"
                                                        >Tuỳ chọn chi tiết bối
                                                        cảnh</span
                                                    >
                                                </div>

                                                <textarea
                                                    v-model="form.image_prompt"
                                                    rows="2"
                                                    class="w-full rounded-xl border bg-card p-3 text-xs leading-relaxed focus:ring-primary"
                                                    placeholder="Ví dụ: Cận cảnh ly cà phê sữa đá đọng hơi nước mát lạnh, giọt cà phê tí tách sóng sánh trên bàn gỗ sang trọng..."
                                                />

                                                <!-- Gợi ý tags nhanh -->
                                                <div class="space-y-1.5 pt-1">
                                                    <span
                                                        class="text-3xs font-semibold text-muted-foreground"
                                                        >Gợi ý bối cảnh nhanh
                                                        (Bấm để thêm):</span
                                                    >
                                                    <div
                                                        class="flex flex-wrap gap-1.5"
                                                    >
                                                        <button
                                                            v-for="preset in visualPromptPresets"
                                                            :key="preset"
                                                            type="button"
                                                            class="text-3xs cursor-pointer rounded-full border bg-muted/40 px-2.5 py-1 text-muted-foreground transition hover:border-primary/40 hover:bg-primary/10 hover:text-primary"
                                                            @click="
                                                                addVisualPromptTag(
                                                                    preset,
                                                                )
                                                            "
                                                        >
                                                            + {{ preset }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Nút Tạo Thử Nghiệm Ngay Dưới Khung Studio -->
                                        <div
                                            class="flex flex-col items-center justify-between gap-3 border-t border-border/70 pt-3 sm:flex-row"
                                        >
                                            <div
                                                class="text-3xs flex items-center gap-1.5 text-muted-foreground"
                                            >
                                                <IconInfoCircle
                                                    class="size-3.5 shrink-0 text-amber-500"
                                                />
                                                <span
                                                    >AI sẽ tạo 3 bản gợi ý bài
                                                    viết mẫu kèm hình ảnh minh
                                                    họa tương ứng.</span
                                                >
                                            </div>

                                            <button
                                                type="button"
                                                class="inline-flex h-10 w-full transform cursor-pointer items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 px-5 text-xs font-bold text-white shadow-sm transition hover:from-amber-600 hover:to-amber-700 active:scale-95 disabled:opacity-60 sm:w-auto"
                                                :disabled="
                                                    previewing ||
                                                    (sourceInputMode ===
                                                    'select'
                                                        ? !form.source_post_id
                                                        : !manualSourceContent.trim()) ||
                                                    !form
                                                        .target_social_account_ids
                                                        .length
                                                "
                                                @click="generatePreview"
                                            >
                                                <IconLoader
                                                    v-if="previewing"
                                                    class="size-4 animate-spin"
                                                />
                                                <IconSparkles
                                                    v-else
                                                    class="size-4"
                                                />
                                                <span>{{
                                                    previewing
                                                        ? 'AI Đang Vẽ Ảnh & Soạn Bài...'
                                                        : '✨ Tạo Thử Nghiệm Bài Viết & Ảnh AI'
                                                }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- MODE 2: ELEGANT & CLEAN CAPCUT VIDEO STUDIO -->
                                <div
                                    v-else-if="aiContentMode === 'video_ai'"
                                    class="space-y-6"
                                >
                                    <!-- MAIN GRID: Storyboard Workstation (Left) + Sticky Live Monitor (Right) -->
                                    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                                        <!-- LEFT COLUMN: Studio Workstation (7-8 cols) -->
                                        <div class="space-y-5 lg:col-span-7 xl:col-span-8">
                                            <!-- 1. BỘ THIẾT LẬP KỊCH BẢN & CẤU HÌNH (GỌN GÀNG, SANG TRỌNG) -->
                                            <div class="rounded-2xl border border-border/80 bg-card p-4.5 shadow-xs space-y-4">
                                                <!-- Header & Hook Mở Đầu -->
                                                <div class="space-y-2.5">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center gap-2">
                                                            <div class="flex size-7 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500">
                                                                <IconMovie class="size-4" />
                                                            </div>
                                                            <h3 class="text-sm font-bold text-foreground">
                                                                Kịch Bản Video Review AI
                                                            </h3>
                                                            <span class="rounded-full bg-amber-500/10 px-2 py-0.5 text-3xs font-bold text-amber-600 dark:text-amber-400">
                                                                {{ form.video_target_duration }}s · {{ form.video_scenes.length }} cảnh quay
                                                            </span>
                                                        </div>

                                                        <!-- Nút AI Lên Kịch Bản Nổi Bật -->
                                                        <button
                                                            type="button"
                                                            @click="generateAiVideoScript"
                                                            :disabled="generatingAiVideoScript"
                                                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm transition hover:from-amber-600 hover:to-amber-700 active:scale-95 disabled:opacity-50"
                                                            title="AI biên kịch chi tiết từng cảnh quay theo Hook và Thời lượng đang chọn"
                                                        >
                                                            <IconLoader v-if="generatingAiVideoScript" class="size-3.5 animate-spin" />
                                                            <IconSparkles v-else class="size-3.5" />
                                                            <span>{{ generatingAiVideoScript ? 'AI Đang Viết Kịch Bản...' : '✨ AI Lên Kịch Bản (' + form.video_target_duration + 's)' }}</span>
                                                        </button>
                                                    </div>

                                                    <!-- Input Hook mở đầu -->
                                                    <div class="relative">
                                                        <input
                                                            v-model="form.video_hook"
                                                            type="text"
                                                            class="h-10 w-full rounded-xl border border-border/80 bg-background px-3.5 pr-8 text-xs font-medium shadow-2xs transition placeholder:text-muted-foreground/60 focus:border-primary focus:ring-1 focus:ring-primary"
                                                            placeholder="Nhập Hook mở đầu (ví dụ: King CF HÀ NỘI, GIỚI THIỆU VỀ SẢN PHẨM)..."
                                                        />
                                                        <button
                                                            v-if="form.video_hook"
                                                            type="button"
                                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 cursor-pointer text-muted-foreground/60 hover:text-foreground"
                                                            @click="form.video_hook = ''"
                                                        >
                                                            <IconX class="size-3.5" />
                                                        </button>
                                                    </div>

                                                    <!-- Gợi ý Hook nhanh dạng chips -->
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        <span class="text-3xs font-medium text-muted-foreground">Gợi ý:</span>
                                                        <button
                                                            v-for="preset in quickHookPresets"
                                                            :key="preset.label"
                                                            type="button"
                                                            class="inline-flex cursor-pointer items-center rounded-lg border border-border/70 bg-muted/30 px-2 py-0.5 text-3xs font-medium text-muted-foreground transition hover:border-primary/50 hover:bg-primary/5 hover:text-primary active:scale-95"
                                                            @click="addHookPreset(preset.text)"
                                                        >
                                                            {{ preset.label }}
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- HÀNG 1: THÔNG SỐ VIDEO (Thời lượng & Giọng đọc nằm ngang gọn gàng, không bể chữ) -->
                                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 rounded-xl border border-border/70 bg-muted/20 p-3">
                                                    <!-- Thời lượng -->
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-xs font-semibold text-foreground whitespace-nowrap">⏱️ Thời lượng:</span>
                                                        <div class="flex items-center gap-1.5">
                                                            <button
                                                                v-for="opt in targetDurationOptions"
                                                                :key="opt.value"
                                                                type="button"
                                                                @click="form.video_target_duration = opt.value"
                                                                class="cursor-pointer rounded-lg border px-2.5 py-1 text-xs font-bold transition select-none"
                                                                :class="form.video_target_duration === opt.value
                                                                    ? 'border-primary bg-primary/15 text-primary shadow-2xs ring-1 ring-primary/40'
                                                                    : 'border-border/70 bg-background text-muted-foreground hover:bg-muted/50'
                                                                "
                                                            >
                                                                {{ opt.duration }} <span class="text-3xs font-normal opacity-70">({{ opt.scenes }})</span>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Giọng đọc AI -->
                                                    <div class="flex items-center gap-2 min-w-0 sm:max-w-xs flex-1 justify-end">
                                                        <span class="text-xs font-semibold text-foreground whitespace-nowrap">🎙️ Giọng đọc:</span>
                                                        <select
                                                            v-model="form.video_voiceover_voice"
                                                            class="h-8.5 w-full cursor-pointer rounded-lg border border-border/80 bg-background px-2.5 text-xs font-semibold text-foreground focus:border-primary focus:ring-1 focus:ring-primary"
                                                        >
                                                            <option
                                                                v-for="v in voiceoverVoiceOptions"
                                                                :key="v.id"
                                                                :value="v.id"
                                                            >
                                                                {{ v.gender === 'Nữ' ? '👩' : '👨' }} {{ v.name }} ({{ v.accent }})
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- HÀNG 2: DIỄN VIÊN MODEL & ẢNH SẢN PHẨM (2 Cột Cân Đối, Chữ Đẹp Tự Nhiên) -->
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                    <!-- Cột 1: Nhân vật Model AI -->
                                                    <div class="rounded-xl border border-border/70 bg-muted/20 p-3 space-y-2.5">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-xs font-bold text-foreground">👤 Nhân vật Model AI</span>
                                                            <button
                                                                type="button"
                                                                class="text-xs font-semibold text-amber-600 hover:underline dark:text-amber-400 cursor-pointer"
                                                                @click="showCharacterDnaDetails = !showCharacterDnaDetails"
                                                            >
                                                                {{ showCharacterDnaDetails ? 'Ẩn DNA' : '⚙️ Tùy chỉnh DNA' }}
                                                            </button>
                                                        </div>

                                                        <!-- Model hiện tại + Tải lên / Thư viện -->
                                                        <div class="flex items-center gap-2 rounded-xl border border-border/80 bg-background p-1.5 shadow-2xs">
                                                            <div
                                                                class="relative size-8 shrink-0 cursor-pointer overflow-hidden rounded-full border-2 border-amber-500 bg-muted shadow-xs"
                                                                title="Đổi ảnh nhân vật"
                                                                @click="openMediaPickerForCharacter"
                                                            >
                                                                <img
                                                                    v-if="form.character_avatar"
                                                                    :src="form.character_avatar"
                                                                    class="size-full object-cover"
                                                                />
                                                                <div v-else class="flex size-full items-center justify-center text-3xs font-bold text-muted-foreground">
                                                                    AI
                                                                </div>
                                                            </div>

                                                            <input
                                                                v-model="form.character_name"
                                                                type="text"
                                                                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-xs font-bold text-foreground focus:ring-0"
                                                                placeholder="Tên nhân vật..."
                                                            />

                                                            <div class="flex items-center gap-1 shrink-0">
                                                                <button
                                                                    type="button"
                                                                    @click="openMediaPickerForCharacter"
                                                                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/70 bg-muted/40 px-2 py-1 text-xs font-semibold text-foreground hover:bg-muted"
                                                                >
                                                                    <IconPhoto class="size-3.5 text-primary" />
                                                                    <span>Thư viện</span>
                                                                </button>
                                                                <label class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/70 bg-muted/40 px-2 py-1 text-xs font-semibold text-foreground hover:bg-muted">
                                                                    <IconLoader v-if="characterAvatarUploading" class="size-3.5 animate-spin text-primary" />
                                                                    <IconUpload v-else class="size-3.5 text-emerald-500" />
                                                                    <span>Tải lên</span>
                                                                    <input
                                                                        type="file"
                                                                        accept="image/*"
                                                                        class="hidden"
                                                                        :disabled="characterAvatarUploading"
                                                                        @change="handleCharacterAvatarUpload"
                                                                    />
                                                                </label>
                                                            </div>
                                                        </div>

                                                        <!-- Presets chọn nhanh -->
                                                        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                                                            <button
                                                                v-for="preset in characterPresets"
                                                                :key="preset.id"
                                                                type="button"
                                                                @click="selectCharacterPreset(preset)"
                                                                class="inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-medium transition"
                                                                :class="form.character_id === preset.id
                                                                    ? 'border-primary bg-primary/15 text-primary shadow-2xs ring-1 ring-primary/40 font-bold'
                                                                    : 'border-border/70 bg-background text-muted-foreground hover:border-primary/40 hover:text-foreground'
                                                                "
                                                            >
                                                                <img :src="preset.avatar" class="size-3.5 rounded-full object-cover shrink-0" />
                                                                <span>{{ preset.name }}</span>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Cột 2: Sản phẩm tham chiếu -->
                                                    <div class="rounded-xl border border-border/70 bg-muted/20 p-3 space-y-2.5">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-xs font-bold text-foreground">☕ Sản phẩm mẫu</span>
                                                            <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                                                                Bảo tồn 100% bao bì
                                                            </span>
                                                        </div>

                                                        <div class="flex items-center justify-between rounded-xl border border-border/80 bg-background p-1.5 shadow-2xs">
                                                            <div class="flex items-center gap-2 min-w-0">
                                                                <div
                                                                    v-if="manualSourceMedia.length > 0"
                                                                    class="size-8 shrink-0 overflow-hidden rounded-lg border border-border/80 shadow-2xs"
                                                                >
                                                                    <img :src="manualSourceMedia[0].url" class="size-full object-cover" />
                                                                </div>
                                                                <div v-else class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                                                    <IconPhoto class="size-4" />
                                                                </div>
                                                                <div class="min-w-0">
                                                                    <p class="truncate text-xs font-semibold text-foreground">
                                                                        {{ manualSourceMedia.length > 0 ? `Đã chọn ${manualSourceMedia.length} ảnh SP` : 'Chưa có ảnh SP' }}
                                                                    </p>
                                                                    <p class="text-3xs text-muted-foreground truncate">
                                                                        {{ manualSourceMedia.length > 0 ? 'AI cố định nhãn mác' : 'Thêm ảnh mẫu bao bì' }}
                                                                    </p>
                                                                </div>
                                                            </div>

                                                            <div class="flex items-center gap-1 shrink-0">
                                                                <button
                                                                    type="button"
                                                                    @click="openMediaPickerForReference"
                                                                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/70 bg-muted/40 px-2 py-1 text-xs font-semibold text-primary hover:bg-muted"
                                                                >
                                                                    <IconPhoto class="size-3.5" />
                                                                    <span>Thư viện</span>
                                                                </button>
                                                                <label class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/70 bg-muted/40 px-2 py-1 text-xs font-semibold text-foreground hover:bg-muted">
                                                                    <IconUpload class="size-3.5 text-emerald-500" />
                                                                    <span>Tải lên</span>
                                                                    <input
                                                                        type="file"
                                                                        accept="image/*"
                                                                        multiple
                                                                        class="hidden"
                                                                        @change="handleManualMediaUpload"
                                                                    />
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Panel DNA (Chỉ hiện khi mở) -->
                                                <div
                                                    v-if="showCharacterDnaDetails"
                                                    class="rounded-xl border border-border/80 bg-muted/20 p-2.5 space-y-1.5"
                                                >
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-3xs font-bold text-foreground">Character DNA Prompt:</span>
                                                        <span class="text-4xs text-muted-foreground">AI giữ khuôn mặt & phong thái</span>
                                                    </div>
                                                    <textarea
                                                        v-model="form.character_dna_prompt"
                                                        rows="2"
                                                        class="w-full rounded-lg border border-border/80 bg-background px-3 py-1.5 text-xs font-medium focus:border-primary focus:ring-1 focus:ring-primary"
                                                        placeholder="Mô tả khuôn mặt, độ tuổi, trang phục, phong thái của nhân vật..."
                                                    />
                                                </div>
                                            </div>

                                            <!-- 2. DANH SÁCH PHÂN CẢNH ĐIỆN ẢNH (CINEMA STORYBOARD CARDS) -->
                                            <div class="space-y-3">
                                                <!-- Header Storyboard & Nút Thêm Cảnh -->
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <h4 class="text-xs font-extrabold uppercase tracking-wider text-foreground">
                                                            Phân Cảnh Kịch Bản (Storyboard)
                                                        </h4>
                                                        <span class="rounded-md bg-muted px-2 py-0.5 text-3xs font-bold text-muted-foreground">
                                                            {{ form.video_scenes.length }} Cảnh · {{ totalVideoDuration }}s
                                                        </span>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        @click="addVideoScene"
                                                        class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/80 bg-card px-2.5 py-1 text-xs font-bold text-foreground shadow-2xs transition hover:bg-muted active:scale-95"
                                                    >
                                                        <IconPlus class="size-3.5 text-primary" />
                                                        <span>Thêm cảnh (8s)</span>
                                                    </button>
                                                </div>

                                                <!-- Khung cảnh báo khi chưa có phân cảnh -->
                                                <div
                                                    v-if="form.video_scenes.length === 0"
                                                    class="rounded-2xl border border-dashed border-border/80 bg-card p-8 text-center"
                                                >
                                                    <IconMovie class="mx-auto size-10 text-amber-500/80 mb-2" />
                                                    <h5 class="text-sm font-bold text-foreground">Chưa có phân cảnh nào</h5>
                                                    <p class="text-xs text-muted-foreground mt-1 max-w-sm mx-auto">
                                                        Hãy nhập Hook mở đầu ở trên và bấm nút <strong>[ ✨ AI Lên Kịch Bản ]</strong> để AI tự động biên kịch các cảnh quay review chi tiết.
                                                    </p>
                                                    <button
                                                        type="button"
                                                        @click="generateAiVideoScript"
                                                        :disabled="generatingAiVideoScript"
                                                        class="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-xl bg-primary px-4 py-2 text-xs font-bold text-primary-foreground shadow transition hover:bg-primary/90"
                                                    >
                                                        <IconSparkles class="size-4" />
                                                        <span>Khởi Tạo Kịch Bản Ngay</span>
                                                    </button>
                                                </div>

                                                <!-- DANH SÁCH CÁC THẺ PHÂN CẢNH (MỖI SCENE LÀ 1 CARD TINH TẾ) -->
                                                <div v-else class="space-y-3">
                                                    <template v-for="(scene, index) in form.video_scenes" :key="`scene-card-${index}`">
                                                        <div
                                                            class="group relative rounded-2xl border bg-card p-3.5 shadow-xs transition-all"
                                                            :class="activePreviewSceneIndex === index
                                                                ? 'border-primary ring-1 ring-primary/40 bg-primary/[0.02]'
                                                                : 'border-border/80 hover:border-border hover:shadow-sm'
                                                            "
                                                            @click="selectPreviewScene(index)"
                                                        >
                                                            <!-- CARD HEADER: Cảnh số mấy + Thời lượng + Chuyển cảnh + Nút xóa/nhân bản -->
                                                            <div class="flex items-center justify-between border-b border-border/60 pb-2.5 mb-3">
                                                                <div class="flex items-center gap-2">
                                                                    <span
                                                                        class="rounded-lg px-2.5 py-0.5 text-xs font-black uppercase tracking-wide"
                                                                        :class="activePreviewSceneIndex === index
                                                                            ? 'bg-primary text-primary-foreground'
                                                                            : 'bg-muted text-foreground'
                                                                        "
                                                                    >
                                                                        Cảnh {{ index + 1 }}
                                                                    </span>
                                                                    <!-- Bộ điều chỉnh thời lượng -->
                                                                    <div class="flex items-center gap-1 rounded-md bg-muted/60 px-2 py-0.5 text-xs font-bold text-foreground">
                                                                        <button
                                                                            type="button"
                                                                            @click.stop="adjustSceneDuration(index, -1)"
                                                                            class="cursor-pointer text-muted-foreground hover:text-foreground px-0.5"
                                                                        >
                                                                            -
                                                                        </button>
                                                                        <span class="font-bold text-primary">{{ scene.duration }}s</span>
                                                                        <button
                                                                            type="button"
                                                                            @click.stop="adjustSceneDuration(index, 1)"
                                                                            class="cursor-pointer text-muted-foreground hover:text-foreground px-0.5"
                                                                        >
                                                                            +
                                                                        </button>
                                                                    </div>
                                                                    <!-- Badge đã có video -->
                                                                    <span
                                                                        v-if="scene.video_url"
                                                                        class="flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-4xs font-bold text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
                                                                    >
                                                                        <IconVideo class="size-2.5" /> Đã có video
                                                                    </span>
                                                                </div>

                                                                <div class="flex items-center gap-1.5">
                                                                    <!-- Select chuyển cảnh -->
                                                                    <select
                                                                        v-model="scene.transition"
                                                                        @click.stop
                                                                        class="h-7 cursor-pointer rounded-lg border border-border/70 bg-background px-2 text-3xs font-semibold text-muted-foreground hover:text-foreground"
                                                                        title="Hiệu ứng chuyển cảnh"
                                                                    >
                                                                        <option value="glitch">⚡ Glitch</option>
                                                                        <option value="fade_black">🌑 Mờ đen</option>
                                                                        <option value="slide_left">➡️ Trượt</option>
                                                                        <option value="zoom_in">🔍 Thu phóng</option>
                                                                        <option value="cross_dissolve">⇄ Hòa tan</option>
                                                                    </select>

                                                                    <!-- Nút Nhân bản -->
                                                                    <button
                                                                        type="button"
                                                                        @click.stop="duplicateScene(index)"
                                                                        class="flex size-7 cursor-pointer items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                                                                        title="Nhân bản phân cảnh"
                                                                    >
                                                                        <IconCopy class="size-3.5" />
                                                                    </button>

                                                                    <!-- Nút Xóa cảnh -->
                                                                    <button
                                                                        v-if="form.video_scenes.length > 1"
                                                                        type="button"
                                                                        @click.stop="removeVideoScene(index)"
                                                                        class="flex size-7 cursor-pointer items-center justify-center rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                                        title="Xóa phân cảnh"
                                                                    >
                                                                        <IconTrash class="size-3.5" />
                                                                    </button>
                                                                </div>
                                                            </div>

                                                            <!-- CARD BODY: 2 CỘT (Cột trái: Thumbnail & Render | Cột phải: Lời thoại & Hành động 8s) -->
                                                            <div class="flex flex-col sm:flex-row gap-3.5 items-start">
                                                                <!-- CỘT 1: THUMBNAIL / MEDIA (Rộng 150px) -->
                                                                <div class="w-full sm:w-[150px] shrink-0 space-y-2">
                                                                    <div class="relative h-28 w-full overflow-hidden rounded-xl border border-border/80 bg-neutral-950 flex items-center justify-center shadow-inner group/thumb">
                                                                        <!-- Nếu đã có video -->
                                                                        <template v-if="scene.video_url">
                                                                            <video
                                                                                :src="scene.video_url"
                                                                                class="size-full object-cover"
                                                                                muted
                                                                                loop
                                                                                playsinline
                                                                            />
                                                                            <button
                                                                                type="button"
                                                                                @click.stop="openSceneVideoModal(index)"
                                                                                class="absolute inset-0 flex items-center justify-center bg-black/40 hover:bg-black/50 transition cursor-pointer"
                                                                                title="Xem video"
                                                                            >
                                                                                <IconPlayerPlay class="size-8 text-white fill-white" />
                                                                            </button>
                                                                        </template>
                                                                        <!-- Nếu có ảnh Keyframe -->
                                                                        <template v-else-if="scene.start_image || scene.end_image">
                                                                            <img
                                                                                :src="scene.start_image || scene.end_image"
                                                                                class="size-full object-cover"
                                                                            />
                                                                        </template>
                                                                        <!-- Chưa có ảnh -->
                                                                        <div v-else class="p-2 text-center text-muted-foreground">
                                                                            <IconMovie class="size-6 mx-auto text-amber-500/80 mb-1" />
                                                                            <span class="text-4xs font-medium">Chưa có ảnh</span>
                                                                        </div>

                                                                        <!-- Nút chọn ảnh từ thư viện & tải lên trên góc thumbnail -->
                                                                        <div class="absolute top-1 right-1 flex items-center gap-1 bg-black/60 rounded-md p-0.5 backdrop-blur">
                                                                            <button
                                                                                type="button"
                                                                                @click.stop="openMediaPickerForScene(index)"
                                                                                class="size-5 flex items-center justify-center rounded text-white hover:bg-white/20"
                                                                                title="Chọn ảnh từ thư viện"
                                                                            >
                                                                                <IconPhoto class="size-3 text-primary" />
                                                                            </button>
                                                                            <label class="size-5 flex cursor-pointer items-center justify-center rounded text-white hover:bg-white/20" title="Tải ảnh lên">
                                                                                <IconUpload class="size-3 text-emerald-400" />
                                                                                <input
                                                                                    type="file"
                                                                                    accept="image/*"
                                                                                    class="hidden"
                                                                                    @change="handleVideoImageUpload($event, index, 'start')"
                                                                                />
                                                                            </label>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Nút Tạo Video 8s -->
                                                                    <button
                                                                        type="button"
                                                                        @click.stop="generateSceneVideo(index)"
                                                                        :disabled="generatingSceneVideoIndex === index"
                                                                        class="w-full flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border py-1.5 text-3xs font-bold transition select-none"
                                                                        :class="scene.video_url
                                                                            ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20 dark:text-emerald-400'
                                                                            : 'border-blue-500/40 bg-blue-500/10 text-blue-600 hover:bg-blue-500/20 dark:text-blue-400'
                                                                        "
                                                                    >
                                                                        <IconLoader v-if="generatingSceneVideoIndex === index" class="size-3 animate-spin" />
                                                                        <IconVideo v-else class="size-3" />
                                                                        <span>{{ generatingSceneVideoIndex === index ? 'Đang tạo...' : (scene.video_url ? 'Render lại' : 'Tạo video 8s') }}</span>
                                                                    </button>
                                                                </div>

                                                                <!-- CỘT 2: LỜI THOẠI & DIỄN BIẾN HÀNH ĐỘNG 8S (FLEX-1) -->
                                                                <div class="min-w-0 flex-1 space-y-2.5 w-full">
                                                                    <!-- 1. Lời thoại nhân vật nói (Spoken Dialogue) - TO RÕ & NỔI BẬT -->
                                                                    <div class="space-y-1">
                                                                        <div class="flex items-center justify-between">
                                                                            <div class="flex items-center gap-1.5">
                                                                                <span class="text-xs font-bold text-foreground">
                                                                                    Lời thoại Reviewer nói trực tiếp:
                                                                                </span>
                                                                                <span class="text-4xs font-bold text-amber-600 dark:text-amber-400 bg-amber-500/15 rounded-md px-1.5 py-0.5">
                                                                                    🎙️ Lip-sync giọng nói
                                                                                </span>
                                                                            </div>

                                                                            <!-- Nút nghe thử / tạo giọng -->
                                                                            <button
                                                                                type="button"
                                                                                @click.stop="scene.voiceover_audio_url ? playSceneVoice(index) : generateSceneVoiceover(index)"
                                                                                :disabled="generatingSceneVoiceIndex === index"
                                                                                class="inline-flex cursor-pointer items-center gap-1 text-3xs font-bold text-primary hover:underline"
                                                                            >
                                                                                <IconLoader v-if="generatingSceneVoiceIndex === index" class="size-3 animate-spin" />
                                                                                <IconPlayerPlay v-else-if="scene.voiceover_audio_url" class="size-3 text-emerald-500" />
                                                                                <IconMusic v-else class="size-3" />
                                                                                <span>{{ scene.voiceover_audio_url ? 'Nghe thử giọng' : 'Tạo giọng đọc' }}</span>
                                                                            </button>
                                                                        </div>

                                                                        <textarea
                                                                            v-model="scene.voiceover_text"
                                                                            @click.stop
                                                                            rows="2"
                                                                            class="w-full rounded-xl border border-amber-500/30 bg-amber-500/[0.04] p-2.5 text-xs font-medium leading-relaxed text-foreground focus:border-primary focus:ring-1 focus:ring-primary placeholder:text-muted-foreground/60"
                                                                            placeholder="Nhập câu thoại nhân vật nói trong 8s của cảnh này..."
                                                                        />
                                                                    </div>

                                                                    <!-- 2. Chuyển động Camera & Diễn biến hành động 8s -->
                                                                    <div class="space-y-1">
                                                                        <div class="flex items-center justify-between">
                                                                            <span class="text-3xs font-bold text-muted-foreground uppercase tracking-wider">
                                                                                Góc máy & Hành động chi tiết 8s (Camera Motion & Action Timeline):
                                                                            </span>
                                                                        </div>
                                                                        <textarea
                                                                            v-model="scene.action_prompt"
                                                                            @click.stop
                                                                            rows="2"
                                                                            class="w-full rounded-xl border border-border/80 bg-background p-2 text-xs font-medium leading-relaxed text-foreground/90 focus:border-primary focus:ring-1 focus:ring-primary placeholder:text-muted-foreground/60"
                                                                            placeholder="(0s-3s: camera zoom vào reviewer...; 3s-6s: reviewer nâng tách cà phê...; 6s-8s: lia cận cảnh bao bì...)"
                                                                        />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Divider chuyển cảnh giữa các clip -->
                                                        <div
                                                            v-if="index < form.video_scenes.length - 1"
                                                            class="flex items-center justify-center py-0.5"
                                                        >
                                                            <div class="h-4 w-px bg-border/80"></div>
                                                        </div>
                                                    </template>
                                                </div>

                                                <!-- BỘ NÚT QUY TRÌNH SẢN XUẤT HÀNG LOẠT (DƯỚI STORYBOARD) -->
                                                <div class="rounded-2xl border border-border/80 bg-card p-4 shadow-xs space-y-3">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs font-bold text-foreground">Quy trình sản xuất hàng loạt</span>
                                                        <span class="text-3xs text-muted-foreground">Tự động hóa từng bước theo thứ tự</span>
                                                    </div>
                                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                                        <!-- Bước 1: Vẽ ảnh Keyframe -->
                                                        <button
                                                            type="button"
                                                            @click="renderAllSceneKeyframes"
                                                            :disabled="renderingAllKeyframes || form.video_scenes.length === 0"
                                                            class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-amber-500/40 bg-amber-500/10 px-3.5 py-2.5 text-xs font-bold text-amber-700 dark:text-amber-300 transition hover:bg-amber-500/20 active:scale-95 disabled:opacity-40"
                                                        >
                                                            <IconLoader v-if="renderingAllKeyframes" class="size-4 animate-spin text-amber-500" />
                                                            <IconPhoto v-else class="size-4 text-amber-500" />
                                                            <span>1. Vẽ Keyframe Các Cảnh</span>
                                                        </button>

                                                        <!-- Bước 2: Render Video Tất Cả Cảnh -->
                                                        <button
                                                            type="button"
                                                            @click="renderAllSceneVideos"
                                                            :disabled="renderingAllVideos || form.video_scenes.length === 0"
                                                            class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-blue-500/40 bg-blue-500/10 px-3.5 py-2.5 text-xs font-bold text-blue-700 dark:text-blue-300 transition hover:bg-blue-500/20 active:scale-95 disabled:opacity-40"
                                                        >
                                                            <IconLoader v-if="renderingAllVideos" class="size-4 animate-spin text-blue-500" />
                                                            <IconVideo v-else class="size-4 text-blue-500" />
                                                            <span>2. Render Video Từng Cảnh</span>
                                                        </button>

                                                        <!-- Bước 3: Ghép Video Hoàn Chỉnh -->
                                                        <button
                                                            type="button"
                                                            @click="stitchVideo"
                                                            :disabled="stitchingVideo || form.video_scenes.length === 0"
                                                            class="flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 px-3.5 py-2.5 text-xs font-bold text-white shadow-sm shadow-emerald-600/20 transition hover:brightness-110 active:scale-95 disabled:opacity-40"
                                                        >
                                                            <IconLoader v-if="stitchingVideo" class="size-4 animate-spin" />
                                                            <IconMovie v-else class="size-4" />
                                                            <span>3. Ghép Video Hoàn Chỉnh</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- RIGHT COLUMN: Sticky CapCut Live Monitor & Audio Controller (4-5 cols) -->
                                        <div class="space-y-4 lg:col-span-5 xl:col-span-4 lg:sticky lg:top-6">
                                            <!-- MOCKUP ĐIỆN THOẠI 9:16 LIVE MONITOR -->
                                            <div
                                                class="relative mx-auto flex flex-col items-center justify-center overflow-hidden rounded-[36px] border-[5px] border-neutral-800 bg-neutral-950 text-white shadow-2xl transition-all duration-300 select-none ring-1 ring-white/10"
                                                :style="{
                                                    width: form.video_aspect_ratio === '9:16' ? '280px' : '100%',
                                                    height: form.video_aspect_ratio === '9:16' ? '496px' : '260px',
                                                    maxWidth: '100%',
                                                }"
                                            >
                                                <!-- Video / Keyframe Visual -->
                                                <video
                                                    v-if="stitchedVideoUrl || currentPreviewScene?.video_url"
                                                    :src="stitchedVideoUrl || currentPreviewScene.video_url"
                                                    autoplay
                                                    muted
                                                    loop
                                                    playsinline
                                                    class="absolute inset-0 size-full object-cover"
                                                />
                                                <img
                                                    v-else-if="currentPreviewScene?.start_image || currentPreviewScene?.end_image"
                                                    :src="currentPreviewScene.start_image || currentPreviewScene.end_image"
                                                    class="absolute inset-0 size-full object-cover transition-all duration-1000"
                                                    :class="isPlayingVideo ? 'scale-105 brightness-105 filter' : 'scale-100'"
                                                />
                                                <div
                                                    v-else-if="form.video_scenes.length === 0"
                                                    class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-neutral-900 via-neutral-950 to-black p-6 text-center"
                                                >
                                                    <div class="mb-3 flex size-12 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30">
                                                        <IconMovie class="size-6 text-amber-400" />
                                                    </div>
                                                    <p class="text-xs font-bold text-amber-300">
                                                        Live Monitor Sẵn Sàng
                                                    </p>
                                                    <p class="text-3xs mt-1.5 leading-relaxed text-neutral-400">
                                                        Bấm <strong>[ ✨ AI Lên Kịch Bản ]</strong> để bắt đầu xem trước phân cảnh!
                                                    </p>
                                                </div>
                                                <div
                                                    v-else
                                                    class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-amber-950 via-black to-neutral-950 p-6 text-center"
                                                >
                                                    <IconMovie class="mb-2 size-8 animate-pulse text-amber-400" />
                                                    <p class="text-xs font-bold text-amber-200">
                                                        Cảnh {{ activePreviewSceneIndex + 1 }}
                                                    </p>
                                                    <p class="mt-1 line-clamp-2 text-4xs text-neutral-400">
                                                        {{ currentPreviewScene?.context_prompt || 'Chưa có ảnh/video bối cảnh' }}
                                                    </p>
                                                </div>

                                                <!-- Top Status Header (Dynamic Island Style) -->
                                                <div class="absolute inset-x-3.5 top-3.5 z-20 flex items-center justify-between pointer-events-none">
                                                    <div class="pointer-events-auto inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-black/80 px-2.5 py-1 text-3xs font-extrabold text-amber-300 shadow-md backdrop-blur-md whitespace-nowrap">
                                                        <span class="size-1.5 animate-pulse rounded-full bg-emerald-400" v-if="isPlayingVideo" />
                                                        <span v-if="stitchedVideoUrl" class="text-emerald-400">🎬 Full Video</span>
                                                        <span v-else>Cảnh {{ form.video_scenes.length > 0 ? activePreviewSceneIndex + 1 : 0 }}/{{ form.video_scenes.length }}</span>
                                                        <span class="opacity-40">·</span>
                                                        <span>{{ formattedTimecode }}</span>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        class="pointer-events-auto flex cursor-pointer items-center rounded-full border border-white/15 bg-black/80 p-1.5 text-white backdrop-blur-md transition hover:bg-black/95"
                                                        @click="isPreviewModalOpen = true"
                                                        title="Toàn màn hình"
                                                    >
                                                        <IconArrowsMaximize class="size-3 text-amber-300" />
                                                    </button>
                                                </div>

                                                <!-- Karaoke-style Subtitles (Phụ đề CapCut) -->
                                                <div
                                                    v-if="form.video_auto_subtitles && currentPreviewScene?.voiceover_text"
                                                    class="absolute inset-x-3 bottom-14 z-20 flex items-center justify-center gap-2 rounded-xl border border-white/15 bg-black/85 p-2 text-center shadow-xl backdrop-blur-md"
                                                >
                                                    <img
                                                        v-if="form.character_enabled && form.character_avatar"
                                                        :src="form.character_avatar"
                                                        class="size-6 shrink-0 rounded-full border border-amber-400 object-cover shadow-xs"
                                                    />
                                                    <div class="min-w-0 text-left">
                                                        <p
                                                            v-if="form.character_enabled && form.character_name"
                                                            class="text-4xs font-bold text-amber-400 uppercase tracking-wider leading-none mb-0.5"
                                                        >
                                                            {{ form.character_name }}
                                                        </p>
                                                        <p class="text-3xs leading-snug font-bold tracking-wide text-amber-300 drop-shadow-md line-clamp-2">
                                                            "{{ currentPreviewScene.voiceover_text }}"
                                                        </p>
                                                    </div>
                                                </div>

                                                <!-- Scene Progress Line -->
                                                <div class="absolute inset-x-3 bottom-11 z-20 h-1 overflow-hidden rounded-full bg-white/20">
                                                    <div
                                                        class="h-full bg-amber-400 transition-all duration-75"
                                                        :style="{ width: `${sceneProgress}%` }"
                                                    />
                                                </div>

                                                <!-- Bottom Transport Bar -->
                                                <div class="absolute inset-x-3 bottom-2.5 z-20 flex items-center justify-between rounded-full border border-white/10 bg-black/80 px-3 py-1.5 shadow-lg backdrop-blur-md">
                                                    <button
                                                        type="button"
                                                        @click="toggleMute"
                                                        class="cursor-pointer text-white/80 transition hover:text-amber-400"
                                                        :title="isMuted ? 'Bật âm thanh' : 'Tắt tiếng'"
                                                    >
                                                        <IconVolumeOff v-if="isMuted" class="size-4 text-rose-400" />
                                                        <IconVolume2 v-else class="size-4" />
                                                    </button>

                                                    <div class="flex items-center gap-3">
                                                        <button
                                                            type="button"
                                                            @click="selectPreviewScene(Math.max(0, activePreviewSceneIndex - 1))"
                                                            class="cursor-pointer text-white transition hover:text-amber-400"
                                                            title="Scene trước"
                                                        >
                                                            <IconChevronLeft class="size-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            @click="toggleVideoPlay"
                                                            class="flex size-7 cursor-pointer items-center justify-center rounded-full bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow transition hover:from-amber-600 hover:to-amber-700 active:scale-95"
                                                        >
                                                            <IconPlayerPause v-if="isPlayingVideo" class="size-3.5" />
                                                            <IconPlayerPlay v-else class="ml-0.5 size-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            @click="selectPreviewScene((activePreviewSceneIndex + 1) % form.video_scenes.length)"
                                                            class="cursor-pointer text-white transition hover:text-amber-400"
                                                            title="Scene kế tiếp"
                                                        >
                                                            <IconChevronRight class="size-4" />
                                                        </button>
                                                    </div>

                                                    <span class="text-3xs font-bold text-amber-300">
                                                        {{ currentPreviewScene?.duration || 8 }}s
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Nhạc Nền (BGM) & Âm Lượng -->
                                            <div class="rounded-2xl border border-border/80 bg-card p-3.5 shadow-xs space-y-2.5">
                                                <div class="flex items-center justify-between text-xs">
                                                    <div class="flex items-center gap-1.5 font-bold text-foreground">
                                                        <IconMusic class="size-4 text-primary" />
                                                        <span>Nhạc nền video (BGM)</span>
                                                    </div>
                                                    <span class="text-3xs font-bold text-primary">
                                                        Âm lượng: {{ form.video_bgm_volume }}%
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-2.5">
                                                    <select
                                                        v-model="form.video_bgm_track"
                                                        @change="selectBgmTrack(form.video_bgm_track)"
                                                        class="h-9 min-w-0 flex-1 cursor-pointer rounded-lg border border-border/80 bg-background px-2.5 text-xs font-medium text-foreground"
                                                    >
                                                        <option
                                                            v-for="t in presetBgmTracks"
                                                            :key="t.id"
                                                            :value="t.id"
                                                        >
                                                            {{ t.title }} ({{ t.duration }})
                                                        </option>
                                                    </select>
                                                    <input
                                                        v-model.number="form.video_bgm_volume"
                                                        type="range"
                                                        min="0"
                                                        max="100"
                                                        class="w-24 shrink-0 cursor-pointer accent-primary"
                                                        title="Âm lượng nhạc nền"
                                                    />
                                                </div>
                                                <!-- Hidden audio player for BGM -->
                                                <audio
                                                    ref="bgmAudioRef"
                                                    :src="currentBgmUrl"
                                                    loop
                                                    preload="auto"
                                                    class="hidden"
                                                />
                                            </div>

                                            <!-- Card Thông Báo Video Hoàn Chỉnh Khi Ghép Xong -->
                                            <div
                                                v-if="stitchedVideoUrl"
                                                class="space-y-2 rounded-2xl border-2 border-emerald-500/40 bg-emerald-500/10 p-3.5 shadow-sm"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-1.5">
                                                        <IconCircleCheck class="size-4 text-emerald-500" />
                                                        <span class="text-xs font-bold text-foreground">Video đã ghép hoàn chỉnh!</span>
                                                    </div>
                                                    <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-4xs font-bold text-white">
                                                        {{ form.video_resolution }}
                                                    </span>
                                                </div>
                                                <a
                                                    :href="stitchedVideoUrl"
                                                    download="king-coffee-video-commercial.mp4"
                                                    target="_blank"
                                                    class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white shadow transition hover:bg-emerald-700"
                                                >
                                                    <IconDownload class="size-4" />
                                                    <span>Tải Video MP4 Về Máy</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Navigation Buttons for Step 2 -->
                                <div
                                    class="flex items-center justify-between border-t pt-4"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl border bg-background px-5 py-2.5 text-xs font-bold text-foreground shadow-2xs transition hover:bg-muted"
                                        @click="currentStep = 1"
                                    >
                                        <IconChevronLeft class="size-4" />
                                        <span>Quay lại Bước 1</span>
                                    </button>

                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-primary px-6 py-3 text-xs font-bold text-primary-foreground shadow-sm transition hover:bg-primary/90"
                                        @click="currentStep = 3"
                                    >
                                        <span
                                            >Tiếp theo: Lên lịch & Đăng
                                            bài</span
                                        >
                                        <IconChevronRight class="size-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- BƯỚC 3: LỊCH TRÌNH VÀ DUYỆT BÀI -->
                            <div
                                v-show="currentStep === 3"
                                class="space-y-6 rounded-2xl border bg-card p-5 shadow-2xs sm:p-6"
                            >
                                <div
                                    class="flex items-center gap-3 border-b pb-4"
                                >
                                    <div
                                        class="flex size-10 items-center justify-center rounded-xl bg-emerald-500 font-bold text-white shadow-xs"
                                    >
                                        <IconCalendar class="size-5" />
                                    </div>
                                    <div>
                                        <h2
                                            class="text-base font-bold text-foreground"
                                        >
                                            Bước 3: Lịch trình Đăng bài & Duyệt
                                            nội dung
                                        </h2>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Thiết lập tần suất, ngày bắt đầu và
                                            quy trình phê duyệt tự động.
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label
                                        class="block space-y-1.5 text-xs font-bold text-foreground"
                                    >
                                        Quy trình duyệt bài (Workflow)
                                        <select
                                            v-model="form.content_workflow_id"
                                            class="h-10 w-full rounded-lg border bg-background px-3 text-xs font-medium focus:ring-primary"
                                        >
                                            <option
                                                v-for="workflow in contentWorkflows"
                                                :key="workflow.id"
                                                :value="workflow.id"
                                            >
                                                {{ workflow.name }}
                                            </option>
                                        </select>
                                    </label>

                                    <label
                                        class="block space-y-1.5 text-xs font-bold text-foreground"
                                    >
                                        Thời điểm bắt đầu đăng
                                        <input
                                            v-model="form.start_at"
                                            type="datetime-local"
                                            class="h-10 w-full rounded-lg border bg-background px-3 text-xs font-medium focus:ring-primary"
                                        />
                                    </label>

                                    <label
                                        class="block space-y-1.5 text-xs font-bold text-foreground"
                                    >
                                        Tổng số lượng bài viết tạo ra
                                        <input
                                            v-model.number="form.total_posts"
                                            type="number"
                                            min="1"
                                            max="90"
                                            class="h-10 w-full rounded-lg border bg-background px-3 text-xs font-medium focus:ring-primary"
                                        />
                                    </label>

                                    <label
                                        class="block space-y-1.5 text-xs font-bold text-foreground"
                                    >
                                        Khoảng cách giữa các bài (ngày)
                                        <input
                                            v-model.number="form.interval_days"
                                            type="number"
                                            min="1"
                                            max="30"
                                            class="h-10 w-full rounded-lg border bg-background px-3 text-xs font-medium focus:ring-primary"
                                        />
                                    </label>
                                </div>

                                <div
                                    class="space-y-3 rounded-xl border bg-muted/15 p-4"
                                >
                                    <label
                                        class="flex cursor-pointer items-center gap-2.5 text-xs font-bold text-foreground"
                                    >
                                        <input
                                            v-model="form.diff_content_per_page"
                                            type="checkbox"
                                            class="size-4 rounded border-gray-300 text-primary focus:ring-primary"
                                        />
                                        <span
                                            >Mỗi trang đích đăng một nội dung
                                            khác nhau (AI tạo nhiều biến thể độc
                                            lập)</span
                                        >
                                    </label>

                                    <label
                                        class="flex cursor-pointer items-center gap-2.5 text-xs font-bold text-foreground"
                                    >
                                        <input
                                            v-model="form.require_approval"
                                            type="checkbox"
                                            class="size-4 rounded border-gray-300 text-primary focus:ring-primary"
                                        />
                                        <span
                                            >Gửi bản nháp duyệt trước khi tự
                                            động đăng bài lên mạng xã hội</span
                                        >
                                    </label>
                                </div>

                                <div
                                    class="flex items-center justify-between pt-2"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl border bg-background px-4 py-2 text-xs font-bold text-foreground transition hover:bg-muted"
                                        @click="currentStep = 2"
                                    >
                                        <IconChevronLeft class="size-4" />
                                        <span>Quay lại</span>
                                    </button>

                                    <button
                                        type="submit"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-xs font-bold text-white shadow-md transition hover:bg-emerald-700 disabled:opacity-60"
                                        :disabled="
                                            submitting ||
                                            !form.target_social_account_ids
                                                .length
                                        "
                                    >
                                        <IconLoader
                                            v-if="submitting"
                                            class="size-4 animate-spin"
                                        />
                                        <IconCheck v-else class="size-4" />
                                        <span
                                            >Kích hoạt Chiến dịch Clone
                                            Ngay</span
                                        >
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Cột xem trước (Live Preview Card) -->
                        <div
                            v-if="
                                !(
                                    currentStep === 2 &&
                                    aiContentMode === 'video_ai'
                                )
                            "
                            class="sticky top-6 space-y-5 rounded-2xl border bg-card p-5 shadow-2xs sm:p-6"
                        >
                            <div
                                class="flex items-center justify-between border-b pb-4"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="flex size-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400"
                                    >
                                        <IconEye class="size-5" />
                                    </div>
                                    <div>
                                        <h3
                                            class="text-sm font-bold text-foreground"
                                        >
                                            Bản xem trước Live
                                        </h3>
                                        <p
                                            class="text-3xs text-muted-foreground"
                                        >
                                            Mô phỏng hiển thị trên mạng xã hội
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="
                                            suggestions.length > 0 ||
                                            aiContentMode === 'video_ai'
                                        "
                                        type="button"
                                        class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-xl border bg-background px-3 text-xs font-bold text-foreground shadow-2xs transition hover:bg-muted/60"
                                        @click="isPreviewModalOpen = true"
                                        title="Mở toàn màn hình xem trước chi tiết"
                                    >
                                        <IconArrowsMaximize
                                            class="size-4 text-muted-foreground"
                                        />
                                        <span class="hidden sm:inline"
                                            >Phóng to</span
                                        >
                                    </button>

                                    <button
                                        type="button"
                                        class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 px-4 text-xs font-bold text-white shadow-xs transition hover:from-amber-600 hover:to-amber-700 disabled:opacity-60"
                                        :disabled="
                                            previewing ||
                                            (sourceInputMode === 'select'
                                                ? !form.source_post_id
                                                : !manualSourceContent.trim()) ||
                                            !form.target_social_account_ids
                                                .length
                                        "
                                        @click="generatePreview"
                                    >
                                        <IconLoader
                                            v-if="previewing"
                                            class="size-4 animate-spin"
                                        />
                                        <IconSparkles v-else class="size-4" />
                                        <span>Tạo gợi ý AI</span>
                                    </button>
                                </div>
                            </div>

                            <p
                                v-if="previewError"
                                class="flex items-center gap-2 rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive"
                            >
                                <IconX class="size-4 shrink-0" />
                                <span>{{ previewError }}</span>
                            </p>

                            <!-- CAPCUT STUDIO VIDEO LIVE MONITOR -->
                            <div
                                v-if="aiContentMode === 'video_ai'"
                                class="space-y-3.5"
                            >
                                <div
                                    class="relative mx-auto flex flex-col items-center justify-center overflow-hidden rounded-2xl border-4 border-foreground/20 bg-black text-white shadow-2xl transition-all duration-300 select-none"
                                    :style="{
                                        width:
                                            form.video_aspect_ratio === '9:16'
                                                ? '270px'
                                                : '100%',
                                        height:
                                            form.video_aspect_ratio === '9:16'
                                                ? '460px'
                                                : '240px',
                                    }"
                                >
                                    <!-- Video / Keyframe Visual -->
                                    <video
                                        v-if="currentPreviewScene?.video_url"
                                        :src="currentPreviewScene.video_url"
                                        autoplay
                                        muted
                                        loop
                                        playsinline
                                        class="absolute inset-0 size-full object-cover"
                                    />
                                    <img
                                        v-else-if="
                                            currentPreviewScene?.start_image ||
                                            currentPreviewScene?.end_image
                                        "
                                        :src="
                                            currentPreviewScene.start_image ||
                                            currentPreviewScene.end_image
                                        "
                                        class="absolute inset-0 size-full object-cover transition-all duration-1000"
                                        :class="
                                            isPlayingVideo
                                                ? 'scale-105 brightness-105 filter'
                                                : 'scale-100'
                                        "
                                    />
                                    <div
                                        v-else
                                        class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-amber-950 via-black to-red-950 p-4 text-center"
                                    >
                                        <IconMovie
                                            class="mb-2 size-8 animate-pulse text-amber-400"
                                        />
                                        <p
                                            class="text-xs font-bold text-amber-200"
                                        >
                                            Scene
                                            {{ activePreviewSceneIndex + 1 }}
                                        </p>
                                        <p
                                            class="text-3xs mt-1 line-clamp-2 text-muted-foreground"
                                        >
                                            {{
                                                currentPreviewScene?.context_prompt ||
                                                'Chưa tải ảnh bối cảnh'
                                            }}
                                        </p>
                                    </div>

                                    <!-- Top Status Glassmorphism Header -->
                                    <div
                                        class="absolute inset-x-2.5 top-2.5 z-20 flex items-center justify-between"
                                    >
                                        <div
                                            class="text-3xs flex items-center gap-1.5 rounded-full border border-white/10 bg-black/70 px-2.5 py-1 font-extrabold text-amber-300 shadow-sm backdrop-blur-md"
                                        >
                                            <span
                                                class="size-1.5 animate-pulse rounded-full bg-emerald-400"
                                                v-if="isPlayingVideo"
                                            />
                                            <span
                                                >S{{
                                                    activePreviewSceneIndex + 1
                                                }}/{{
                                                    form.video_scenes.length
                                                }}</span
                                            >
                                            <span class="opacity-50">·</span>
                                            <span>{{ formattedTimecode }}</span>
                                        </div>

                                        <div class="flex items-center gap-1">
                                            <!-- Audio Waveform Live Indicator -->
                                            <div
                                                v-if="
                                                    isPlayingVideo && !isMuted
                                                "
                                                class="flex h-3.5 items-end gap-0.5 rounded-full border border-white/10 bg-black/60 px-1.5 py-0.5 backdrop-blur-md"
                                            >
                                                <span
                                                    class="h-2 w-0.5 animate-bounce rounded-full bg-emerald-400"
                                                />
                                                <span
                                                    class="h-3 w-0.5 animate-bounce rounded-full bg-emerald-400 delay-75"
                                                />
                                                <span
                                                    class="h-1.5 w-0.5 animate-bounce rounded-full bg-emerald-400 delay-150"
                                                />
                                            </div>

                                            <button
                                                type="button"
                                                class="text-3xs flex cursor-pointer items-center gap-1 rounded-full border border-white/10 bg-black/70 px-2 py-1 font-bold text-white backdrop-blur-md transition hover:bg-black/90"
                                                @click="
                                                    isPreviewModalOpen = true
                                                "
                                                title="Mở toàn màn hình"
                                            >
                                                <IconArrowsMaximize
                                                    class="size-3 text-amber-300"
                                                />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Karaoke-style Subtitles (Phụ đề CapCut) -->
                                    <div
                                        v-if="
                                            form.video_auto_subtitles &&
                                            currentPreviewScene?.voiceover_text
                                        "
                                        class="absolute inset-x-3 bottom-14 z-20 flex items-center justify-center gap-2 rounded-xl border border-white/15 bg-black/85 p-2 text-center shadow-lg backdrop-blur-md"
                                    >
                                        <img
                                            v-if="form.character_enabled && form.character_avatar"
                                            :src="form.character_avatar"
                                            :alt="form.character_name || 'Nhân vật AI'"
                                            class="size-6 shrink-0 rounded-full border border-amber-400 object-cover shadow-xs"
                                        />
                                        <div class="min-w-0 text-left">
                                            <p
                                                v-if="form.character_enabled && form.character_name"
                                                class="text-4xs font-bold text-amber-400 uppercase tracking-wider"
                                            >
                                                {{ form.character_name }}
                                            </p>
                                            <p
                                                class="text-xs leading-tight font-black tracking-wide text-amber-300 drop-shadow-md"
                                            >
                                                "{{
                                                    currentPreviewScene.voiceover_text
                                                }}"
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Scene Progress Scrubber Line -->
                                    <div
                                        class="absolute inset-x-3 bottom-11 z-20 h-1 overflow-hidden rounded-full bg-white/20"
                                    >
                                        <div
                                            class="h-full bg-amber-400 transition-all duration-75"
                                            :style="{
                                                width: `${sceneProgress}%`,
                                            }"
                                        />
                                    </div>

                                    <!-- Bottom Transport Bar -->
                                    <div
                                        class="absolute inset-x-2.5 bottom-2 z-20 flex items-center justify-between rounded-full border border-white/10 bg-black/75 px-3 py-1.5 shadow-lg backdrop-blur-md"
                                    >
                                        <button
                                            type="button"
                                            @click="toggleMute"
                                            class="text-white/80 transition hover:text-amber-400"
                                            :title="
                                                isMuted
                                                    ? 'Bật âm thanh'
                                                    : 'Tắt tiếng'
                                            "
                                        >
                                            <IconVolumeOff
                                                v-if="isMuted"
                                                class="size-4 text-rose-400"
                                            />
                                            <IconVolume2
                                                v-else
                                                class="size-4"
                                            />
                                        </button>

                                        <div class="flex items-center gap-3">
                                            <button
                                                type="button"
                                                @click="
                                                    selectPreviewScene(
                                                        Math.max(
                                                            0,
                                                            activePreviewSceneIndex -
                                                                1,
                                                        ),
                                                    )
                                                "
                                                class="cursor-pointer text-white transition hover:text-amber-400"
                                                title="Scene trước"
                                            >
                                                <IconChevronLeft
                                                    class="size-4"
                                                />
                                            </button>
                                            <button
                                                type="button"
                                                @click="toggleVideoPlay"
                                                class="flex size-7 transform cursor-pointer items-center justify-center rounded-full bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-md transition hover:from-amber-600 hover:to-amber-700 active:scale-95"
                                                :title="
                                                    isPlayingVideo
                                                        ? 'Tạm dừng'
                                                        : 'Phát kịch bản'
                                                "
                                            >
                                                <IconPlayerPause
                                                    v-if="isPlayingVideo"
                                                    class="size-3.5"
                                                />
                                                <IconPlayerPlay
                                                    v-else
                                                    class="ml-0.5 size-3.5"
                                                />
                                            </button>
                                            <button
                                                type="button"
                                                @click="
                                                    selectPreviewScene(
                                                        (activePreviewSceneIndex +
                                                            1) %
                                                            form.video_scenes
                                                                .length,
                                                    )
                                                "
                                                class="cursor-pointer text-white transition hover:text-amber-400"
                                                title="Scene kế tiếp"
                                            >
                                                <IconChevronRight
                                                    class="size-4"
                                                />
                                            </button>
                                        </div>

                                        <span
                                            class="text-3xs font-bold text-amber-300"
                                        >
                                            {{ currentPreviewScene?.duration }}s
                                        </span>
                                    </div>
                                </div>

                                <!-- Audio Player Controller Under Monitor -->
                                <div
                                    class="text-3xs space-y-2 rounded-xl border bg-muted/20 p-3"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="flex items-center gap-1.5 font-bold text-emerald-600 text-foreground dark:text-emerald-400"
                                        >
                                            <IconMusic class="size-3.5" />
                                            <span
                                                class="max-w-[140px] truncate"
                                            >
                                                {{
                                                    presetBgmTracks.find(
                                                        (t) =>
                                                            t.id ===
                                                            form.video_bgm_track,
                                                    )?.title ||
                                                    (form.video_bgm_track ===
                                                    'custom_upload'
                                                        ? 'Nhạc tải lên'
                                                        : 'Âm thanh CapCut')
                                                }}
                                            </span>
                                        </span>
                                        <div class="flex items-center gap-2">
                                            <span class="text-muted-foreground"
                                                >{{
                                                    form.video_bgm_volume
                                                }}%</span
                                            >
                                            <input
                                                v-model.number="
                                                    form.video_bgm_volume
                                                "
                                                type="range"
                                                min="0"
                                                max="100"
                                                class="w-16 cursor-pointer accent-primary"
                                            />
                                        </div>
                                    </div>

                                    <!-- Hidden HTML5 Audio Element for live preview sync -->
                                    <audio
                                        ref="bgmAudioRef"
                                        :src="currentBgmUrl"
                                        loop
                                        preload="auto"
                                        class="hidden"
                                    />
                                </div>
                            </div>

                            <!-- TEXT & IMAGE PREVIEW -->
                            <template v-else>
                                <div
                                    v-if="!suggestions.length && !previewing"
                                    class="space-y-3 rounded-2xl border-2 border-dashed border-border/80 bg-gradient-to-b from-amber-50/20 to-transparent p-8 text-center dark:from-amber-950/10"
                                >
                                    <div
                                        class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 shadow-xs dark:text-amber-400"
                                    >
                                        <IconSparkles class="size-6" />
                                    </div>
                                    <div class="space-y-1">
                                        <p
                                            class="text-sm font-bold text-foreground"
                                        >
                                            Chưa có bản xem trước
                                        </p>
                                        <p
                                            class="mx-auto max-w-xs text-xs text-muted-foreground"
                                        >
                                            Bấm
                                            <strong>"Tạo gợi ý AI"</strong> để
                                            xem trước bài viết và hình ảnh do AI
                                            sinh theo cấu hình.
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 px-4 text-xs font-bold text-white shadow-xs transition hover:from-amber-600 hover:to-amber-700"
                                        :disabled="
                                            previewing ||
                                            (sourceInputMode === 'select'
                                                ? !form.source_post_id
                                                : !manualSourceContent.trim()) ||
                                            !form.target_social_account_ids
                                                .length
                                        "
                                        @click="generatePreview"
                                    >
                                        <IconSparkles class="size-3.5" />
                                        <span>Tạo Gợi Ý Ngay</span>
                                    </button>
                                </div>

                                <div
                                    v-if="previewing"
                                    class="space-y-3 rounded-2xl border border-dashed bg-muted/10 py-14 text-center"
                                >
                                    <IconLoader
                                        class="mx-auto size-8 animate-spin text-primary"
                                    />
                                    <p
                                        class="text-xs font-semibold text-foreground"
                                    >
                                        {{
                                            previewStatusMsg ||
                                            'AI đang sáng tạo nội dung & vẽ ảnh...'
                                        }}
                                    </p>
                                    <p class="text-3xs text-muted-foreground">
                                        Quá trình có thể mất vài giây tùy vào độ
                                        phân giải ảnh
                                    </p>
                                </div>

                                <div
                                    v-if="suggestions.length && !previewing"
                                    class="space-y-4"
                                >
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <div class="flex flex-1 gap-1.5">
                                            <button
                                                v-for="(
                                                    suggestion, index
                                                ) in suggestions"
                                                :key="index"
                                                type="button"
                                                class="flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-xl border-2 px-2.5 py-2 text-center text-xs font-bold transition"
                                                :class="
                                                    selectedSuggestion ===
                                                    suggestion
                                                        ? 'border-primary bg-primary/10 text-primary shadow-xs'
                                                        : 'border-border bg-card text-muted-foreground hover:bg-muted/50'
                                                "
                                                @click="
                                                    chooseSuggestion(suggestion)
                                                "
                                            >
                                                <IconCheck
                                                    v-if="
                                                        selectedSuggestion ===
                                                        suggestion
                                                    "
                                                    class="size-3.5"
                                                />
                                                <span
                                                    >Gợi ý {{ index + 1 }}</span
                                                >
                                            </button>
                                        </div>

                                        <button
                                            type="button"
                                            class="inline-flex cursor-pointer items-center gap-1 rounded-xl border bg-background px-3 py-2 text-xs font-bold text-foreground shadow-2xs transition hover:bg-muted"
                                            @click="isPreviewModalOpen = true"
                                            title="Xem toàn màn hình"
                                        >
                                            <IconArrowsMaximize
                                                class="size-3.5 text-muted-foreground"
                                            />
                                            <span>Mở to</span>
                                        </button>
                                    </div>

                                    <div
                                        v-if="previewPlatform"
                                        class="group relative overflow-hidden rounded-2xl border-2 bg-background shadow-md"
                                    >
                                        <div
                                            class="text-3xs flex items-center justify-between border-b bg-muted/30 px-3.5 py-2 font-semibold text-muted-foreground"
                                        >
                                            <span
                                                >Bản mô phỏng xem trước
                                                Live</span
                                            >
                                            <span
                                                class="font-bold tracking-wider text-foreground uppercase"
                                                >{{ previewPlatform }}</span
                                            >
                                        </div>
                                        <PlatformPreview
                                            :platform="previewPlatform"
                                            :social-account="
                                                previewSocialAccount
                                            "
                                            :content="
                                                selectedSuggestion?.content ||
                                                ''
                                            "
                                            :media="
                                                selectedSuggestion?.media || []
                                            "
                                        />
                                    </div>

                                    <!-- Media Download Strip -->
                                    <div
                                        v-if="
                                            selectedSuggestion?.media &&
                                            selectedSuggestion.media.length > 0
                                        "
                                        class="space-y-2.5 rounded-2xl border bg-muted/15 p-3.5"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="flex items-center gap-1.5 text-xs font-bold text-foreground"
                                            >
                                                <IconPhoto
                                                    class="size-4 text-primary"
                                                />
                                                <span
                                                    >Ảnh đính kèm AI ({{
                                                        selectedSuggestion.media
                                                            .length
                                                    }}
                                                    ảnh)</span
                                                >
                                            </span>
                                            <button
                                                type="button"
                                                class="flex cursor-pointer items-center gap-1 text-xs font-bold text-primary hover:underline"
                                                @click="
                                                    downloadAllSuggestionMedia
                                                "
                                            >
                                                <IconDownload
                                                    class="size-3.5"
                                                />
                                                <span>Tải tất cả</span>
                                            </button>
                                        </div>
                                        <div class="flex flex-wrap gap-2.5">
                                            <div
                                                v-for="(
                                                    m, idx
                                                ) in selectedSuggestion.media"
                                                :key="m.url"
                                                class="group/item relative size-16 overflow-hidden rounded-xl border-2 bg-background shadow-xs"
                                            >
                                                <img
                                                    :src="m.url"
                                                    class="size-full object-cover transition duration-300 group-hover/item:scale-105"
                                                />
                                                <button
                                                    type="button"
                                                    class="absolute inset-0 flex cursor-pointer items-center justify-center bg-black/60 text-white opacity-0 transition group-hover/item:opacity-100"
                                                    title="Tải ảnh này về máy"
                                                    @click="
                                                        downloadMedia(
                                                            m.url,
                                                            `king-coffee-image-${idx + 1}.jpg`,
                                                        )
                                                    "
                                                >
                                                    <IconDownload
                                                        class="size-4.5"
                                                    />
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <label
                                        class="block space-y-1.5 pt-1 text-xs font-bold text-foreground"
                                    >
                                        Nội dung bài đăng mẫu (Có thể chỉnh sửa
                                        trước khi xuất bản)
                                        <textarea
                                            v-model="form.initial_content"
                                            rows="4"
                                            class="w-full rounded-xl border bg-card p-3 text-xs leading-relaxed focus:ring-primary"
                                        />
                                    </label>
                                </div>
                            </template>

                            <!-- Nút Kích hoạt nhanh ngay dưới Preview -->
                            <div class="border-t pt-4">
                                <Button
                                    type="button"
                                    class="h-11 w-full cursor-pointer gap-2 rounded-xl bg-emerald-600 text-xs font-bold text-white shadow-md hover:bg-emerald-700 disabled:opacity-60"
                                    :disabled="
                                        submitting ||
                                        !form.target_social_account_ids.length
                                    "
                                    @click="submit"
                                >
                                    <IconLoader
                                        v-if="submitting"
                                        class="size-4 animate-spin"
                                    />
                                    <IconCheck v-else class="size-4" />
                                    <span>Kích hoạt Chiến dịch Clone Ngay</span>
                                </Button>
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <!-- TAB 2: CHIẾN DỊCH ĐANG CHẠY -->
                <TabsContent value="campaigns" class="mt-0">
                    <div class="space-y-4">
                        <!-- Bộ lọc danh sách chiến dịch -->
                        <div
                            class="flex flex-col items-center justify-between gap-3 rounded-xl border bg-card p-4 shadow-2xs sm:flex-row"
                        >
                            <div class="relative w-full sm:max-w-xs">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground"
                                >
                                    <IconSearch class="size-4" />
                                </span>
                                <input
                                    v-model="campaignSearchTerm"
                                    type="text"
                                    placeholder="Tìm kiếm chủ đề..."
                                    class="h-9 w-full rounded-md border bg-background pr-3 pl-9 text-sm focus:ring-primary"
                                />
                            </div>
                            <div
                                class="flex w-full items-center justify-end gap-3 sm:w-auto"
                            >
                                <span
                                    class="flex items-center gap-1 text-xs font-semibold text-muted-foreground"
                                >
                                    <IconFilter class="size-3.5" /> Lọc trạng
                                    thái:
                                </span>
                                <select
                                    v-model="campaignStatusFilter"
                                    class="h-9 rounded-md border bg-background px-3 text-xs focus:ring-primary"
                                >
                                    <option value="all">
                                        Tất cả chiến dịch
                                    </option>
                                    <option value="active">Đang chạy</option>
                                    <option value="inactive">
                                        Đã dừng / Hoàn thành
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div
                            v-if="!filteredCampaigns.length"
                            class="rounded-xl border border-dashed bg-card p-12 text-center text-sm text-muted-foreground shadow-2xs"
                        >
                            Không tìm thấy chiến dịch nào phù hợp với bộ lọc của
                            bạn.
                        </div>

                        <div v-else class="grid gap-4 md:grid-cols-2">
                            <div
                                v-for="campaign in filteredCampaigns"
                                :key="campaign.id"
                                class="flex flex-col justify-between rounded-xl border bg-card p-5 shadow-2xs transition-colors hover:border-muted-foreground/30"
                            >
                                <div class="space-y-3">
                                    <div
                                        class="flex items-start justify-between gap-4"
                                    >
                                        <div class="min-w-0">
                                            <h3
                                                class="truncate text-sm leading-snug font-bold tracking-tight text-foreground"
                                            >
                                                {{
                                                    campaign.theme ||
                                                    'Clone từ bài nguồn'
                                                }}
                                            </h3>
                                            <p
                                                class="text-2xs mt-0.5 flex items-center gap-1.5 text-muted-foreground"
                                            >
                                                <span
                                                    >Khoảng cách: mỗi
                                                    {{ campaign.interval_days }}
                                                    ngày</span
                                                >
                                                <span>·</span>
                                                <span
                                                    >Workflow:
                                                    {{
                                                        campaign
                                                            .content_workflow
                                                            ?.name || 'Mặc định'
                                                    }}</span
                                                >
                                            </p>
                                        </div>
                                        <span
                                            class="text-3xs inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-bold tracking-wider uppercase"
                                            :class="
                                                campaign.is_active
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400'
                                                    : 'bg-muted text-muted-foreground'
                                            "
                                        >
                                            {{
                                                campaign.is_active
                                                    ? 'Đang chạy'
                                                    : 'Đã dừng'
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="line-clamp-3 rounded-lg border bg-muted/20 p-2.5 text-xs whitespace-pre-line text-foreground/80"
                                    >
                                        {{ campaign.source_post?.content }}
                                    </div>

                                    <!-- Tiến trình (Progress bar) -->
                                    <div class="space-y-1">
                                        <div
                                            class="text-2xs flex justify-between font-semibold text-muted-foreground"
                                        >
                                            <span
                                                >Tiến độ generated:
                                                {{
                                                    campaign.generated_posts
                                                }}/{{
                                                    campaign.total_posts
                                                }}
                                                bài</span
                                            >
                                            <span
                                                >{{
                                                    Math.round(
                                                        (campaign.generated_posts /
                                                            campaign.total_posts) *
                                                            100,
                                                    )
                                                }}%</span
                                            >
                                        </div>
                                        <div
                                            class="h-2 w-full overflow-hidden rounded-full bg-muted"
                                        >
                                            <div
                                                class="h-full bg-primary transition-all duration-300"
                                                :style="{
                                                    width: `${(campaign.generated_posts / campaign.total_posts) * 100}%`,
                                                }"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="text-3xs mt-4 flex items-center justify-between border-t pt-3 font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    <div>
                                        Bắt đầu:
                                        {{ formatDate(campaign.start_at) }}
                                    </div>
                                    <button
                                        v-if="campaign.is_active"
                                        type="button"
                                        class="text-2xs inline-flex items-center gap-1.5 rounded-md border border-transparent px-3 py-1.5 font-bold text-destructive transition-colors hover:border-destructive/30 hover:bg-destructive/10"
                                        @click="stop(campaign)"
                                    >
                                        <IconPlayerStop class="size-3.5" /> Dừng
                                        chạy
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <!-- TAB 3: LỊCH SỬ CLONE (POSTS GENERATED) -->
                <TabsContent value="history" class="mt-0">
                    <div class="space-y-4">
                        <!-- Bộ tìm kiếm & Lọc trên lịch sử -->
                        <div
                            class="flex flex-col items-center justify-between gap-4 rounded-xl border bg-card p-4 shadow-2xs md:flex-row"
                        >
                            <div class="relative w-full md:max-w-md">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground"
                                >
                                    <IconSearch class="size-4" />
                                </span>
                                <input
                                    v-model="searchTerm"
                                    type="text"
                                    placeholder="Tìm kiếm nội dung bài viết đã tạo..."
                                    class="h-10 w-full rounded-md border bg-background pr-3 pl-9 text-sm focus:ring-primary"
                                />
                            </div>

                            <div
                                class="flex w-full flex-wrap items-center justify-end gap-3 md:w-auto"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-xs font-semibold text-muted-foreground"
                                        >Lọc trạng thái:</span
                                    >
                                    <select
                                        v-model="statusFilter"
                                        class="h-9 rounded-md border bg-background px-3 text-xs focus:ring-primary"
                                    >
                                        <option value="all">Tất cả</option>
                                        <option value="published">
                                            Đã đăng
                                        </option>
                                        <option value="pending_review">
                                            Chờ duyệt
                                        </option>
                                        <option value="scheduled">
                                            Đã lên lịch
                                        </option>
                                        <option value="failed">Lỗi</option>
                                    </select>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-xs font-semibold text-muted-foreground"
                                        >Mỗi trang:</span
                                    >
                                    <select
                                        v-model="perPage"
                                        class="h-9 rounded-md border bg-background px-3 text-xs focus:ring-primary"
                                    >
                                        <option :value="5">5</option>
                                        <option :value="10">10</option>
                                        <option :value="20">20</option>
                                        <option :value="50">50</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Bảng danh sách bài viết -->
                        <div
                            v-if="!filteredPosts.length"
                            class="rounded-xl border border-dashed bg-card p-12 text-center text-sm text-muted-foreground shadow-2xs"
                        >
                            Không tìm thấy bài viết nào trong lịch sử clone.
                        </div>

                        <div
                            v-else
                            class="overflow-hidden rounded-xl border bg-card shadow-2xs"
                        >
                            <div class="overflow-x-auto">
                                <table
                                    class="w-full min-w-[760px] text-left text-sm"
                                >
                                    <thead
                                        class="border-b bg-muted/40 text-xs font-semibold text-muted-foreground"
                                    >
                                        <tr>
                                            <th class="px-4 py-3.5 font-bold">
                                                Nội dung bài viết
                                            </th>
                                            <th class="px-4 py-3.5 font-bold">
                                                Chiến dịch
                                            </th>
                                            <th class="px-4 py-3.5 font-bold">
                                                Trang đích
                                            </th>
                                            <th class="px-4 py-3.5 font-bold">
                                                Trạng thái
                                            </th>
                                            <th class="px-4 py-3.5 font-bold">
                                                Thời gian
                                            </th>
                                            <th
                                                class="px-4 py-3.5 text-right font-bold"
                                            />
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr
                                            v-for="post in paginatedPosts"
                                            :key="post.id"
                                            class="align-middle transition-colors hover:bg-muted/10"
                                        >
                                            <td
                                                class="max-w-[340px] px-4 py-3.5"
                                            >
                                                <p
                                                    class="line-clamp-2 font-medium text-foreground"
                                                >
                                                    {{ post.content }}
                                                </p>
                                            </td>
                                            <td class="px-4 py-3.5">
                                                <span
                                                    class="text-xs font-medium text-foreground"
                                                    >{{
                                                        post.campaignTheme
                                                    }}</span
                                                >
                                            </td>
                                            <td
                                                class="max-w-[200px] px-4 py-3.5"
                                            >
                                                <span
                                                    class="block truncate text-xs text-muted-foreground"
                                                    :title="targetNames(post)"
                                                >
                                                    {{ targetNames(post) }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-4 py-3.5 whitespace-nowrap"
                                            >
                                                <span
                                                    class="text-2xs inline-flex rounded-full px-2.5 py-0.5 font-semibold tracking-wider uppercase"
                                                    :class="statusClass(post)"
                                                >
                                                    {{ statusLabel(post) }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-4 py-3.5 text-xs whitespace-nowrap text-muted-foreground"
                                            >
                                                {{
                                                    formatDate(
                                                        post.published_at ||
                                                            post.scheduled_at,
                                                    )
                                                }}
                                            </td>
                                            <td
                                                class="px-4 py-3.5 text-right whitespace-nowrap"
                                            >
                                                <a
                                                    :href="
                                                        editPost.url(post.id)
                                                    "
                                                    class="inline-flex items-center gap-1 rounded px-2.5 py-1 text-xs font-bold text-primary transition-colors hover:bg-primary/10"
                                                >
                                                    Mở bài
                                                    <IconExternalLink
                                                        class="size-3.5"
                                                    />
                                                </a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Footer phân trang -->
                            <div
                                class="flex flex-col items-center justify-between gap-3 border-t bg-muted/20 px-4 py-3.5 text-xs font-medium text-muted-foreground sm:flex-row"
                            >
                                <div>
                                    Hiển thị từ
                                    {{ (currentPage - 1) * perPage + 1 }} đến
                                    {{
                                        Math.min(
                                            currentPage * perPage,
                                            filteredPosts.length,
                                        )
                                    }}
                                    trong tổng số {{ filteredPosts.length }} bài
                                    viết
                                </div>

                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-md border bg-card text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="currentPage === 1"
                                        @click="currentPage--"
                                    >
                                        <IconChevronLeft class="size-4" />
                                    </button>

                                    <div class="flex items-center gap-1">
                                        <button
                                            v-for="p in totalPages"
                                            :key="p"
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-md border text-xs font-bold transition"
                                            :class="
                                                currentPage === p
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'bg-card text-foreground hover:bg-muted'
                                            "
                                            @click="currentPage = p"
                                        >
                                            {{ p }}
                                        </button>
                                    </div>

                                    <button
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-md border bg-card text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="currentPage === totalPages"
                                        @click="currentPage++"
                                    >
                                        <IconChevronRight class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>

            <!-- DIALOG MODAL XEM TRƯỚC LIVE TOÀN MÀN HÌNH & TẢI ẢNH -->
            <Dialog
                :open="isPreviewModalOpen"
                @update:open="isPreviewModalOpen = $event"
            >
                <DialogContent
                    class="max-h-[92vh] max-w-4xl space-y-6 overflow-y-auto p-5 sm:p-7"
                >
                    <DialogHeader class="border-b pb-4">
                        <div
                            class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
                        >
                            <div>
                                <DialogTitle
                                    class="flex items-center gap-2 text-base font-bold text-foreground"
                                >
                                    <IconSparkles
                                        class="size-5 text-amber-500"
                                    />
                                    <span
                                        >Xem Trước Live Chi Tiết & Tải Ảnh Về
                                        Máy</span
                                    >
                                </DialogTitle>
                                <DialogDescription
                                    class="mt-0.5 text-xs text-muted-foreground"
                                >
                                    Bản mô phỏng trực quan theo kích thước chuẩn
                                    kèm công cụ tải ảnh HD.
                                </DialogDescription>
                            </div>

                            <div class="flex items-center gap-2">
                                <!-- Chuyển đổi Thiết bị Mobile / Desktop -->
                                <div
                                    class="flex items-center rounded-xl border bg-muted p-1"
                                >
                                    <button
                                        type="button"
                                        class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1 text-xs font-bold transition"
                                        :class="
                                            previewDeviceMode === 'mobile'
                                                ? 'bg-background text-foreground shadow-2xs'
                                                : 'text-muted-foreground hover:text-foreground'
                                        "
                                        @click="previewDeviceMode = 'mobile'"
                                    >
                                        <IconDeviceMobile class="size-3.5" />
                                        <span>Điện thoại</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1 text-xs font-bold transition"
                                        :class="
                                            previewDeviceMode === 'desktop'
                                                ? 'bg-background text-foreground shadow-2xs'
                                                : 'text-muted-foreground hover:text-foreground'
                                        "
                                        @click="previewDeviceMode = 'desktop'"
                                    >
                                        <IconDeviceDesktop class="size-3.5" />
                                        <span>Máy tính (Feed)</span>
                                    </button>
                                </div>

                                <!-- Tải tất cả ảnh -->
                                <Button
                                    v-if="
                                        selectedSuggestion?.media &&
                                        selectedSuggestion.media.length > 0
                                    "
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-8 cursor-pointer gap-1.5 border-amber-300 text-xs font-bold text-amber-800 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-950/40"
                                    @click="downloadAllSuggestionMedia"
                                >
                                    <IconDownload
                                        class="size-3.5 text-amber-600"
                                    />
                                    <span
                                        >Tải tất cả ({{
                                            selectedSuggestion.media.length
                                        }}
                                        ảnh)</span
                                    >
                                </Button>
                            </div>
                        </div>
                    </DialogHeader>

                    <!-- Khung Mockup Video hoặc Bài viết Social -->
                    <div
                        class="flex flex-col items-center justify-center rounded-2xl border bg-muted/20 p-4 sm:p-6"
                    >
                        <!-- VIDEO PREVIEW LARGE (CapCut Monitor) -->
                        <div
                            v-if="aiContentMode === 'video_ai'"
                            class="flex w-full flex-col items-center space-y-4"
                        >
                            <div
                                class="relative flex flex-col items-center justify-center overflow-hidden rounded-2xl border-4 border-foreground/20 bg-black text-white shadow-2xl transition-all duration-300 select-none"
                                :style="{
                                    width:
                                        form.video_aspect_ratio === '9:16'
                                            ? '330px'
                                            : '100%',
                                    height:
                                        form.video_aspect_ratio === '9:16'
                                            ? '580px'
                                            : '380px',
                                    maxWidth: '100%',
                                }"
                            >
                                <video
                                    v-if="currentPreviewScene?.video_url"
                                    :src="currentPreviewScene.video_url"
                                    autoplay
                                    muted
                                    loop
                                    playsinline
                                    class="absolute inset-0 size-full object-cover"
                                />
                                <img
                                    v-else-if="
                                        currentPreviewScene?.start_image ||
                                        currentPreviewScene?.end_image
                                    "
                                    :src="
                                        currentPreviewScene.start_image ||
                                        currentPreviewScene.end_image
                                    "
                                    class="absolute inset-0 size-full object-cover transition-all duration-1000"
                                    :class="
                                        isPlayingVideo
                                            ? 'scale-105 brightness-105 filter'
                                            : 'scale-100'
                                    "
                                />
                                <div
                                    v-else
                                    class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-br from-amber-950 via-black to-red-950 p-6 text-center"
                                >
                                    <IconMovie
                                        class="mb-2 size-10 animate-pulse text-amber-400"
                                    />
                                    <p class="text-sm font-bold text-amber-200">
                                        Scene {{ activePreviewSceneIndex + 1 }}
                                    </p>
                                    <p
                                        class="mt-1 line-clamp-2 max-w-xs text-xs text-muted-foreground"
                                    >
                                        {{
                                            currentPreviewScene?.context_prompt ||
                                            'Chưa có media'
                                        }}
                                    </p>
                                </div>

                                <!-- Status Header -->
                                <div
                                    class="absolute inset-x-3 top-3 z-20 flex items-center justify-between"
                                >
                                    <div
                                        class="flex items-center gap-2 rounded-full border border-white/10 bg-black/75 px-3 py-1 text-xs font-extrabold text-amber-300 shadow-sm backdrop-blur-md"
                                    >
                                        <span
                                            class="size-2 animate-pulse rounded-full bg-emerald-400"
                                            v-if="isPlayingVideo"
                                        />
                                        <span
                                            >Scene
                                            {{ activePreviewSceneIndex + 1 }}/{{
                                                form.video_scenes.length
                                            }}
                                            ({{
                                                currentPreviewScene?.duration
                                            }}s)</span
                                        >
                                        <span class="opacity-50">·</span>
                                        <span>{{ formattedTimecode }}</span>
                                    </div>

                                    <div
                                        v-if="isPlayingVideo && !isMuted"
                                        class="flex h-4 items-end gap-0.5 rounded-full border border-white/10 bg-black/75 px-2 py-1 backdrop-blur-md"
                                    >
                                        <span
                                            class="h-2.5 w-0.5 animate-bounce rounded-full bg-emerald-400"
                                        />
                                        <span
                                            class="h-3.5 w-0.5 animate-bounce rounded-full bg-emerald-400 delay-75"
                                        />
                                        <span
                                            class="h-2 w-0.5 animate-bounce rounded-full bg-emerald-400 delay-150"
                                        />
                                    </div>
                                </div>

                                <!-- Karaoke-style Subtitles (Phụ đề CapCut) -->
                                <div
                                    v-if="
                                        form.video_auto_subtitles &&
                                        currentPreviewScene?.voiceover_text
                                    "
                                    class="absolute inset-x-4 bottom-16 z-20 flex items-center justify-center gap-2.5 rounded-xl border border-white/15 bg-black/85 p-2.5 text-center shadow-xl backdrop-blur-md"
                                >
                                    <img
                                        v-if="form.character_enabled && form.character_avatar"
                                        :src="form.character_avatar"
                                        :alt="form.character_name || 'Nhân vật AI'"
                                        class="size-7 shrink-0 rounded-full border border-amber-400 object-cover shadow-xs"
                                    />
                                    <div class="min-w-0 text-left">
                                        <p
                                            v-if="form.character_enabled && form.character_name"
                                            class="text-4xs font-bold text-amber-400 uppercase tracking-wider"
                                        >
                                            {{ form.character_name }}
                                        </p>
                                        <p
                                            class="text-sm leading-snug font-black tracking-wide text-amber-300 drop-shadow-md"
                                        >
                                            "{{
                                                currentPreviewScene.voiceover_text
                                            }}"
                                        </p>
                                    </div>
                                </div>

                                <!-- Scene Progress Scrubber Line -->
                                <div
                                    class="absolute inset-x-4 bottom-12 z-20 h-1.5 overflow-hidden rounded-full bg-white/20"
                                >
                                    <div
                                        class="h-full bg-amber-400 transition-all duration-75"
                                        :style="{ width: `${sceneProgress}%` }"
                                    />
                                </div>

                                <!-- Transport Controls -->
                                <div
                                    class="absolute inset-x-3 bottom-3 z-20 flex items-center justify-between rounded-full border border-white/10 bg-black/75 px-4 py-2 shadow-lg backdrop-blur-md"
                                >
                                    <button
                                        type="button"
                                        @click="toggleMute"
                                        class="cursor-pointer text-white/80 transition hover:text-amber-400"
                                        :title="
                                            isMuted
                                                ? 'Bật âm thanh'
                                                : 'Tắt tiếng'
                                        "
                                    >
                                        <IconVolumeOff
                                            v-if="isMuted"
                                            class="size-5 text-rose-400"
                                        />
                                        <IconVolume2 v-else class="size-5" />
                                    </button>

                                    <div class="flex items-center gap-4">
                                        <button
                                            type="button"
                                            @click="
                                                selectPreviewScene(
                                                    Math.max(
                                                        0,
                                                        activePreviewSceneIndex -
                                                            1,
                                                    ),
                                                )
                                            "
                                            class="cursor-pointer text-white transition hover:text-amber-400"
                                            title="Scene trước"
                                        >
                                            <IconChevronLeft class="size-5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoPlay"
                                            class="flex size-9 transform cursor-pointer items-center justify-center rounded-full bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-lg transition hover:from-amber-600 hover:to-amber-700 active:scale-95"
                                            :title="
                                                isPlayingVideo
                                                    ? 'Tạm dừng'
                                                    : 'Phát kịch bản'
                                            "
                                        >
                                            <IconPlayerPause
                                                v-if="isPlayingVideo"
                                                class="size-4"
                                            />
                                            <IconPlayerPlay
                                                v-else
                                                class="ml-0.5 size-4"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            @click="
                                                selectPreviewScene(
                                                    (activePreviewSceneIndex +
                                                        1) %
                                                        form.video_scenes
                                                            .length,
                                                )
                                            "
                                            class="cursor-pointer text-white transition hover:text-amber-400"
                                            title="Scene kế tiếp"
                                        >
                                            <IconChevronRight class="size-5" />
                                        </button>
                                    </div>

                                    <span
                                        class="text-xs font-bold text-amber-300"
                                    >
                                        {{ currentPreviewScene?.duration }}s
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- SOCIAL POST PREVIEW LARGE -->
                        <div
                            v-else
                            :class="
                                previewDeviceMode === 'mobile'
                                    ? 'w-full max-w-[420px]'
                                    : 'w-full max-w-2xl'
                            "
                        >
                            <PlatformPreview
                                v-if="previewPlatform"
                                :platform="previewPlatform"
                                :social-account="previewSocialAccount"
                                :content="
                                    selectedSuggestion?.content ||
                                    form.initial_content ||
                                    ''
                                "
                                :media="selectedSuggestion?.media || []"
                            />
                        </div>
                    </div>

                    <!-- BỘ SƯU TẬP HÌNH ẢNH & TẢI VỀ TRỰC TIẾP -->
                    <div
                        v-if="
                            selectedSuggestion?.media &&
                            selectedSuggestion.media.length > 0
                        "
                        class="space-y-3 border-t pt-4"
                    >
                        <div class="flex items-center justify-between">
                            <h4
                                class="flex items-center gap-1.5 text-xs font-bold text-foreground"
                            >
                                <IconPhoto class="size-4 text-primary" />
                                <span
                                    >Bộ sưu tập Ảnh AI & Media đính kèm ({{
                                        selectedSuggestion.media.length
                                    }}
                                    ảnh)</span
                                >
                            </h4>
                            <button
                                type="button"
                                class="flex cursor-pointer items-center gap-1 text-xs font-bold text-primary hover:underline"
                                @click="downloadAllSuggestionMedia"
                            >
                                <IconDownload class="size-3.5" />
                                <span>Tải toàn bộ bộ ảnh (.JPG)</span>
                            </button>
                        </div>

                        <div
                            class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
                        >
                            <div
                                v-for="(m, idx) in selectedSuggestion.media"
                                :key="m.url"
                                class="group relative overflow-hidden rounded-xl border bg-card shadow-2xs"
                            >
                                <img
                                    :src="m.url"
                                    class="aspect-square w-full object-cover"
                                />
                                <div
                                    class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/60 p-2 text-center opacity-0 transition group-hover:opacity-100"
                                >
                                    <span
                                        class="text-3xs font-bold text-white uppercase"
                                        >Ảnh {{ idx + 1 }}</span
                                    >
                                    <Button
                                        type="button"
                                        size="sm"
                                        class="text-3xs h-7 gap-1 bg-white font-bold text-black shadow-md hover:bg-white/90"
                                        @click="
                                            downloadMedia(
                                                m.url,
                                                `king-coffee-image-${idx + 1}.jpg`,
                                            )
                                        "
                                    >
                                        <IconDownload class="size-3" />
                                        <span>Tải ảnh về</span>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- DIALOG XEM VIDEO PHÂN CẢNH CHI TIẾT -->
            <Dialog
                :open="isSceneVideoModalOpen"
                @update:open="isSceneVideoModalOpen = $event"
            >
                <DialogContent
                    class="max-h-[95vh] max-w-2xl overflow-hidden border-zinc-800 bg-zinc-950 p-0 text-white sm:rounded-2xl"
                >
                    <!-- Header -->
                    <div
                        class="flex items-center justify-between border-b border-zinc-800/80 bg-zinc-900/80 px-5 py-3.5 backdrop-blur"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex size-7 items-center justify-center rounded-lg bg-emerald-500/20 text-xs font-black text-emerald-400"
                            >
                                #{{ (activeSceneVideoModalIndex ?? 0) + 1 }}
                            </span>
                            <div>
                                <DialogTitle
                                    class="flex items-center gap-2 text-sm font-bold text-white"
                                >
                                    <span>Xem Video Phân Cảnh {{
                                        (activeSceneVideoModalIndex ?? 0) + 1
                                    }}</span>
                                </DialogTitle>
                                <DialogDescription
                                    class="mt-0.5 text-3xs text-zinc-400"
                                >
                                    Thời lượng:
                                    {{
                                        activeSceneVideoModalScene?.duration ||
                                        8
                                    }}s • Tỷ lệ:
                                    {{
                                        form.video_aspect_ratio || '16:9'
                                    }}
                                </DialogDescription>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pr-6">
                            <a
                                v-if="activeSceneVideoModalScene?.video_url"
                                :href="activeSceneVideoModalScene.video_url"
                                target="_blank"
                                download="scene-video.mp4"
                                class="flex items-center gap-1 rounded-lg bg-zinc-800 px-2.5 py-1 text-xs font-semibold text-zinc-200 transition hover:bg-zinc-700 hover:text-white"
                                title="Tải video này về máy"
                            >
                                <IconDownload class="size-3.5 text-zinc-300" />
                                <span>Tải video</span>
                            </a>
                        </div>
                    </div>

                    <!-- Video Player Container -->
                    <div
                        class="relative flex min-h-[320px] items-center justify-center bg-black p-3 sm:p-4"
                    >
                        <video
                            v-if="activeSceneVideoModalScene?.video_url"
                            :key="activeSceneVideoModalScene.video_url"
                            :src="activeSceneVideoModalScene.video_url"
                            controls
                            autoplay
                            loop
                            playsinline
                            class="max-h-[62vh] w-auto max-w-full rounded-xl object-contain shadow-2xl"
                            :poster="
                                activeSceneVideoModalScene?.start_image ||
                                activeSceneVideoModalScene?.end_image
                            "
                        />
                        <div
                            v-else
                            class="flex flex-col items-center justify-center p-8 text-zinc-500"
                        >
                            <IconVideo class="mb-2 size-12" />
                            <span>Phân cảnh này chưa có video</span>
                        </div>
                    </div>

                    <!-- Footer Scene Details & Fast Switcher -->
                    <div
                        class="space-y-3 border-t border-zinc-800/80 bg-zinc-900/90 p-4"
                    >
                        <!-- Nhân vật đại diện & Lời thoại phân cảnh -->
                        <div
                            v-if="form.character_enabled && form.character_avatar"
                            class="flex items-start justify-between gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3"
                        >
                            <div class="flex items-center gap-2.5">
                                <img
                                    :src="form.character_avatar"
                                    :alt="form.character_name || 'Nhân vật AI'"
                                    class="size-9 shrink-0 rounded-full border-2 border-amber-400 object-cover shadow-sm"
                                />
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-bold text-amber-300">
                                            {{ form.character_name || 'Nhân vật AI' }}
                                        </span>
                                        <span class="rounded bg-amber-500/20 px-1.5 py-0.2 text-4xs font-bold text-amber-400 uppercase">
                                            Diễn viên AI
                                        </span>
                                    </div>
                                    <p
                                        v-if="activeSceneVideoModalScene?.voiceover_text"
                                        class="mt-0.5 text-xs font-medium italic text-amber-100"
                                    >
                                        "{{ activeSceneVideoModalScene.voiceover_text }}"
                                    </p>
                                    <p v-else class="mt-0.5 text-3xs text-amber-300/70">
                                        (Phân cảnh không có lời thoại)
                                    </p>
                                </div>
                            </div>
                            <button
                                v-if="activeSceneVideoModalIndex !== null"
                                type="button"
                                class="text-3xs flex shrink-0 cursor-pointer items-center gap-1 rounded-lg border border-amber-400/40 bg-amber-400/20 px-2 py-1 font-bold text-amber-200 transition hover:bg-amber-400/30"
                                title="Gán ảnh nhân vật này làm ảnh phân cảnh"
                                @click="useCharacterAvatarAsSceneImage(activeSceneVideoModalIndex)"
                            >
                                <IconSparkles class="size-3 text-amber-300" />
                                <span>Gán ảnh NV</span>
                            </button>
                        </div>
                        <div
                            v-else-if="activeSceneVideoModalScene?.voiceover_text"
                            class="rounded-xl border border-white/10 bg-black/40 p-2.5"
                        >
                            <span class="text-4xs font-bold text-zinc-400 uppercase">Thoại video:</span>
                            <p class="text-xs font-medium italic text-amber-300">
                                "{{ activeSceneVideoModalScene.voiceover_text }}"
                            </p>
                        </div>

                        <div class="space-y-1">
                            <div
                                class="flex items-center gap-1.5 text-4xs font-bold tracking-wider text-zinc-400 uppercase"
                            >
                                <IconSparkles class="size-3 text-amber-400" />
                                <span>Hành động & Góc máy quay</span>
                            </div>
                            <p
                                class="text-xs font-medium leading-relaxed text-zinc-200"
                            >
                                {{
                                    activeSceneVideoModalScene?.action_prompt ||
                                    activeSceneVideoModalScene?.context_prompt ||
                                    'Không có mô tả'
                                }}
                            </p>
                        </div>

                        <!-- Scene Switcher in Modal -->
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-800/80 pt-2"
                        >
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button
                                    type="button"
                                    v-for="(
                                        sc, sIdx
                                    ) in form.video_scenes"
                                    :key="sIdx"
                                    @click="
                                        activeSceneVideoModalIndex = sIdx
                                    "
                                    class="rounded px-2 py-0.5 text-3xs font-bold transition"
                                    :class="
                                        activeSceneVideoModalIndex === sIdx
                                            ? 'bg-primary text-primary-foreground shadow'
                                            : sc.video_url
                                              ? 'bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30'
                                              : 'bg-zinc-800 text-zinc-500 hover:bg-zinc-700'
                                    "
                                >
                                    Cảnh {{ sIdx + 1 }}
                                    {{ sc.video_url ? '🎬' : '' }}
                                </button>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-lg bg-zinc-800 px-3 py-1 text-xs font-semibold text-zinc-300 transition hover:bg-zinc-700 hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="
                                        (activeSceneVideoModalIndex ?? 0) <= 0
                                    "
                                    @click="
                                        activeSceneVideoModalIndex = Math.max(
                                            0,
                                            (activeSceneVideoModalIndex ??
                                                0) - 1,
                                        )
                                    "
                                >
                                    ← Cảnh trước
                                </button>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-lg bg-zinc-800 px-3 py-1 text-xs font-semibold text-zinc-300 transition hover:bg-zinc-700 hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="
                                        (activeSceneVideoModalIndex ?? 0) >=
                                        form.video_scenes.length - 1
                                    "
                                    @click="
                                        activeSceneVideoModalIndex = Math.min(
                                            form.video_scenes.length - 1,
                                            (activeSceneVideoModalIndex ??
                                                0) + 1,
                                        )
                                    "
                                >
                                    Cảnh sau →
                                </button>
                            </div>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Media Library Picker Dialog -->
            <MediaPickerDialog
                ref="mediaPickerDialog"
                @select="handlePickedMedia"
            />
        </div>
    </AppLayout>
</template>
