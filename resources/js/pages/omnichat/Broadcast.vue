<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    IconAlertCircle,
    IconCalendarTime,
    IconCheck,
    IconChecks,
    IconClock,
    IconFilter,
    IconHistory,
    IconMessageShare,
    IconPhone,
    IconPhoto,
    IconPlus,
    IconRotateClockwise,
    IconSend,
    IconSparkles,
    IconUpload,
    IconUser,
    IconUserCheck,
    IconUserExclamation,
    IconUsers,
} from '@tabler/icons-vue';
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

interface SegmentItem {
    id: string;
    name: string;
    type: string;
    description: string | null;
}

interface CampaignItem {
    id: string;
    name: string;
    trigger_type: string;
    trigger_inactive_days: number | null;
    message_template: string;
    image_url: string | null;
    status: string;
    stats: {
        total_targeted?: number;
        total_sent?: number;
        failed?: number;
    } | null;
    messages_count: number;
    sent_count: number;
    created_at: string;
    scheduled_at?: string | null;
    repeat_daily?: boolean;
    started_at: string | null;
    completed_at: string | null;
}

interface SentMessageItem {
    id: string;
    broadcast_campaign_id: string;
    contact_id: string;
    sent_body: string;
    image_url: string | null;
    status: string;
    sent_at: string | null;
    delivered_at: string | null;
    contact?: {
        id: string;
        name: string | null;
        display_name: string;
        phone: string | null;
        avatar_url: string | null;
    } | null;
    campaign?: {
        id: string;
        name: string;
        trigger_type: string;
    } | null;
}

const props = defineProps<{
    campaigns: {
        data: CampaignItem[];
        total: number;
    };
    sentMessages?: {
        data: SentMessageItem[];
        total: number;
    };
    segments: SegmentItem[];
    stats: {
        total_contacts: number;
        phone_contacts: number;
        inactive_30d: number;
        inactive_40d: number;
        total_messages_sent?: number;
    };
    workspaceId: string;
}>();

// Active Right Tab: 'campaigns' | 'sent_messages'
const activeRightTab = ref<'campaigns' | 'sent_messages'>('campaigns');

// Form State
const campaignName = ref('Chiến dịch kích hoạt lại khách hàng');
const triggerType = ref<'all' | 'inactive_30d' | 'inactive_40d' | 'has_phone' | 'segment'>('all');
const selectedSegmentId = ref<string>('');
const messageTemplate = ref(
    'Dạ em chào {name}! Shop King Coffee đang có chương trình tri ân ưu đãi giảm 20% cho đơn hàng tiếp theo và miễn phí giao hàng toàn quốc. Anh/chị có cần em gửi bảng menu ưu đãi hôm nay không ạ?'
);
const aiSpinEnabled = ref(true);
const delaySeconds = ref(3);

// Image Attachment
const attachedImage = ref<File | null>(null);
const imagePreviewUrl = ref<string | null>(null);

const handleImageChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files?.[0]) {
        attachedImage.value = target.files[0];
        imagePreviewUrl.value = URL.createObjectURL(target.files[0]);
    }
};

const removeAttachedImage = () => {
    attachedImage.value = null;
    imagePreviewUrl.value = null;
};

const isPreviewing = ref(false);
const previewCount = ref<number>(0);
const previewSamples = ref<Array<{ name: string; phone: string | null; last_seen_at: string | null }>>([]);
const isSending = ref(false);
const isGeneratingSamples = ref(false);

const loadPreview = async () => {
    isPreviewing.value = true;
    try {
        const { data } = await axios.post('/omnichat/broadcast/preview', {
            trigger_type: triggerType.value,
            customer_segment_id: triggerType.value === 'segment' ? selectedSegmentId.value : undefined,
        });
        previewCount.value = data.count;
        previewSamples.value = data.samples;
    } catch {
        toast.error('Lỗi khi tính số lượng khách hàng mục tiêu');
    } finally {
        isPreviewing.value = false;
    }
};

const generateMockData = async () => {
    isGeneratingSamples.value = true;
    try {
        const { data } = await axios.post('/omnichat/broadcast/sample-contacts');
        toast.success(`Đã thêm thành công ${data.created} khách hàng mẫu vào hệ thống!`);
        router.reload({ only: ['stats', 'campaigns'] });
        await loadPreview();
    } catch {
        toast.error('Lỗi khi tạo dữ liệu mẫu');
    } finally {
        isGeneratingSamples.value = false;
    }
};

watch([triggerType, selectedSegmentId], () => {
    loadPreview();
});

// Timing & Scheduling
const timingMode = ref<'now' | 'schedule'>('now');
const scheduledAt = ref<string>('');
const repeatDaily = ref(false);

// Kịch bản set time mẫu
interface ScenarioPreset {
    id: string;
    title: string;
    badge: string;
    badgeColor: string;
    timeHint: string;
    trigger: 'all' | 'inactive_30d' | 'inactive_40d' | 'has_phone';
    defaultTimeOffsetHours: number;
    name: string;
    template: string;
}

const scenarioPresets: ScenarioPreset[] = [
    {
        id: 'weekend_sale',
        title: 'Flash Sale Cuối Tuần (T6 - CN)',
        badge: 'Cuối tuần',
        badgeColor: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
        timeHint: 'Thứ 6 lúc 19:30',
        trigger: 'has_phone',
        defaultTimeOffsetHours: 24,
        name: 'Flash Sale Cuối tuần - Giảm 25% Freeship',
        template: 'Chào {name} ơi! Cuối tuần này King Coffee mở tiệc Flash Sale: Giảm 25% toàn bộ menu và Freeship cho đơn từ 2 ly. Ưu đãi áp dụng tới hết Chủ nhật tuần này. Anh/chị có muốn đặt ly nước tươi mát giải nhiệt không ạ? ☕✨',
    },
    {
        id: 'salary_day',
        title: 'Lương Về / Ngày Đôi Siêu Sale',
        badge: 'Đầu tháng & Giữa tháng',
        badgeColor: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
        timeHint: 'Ngày 05 hoặc 15 lúc 11:30 trưa',
        trigger: 'all',
        defaultTimeOffsetHours: 48,
        name: 'Tri ân Ngày Lương Về - Tặng Voucher 50k',
        template: 'Ting ting! Lương về rộn ràng cùng King Coffee nha {name}! Chúng em gửi tặng riêng {name} mã VOUCHER50K giảm trực tiếp 50,000đ cho đơn hàng hôm nay. Anh/chị reply "MENU" để em gửi chọn món ngay nha! 🎁',
    },
    {
        id: 'inactive_30d_reactivate',
        title: 'Kích Hoạt Lại Khách 30 Ngày',
        badge: 'Re-engage 30d',
        badgeColor: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        timeHint: 'Tối 20:00 (Khung giờ vàng đọc tin)',
        trigger: 'inactive_30d',
        defaultTimeOffsetHours: 12,
        name: 'Re-engage Khách hàng 30 ngày ngưng liên hệ',
        template: 'Dạ King Coffee chào {name} ạ! Lâu rồi chưa thấy anh/chị ghé shop dùng thử các món mới. Tuần này shop có món Signature mới cực ngon, em xin phép gửi tặng {name} mã giảm 20% khi ghé lại nhé. Em có thể gửi hình ảnh menu cho anh/chị xem trước không ạ? 😊',
    },
    {
        id: 'inactive_40d_retention',
        title: 'Cứu Vãn Khách Hàng > 40 Ngày',
        badge: 'Giữ chân 40d',
        badgeColor: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
        timeHint: 'Sáng 09:00 hoặc Chiều 15:30',
        trigger: 'inactive_40d',
        defaultTimeOffsetHours: 36,
        name: 'Chiến dịch tri ân giữ chân khách hàng cũ > 40 ngày',
        template: 'Chào {name}, tụi em nhớ {name} ghê! Em xin phép gửi riêng mã TRIANVIP giảm tới 30% và miễn phí giao tận nhà. Nếu có điều gì shop phục vụ chưa tốt trước đây làm {name} chưa hài lòng, hãy cho shop biết để khắc phục nhé! Cảm ơn {name} nhiều ạ! ❤️',
    },
];

const applyScenario = (preset: ScenarioPreset) => {
    campaignName.value = preset.name;
    triggerType.value = preset.trigger;
    messageTemplate.value = preset.template;
    timingMode.value = 'schedule';

    // Tính thời gian mặc định theo offset
    const targetDate = new Date();
    targetDate.setHours(targetDate.getHours() + preset.defaultTimeOffsetHours);
    targetDate.setMinutes(0, 0, 0);

    const year = targetDate.getFullYear();
    const month = String(targetDate.getMonth() + 1).padStart(2, '0');
    const day = String(targetDate.getDate()).padStart(2, '0');
    const hours = String(targetDate.getHours()).padStart(2, '0');
    const minutes = String(targetDate.getMinutes()).padStart(2, '0');

    scheduledAt.value = `${year}-${month}-${day}T${hours}:${minutes}`;

    toast.success(`Đã chọn kịch bản: "${preset.title}" và thiết lập lịch gửi!`);
};

onMounted(() => {
    loadPreview();
});

const submitCampaign = async () => {
    const shouldRepeatDaily = timingMode.value === 'schedule' && repeatDaily.value;

    if (!campaignName.value.trim() || !messageTemplate.value.trim()) {
        toast.error('Vui lòng nhập tên chiến dịch và nội dung tin nhắn');
        return;
    }

    if (previewCount.value === 0) {
        toast.error('Nhóm khách hàng này hiện có 0 người. Bạn hãy bấm "Tạo 7 khách hàng mẫu" ở góc phải để thử nghiệm!');
        return;
    }

    if (timingMode.value === 'schedule' && !scheduledAt.value) {
        toast.error('Vui lòng chọn ngày và giờ muốn lên lịch gửi tin!');
        return;
    }

    const actionText = timingMode.value === 'schedule' 
        ? `lên lịch gửi vào lúc ${new Date(scheduledAt.value).toLocaleString('vi-VN')}`
        : 'gửi tin nhắn ngay lập tức';

    if (!confirm(`Bạn có chắc chắn muốn ${actionText} ${attachedImage.value ? '(kèm hình ảnh)' : ''} đến ${previewCount.value} khách hàng?`)) {
        return;
    }

    isSending.value = true;
    try {
        const formData = new FormData();
        formData.append('name', campaignName.value);
        formData.append('trigger_type', triggerType.value);
        if (triggerType.value === 'segment' && selectedSegmentId.value) {
            formData.append('customer_segment_id', selectedSegmentId.value);
        }
        formData.append('message_template', messageTemplate.value);
        formData.append('ai_spin_enabled', aiSpinEnabled.value ? '1' : '0');
        formData.append('delay_seconds', String(delaySeconds.value));
        if (timingMode.value === 'schedule' && scheduledAt.value) {
            formData.append('scheduled_at', scheduledAt.value);
        }
        formData.append('repeat_daily', shouldRepeatDaily ? '1' : '0');
        if (attachedImage.value) {
            formData.append('image', attachedImage.value);
        }

        const { data } = await axios.post('/omnichat/broadcast', formData);

        if (data.is_scheduled) {
            toast.success(shouldRepeatDaily
                ? 'Đã lên lịch gửi tự động mỗi ngày vào giờ đã chọn.'
                : 'Đã lên lịch thành công! Hệ thống sẽ tự động chạy vào giờ đã chọn.');
        } else {
            toast.success(`Đã khởi tạo và gửi thành công tới ${data.sent_count} khách hàng!`);
        }

        router.reload({ only: ['campaigns', 'sentMessages', 'stats'] });
        campaignName.value = 'Chiến dịch mới - ' + new Date().toLocaleTimeString('vi-VN');
        repeatDaily.value = false;
        removeAttachedImage();
    } catch {
        toast.error('Lỗi khi gửi chiến dịch');
    } finally {
        isSending.value = false;
    }
};

// Checklist Selection & Retry State for Sent Messages Table
const selectedMessageIds = ref<string[]>([]);
const isRetrying = ref(false);
const messageFilterStatus = ref<'all' | 'sent' | 'failed'>('all');

const toggleSelectAll = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.checked) {
        selectedMessageIds.value = (props.sentMessages?.data || []).map((m) => m.id);
    } else {
        selectedMessageIds.value = [];
    }
};

const toggleSelectOne = (id: string) => {
    if (selectedMessageIds.value.includes(id)) {
        selectedMessageIds.value = selectedMessageIds.value.filter((item) => item !== id);
    } else {
        selectedMessageIds.value.push(id);
    }
};

const retrySelectedMessages = async () => {
    if (selectedMessageIds.value.length === 0) {
        toast.error('Vui lòng tích chọn ít nhất 1 khách hàng / tin nhắn để gửi lại!');
        return;
    }

    if (!confirm(`Bạn có chắc chắn muốn gửi lại (Retry) ${selectedMessageIds.value.length} tin nhắn đã chọn?`)) {
        return;
    }

    isRetrying.value = true;
    try {
        const { data } = await axios.post('/omnichat/broadcast/retry', {
            message_ids: selectedMessageIds.value,
        });

        toast.success(data.message || `Đã thử lại thành công ${data.retried_count} tin nhắn!`);
        selectedMessageIds.value = [];
        router.reload({ only: ['sentMessages', 'campaigns', 'stats'] });
    } catch {
        toast.error('Có lỗi xảy ra khi thực hiện gửi lại (Retry)');
    } finally {
        isRetrying.value = false;
    }
};

const retrySingleMessage = async (id: string) => {
    isRetrying.value = true;
    try {
        const { data } = await axios.post('/omnichat/broadcast/retry', {
            message_ids: [id],
        });

        toast.success(data.message || 'Đã gửi lại thành công tin nhắn này!');
        router.reload({ only: ['sentMessages', 'campaigns', 'stats'] });
    } catch {
        toast.error('Có lỗi xảy ra khi gửi lại tin nhắn');
    } finally {
        isRetrying.value = false;
    }
};

const insertVariable = (variable: string) => {
    messageTemplate.value += ` {${variable}}`;
};

const formatDate = (dateStr: string | null) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('vi-VN');
};
</script>

<template>
    <AppLayout>
        <Head title="Gửi tin nhắn khách hàng loạt (Broadcast)" />

        <div class="px-4 py-6 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-border pb-5">
                <div>
                    <div class="flex items-center gap-2">
                        <IconMessageShare class="size-6 text-indigo-500" />
                        <h1 class="text-2xl font-bold tracking-tight text-foreground">
                            Gửi tin nhắn khách hàng loạt (Broadcast & Re-engagement)
                        </h1>
                    </div>
                    <p class="text-sm text-muted-foreground mt-1">
                        Gửi tin nhắn hàng loạt kèm hình ảnh, tự động lọc đối tượng (Tất cả, Inactive 30/40 ngày, Có SĐT).
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        v-if="props.stats.total_contacts === 0"
                        variant="outline"
                        size="sm"
                        :disabled="isGeneratingSamples"
                        class="gap-1.5 text-indigo-600 border-indigo-200 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950 dark:border-indigo-800"
                        @click="generateMockData"
                    >
                        <IconUserCheck class="size-4" />
                        {{ isGeneratingSamples ? 'Đang tạo...' : '+ Tạo 7 khách hàng mẫu để test' }}
                    </Button>
                </div>
            </div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="bg-card border border-border p-4 rounded-xl shadow-2xs space-y-1">
                    <div class="flex items-center justify-between text-muted-foreground">
                        <span class="text-xs font-medium">Tổng khách hàng</span>
                        <IconUsers class="size-4" />
                    </div>
                    <div class="text-2xl font-bold text-foreground">{{ props.stats.total_contacts }}</div>
                </div>

                <div class="bg-card border border-border p-4 rounded-xl shadow-2xs space-y-1">
                    <div class="flex items-center justify-between text-emerald-600">
                        <span class="text-xs font-medium">Đã có Số điện thoại</span>
                        <IconPhone class="size-4" />
                    </div>
                    <div class="text-2xl font-bold text-foreground">{{ props.stats.phone_contacts }}</div>
                </div>

                <div class="bg-card border border-border p-4 rounded-xl shadow-2xs space-y-1">
                    <div class="flex items-center justify-between text-amber-600">
                        <span class="text-xs font-medium">Chưa tương tác > 30 ngày</span>
                        <IconClock class="size-4" />
                    </div>
                    <div class="text-2xl font-bold text-foreground">{{ props.stats.inactive_30d }}</div>
                </div>

                <div class="bg-card border border-border p-4 rounded-xl shadow-2xs space-y-1">
                    <div class="flex items-center justify-between text-rose-600">
                        <span class="text-xs font-medium">Nguy cơ rời bỏ > 40 ngày</span>
                        <IconUserExclamation class="size-4" />
                    </div>
                    <div class="text-2xl font-bold text-foreground">{{ props.stats.inactive_40d }}</div>
                </div>

                <div class="bg-card border border-border p-4 rounded-xl shadow-2xs space-y-1 col-span-2 md:col-span-1">
                    <div class="flex items-center justify-between text-indigo-600">
                        <span class="text-xs font-medium">Tin nhắn đã gửi</span>
                        <IconChecks class="size-4" />
                    </div>
                    <div class="text-2xl font-bold text-foreground">{{ props.stats.total_messages_sent ?? 0 }}</div>
                </div>
            </div>

            <!-- Empty data notice with quick seed button -->
            <div
                v-if="props.stats.total_contacts === 0"
                class="p-4 rounded-xl bg-amber-50 border border-amber-200 dark:bg-amber-950/30 dark:border-amber-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-sm text-amber-800 dark:text-amber-200"
            >
                <div class="flex items-center gap-2">
                    <IconAlertCircle class="size-5 shrink-0 text-amber-600" />
                    <span>Hệ thống chưa có khách hàng nào trong mục Omnichat. Hãy bấm nút bên cạnh để nạp danh sách khách hàng mẫu để thử nghiệm tính năng gửi tin!</span>
                </div>
                <Button
                    size="sm"
                    class="bg-amber-600 hover:bg-amber-700 text-white shrink-0"
                    :disabled="isGeneratingSamples"
                    @click="generateMockData"
                >
                    {{ isGeneratingSamples ? 'Đang nạp...' : 'Tạo khách hàng mẫu ngay' }}
                </Button>
            </div>

            <!-- Kịch bản mẫu Set Time / Hẹn giờ gửi (Scenario Presets) -->
            <div class="bg-card border border-border rounded-xl p-5 shadow-xs space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground flex items-center gap-2">
                            <IconCalendarTime class="size-4 text-indigo-500" />
                            Kịch bản mẫu theo thời gian (Set Time Scenarios)
                        </h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Bấm chọn kịch bản bên dưới để tự động điền nội dung, lọc đối tượng và hẹn giờ vàng phát tin:
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 pt-1">
                    <div
                        v-for="preset in scenarioPresets"
                        :key="preset.id"
                        class="p-3 rounded-lg border border-border hover:border-indigo-500 bg-muted/20 hover:bg-muted/40 transition-all cursor-pointer flex flex-col justify-between gap-2.5 group"
                        @click="applyScenario(preset)"
                    >
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span :class="['text-[10px] font-semibold px-2 py-0.5 rounded-full', preset.badgeColor]">
                                    {{ preset.badge }}
                                </span>
                                <span class="text-[11px] font-mono text-muted-foreground flex items-center gap-1">
                                    <IconClock class="size-3" /> {{ preset.timeHint }}
                                </span>
                            </div>
                            <h3 class="font-medium text-xs text-foreground group-hover:text-indigo-600 transition-colors">
                                {{ preset.title }}
                            </h3>
                            <p class="text-[11px] text-muted-foreground line-clamp-2 leading-relaxed">
                                "{{ preset.template }}"
                            </p>
                        </div>

                        <div class="pt-2 border-t border-border/60 flex items-center justify-between text-[11px]">
                            <span class="text-xs text-indigo-600 dark:text-indigo-400 font-medium group-hover:underline">
                                Áp dụng kịch bản →
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Workspace: Form & Campaigns History -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left: Create Broadcast Campaign -->
                <div class="lg:col-span-6 bg-card border border-border rounded-xl p-5 shadow-xs space-y-5">
                    <HeadingSmall
                        title="Tạo chiến dịch gửi tin nhắn"
                        description="Soạn thảo kịch bản nhắn tin tự động cá nhân hóa theo tên và số điện thoại kèm hình ảnh ưu đãi."
                    />

                    <div class="space-y-4">
                        <!-- Chọn Thời gian gửi: Gửi ngay vs Lên lịch (Set time) -->
                        <div class="p-3 bg-indigo-50/40 border border-indigo-100 dark:bg-indigo-950/20 dark:border-indigo-900/50 rounded-lg space-y-2">
                            <Label class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                <IconCalendarTime class="size-4 text-indigo-600" />
                                <span>Thời gian gửi (Set time)</span>
                            </Label>
                            
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    :class="[
                                        'py-2 px-3 rounded-md text-xs font-medium border text-center transition-all flex items-center justify-center gap-1.5',
                                        timingMode === 'now'
                                            ? 'border-indigo-600 bg-white text-indigo-700 shadow-2xs dark:bg-slate-900 dark:text-indigo-300'
                                            : 'border-transparent text-muted-foreground hover:bg-muted/50',
                                    ]"
                                    @click="timingMode = 'now'"
                                >
                                    <IconSend class="size-3.5" /> Gửi ngay lập tức
                                </button>
                                
                                <button
                                    type="button"
                                    :class="[
                                        'py-2 px-3 rounded-md text-xs font-medium border text-center transition-all flex items-center justify-center gap-1.5',
                                        timingMode === 'schedule'
                                            ? 'border-indigo-600 bg-white text-indigo-700 shadow-2xs dark:bg-slate-900 dark:text-indigo-300'
                                            : 'border-transparent text-muted-foreground hover:bg-muted/50',
                                    ]"
                                    @click="timingMode = 'schedule'"
                                >
                                    <IconClock class="size-3.5" /> Hẹn giờ gửi (Set time)
                                </button>
                            </div>

                            <!-- Input ngày giờ nếu chọn schedule -->
                            <div v-if="timingMode === 'schedule'" class="pt-1.5 space-y-1">
                                <Label for="schedule-time" class="text-[11px] text-muted-foreground">Chọn ngày và giờ chạy chiến dịch:</Label>
                                <input
                                    id="schedule-time"
                                    v-model="scheduledAt"
                                    type="datetime-local"
                                    class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
                                />
                                <label class="flex items-center gap-2 text-xs text-foreground">
                                    <input
                                        v-model="repeatDaily"
                                        type="checkbox"
                                        class="rounded border-input text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span>Lặp lại và tự động gửi mỗi ngày</span>
                                </label>
                                <p class="text-[10px] text-muted-foreground">
                                    Hệ thống tự chạy chiến dịch đến giờ đã hẹn. Nếu bật lặp, chiến dịch sẽ chạy lại mỗi ngày.
                                </p>
                            </div>
                        </div>

                        <!-- Tên chiến dịch -->
                        <div>
                            <Label for="campaign-name">Tên chiến dịch</Label>
                            <Input
                                id="campaign-name"
                                v-model="campaignName"
                                placeholder="VD: Re-engage khách hàng tháng này"
                                class="mt-1"
                            />
                        </div>

                        <!-- Chọn đối tượng khách hàng mục tiêu -->
                        <div>
                            <Label class="flex items-center justify-between">
                                <span>Nhóm khách hàng mục tiêu</span>
                                <span class="text-xs font-semibold text-indigo-600">
                                    {{ isPreviewing ? 'Đang tính...' : `Mục tiêu: ${previewCount} khách` }}
                                </span>
                            </Label>

                            <div class="grid grid-cols-2 gap-2 mt-1.5">
                                <button
                                    type="button"
                                    :class="[
                                        'p-2.5 rounded-lg border text-left text-xs font-medium transition-all flex items-center justify-between col-span-2',
                                        triggerType === 'all'
                                            ? 'border-indigo-600 bg-indigo-50/50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300'
                                            : 'border-border text-muted-foreground hover:bg-muted/40',
                                    ]"
                                    @click="triggerType = 'all'"
                                >
                                    <span>👥 Gửi toàn bộ khách hàng (Tất cả)</span>
                                    <IconCheck v-if="triggerType === 'all'" class="size-4 text-indigo-600" />
                                </button>

                                <button
                                    type="button"
                                    :class="[
                                        'p-2.5 rounded-lg border text-left text-xs font-medium transition-all flex items-center justify-between',
                                        triggerType === 'inactive_30d'
                                            ? 'border-indigo-600 bg-indigo-50/50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300'
                                            : 'border-border text-muted-foreground hover:bg-muted/40',
                                    ]"
                                    @click="triggerType = 'inactive_30d'"
                                >
                                    <span>⏳ Inactive > 30 ngày</span>
                                    <IconCheck v-if="triggerType === 'inactive_30d'" class="size-4 text-indigo-600" />
                                </button>

                                <button
                                    type="button"
                                    :class="[
                                        'p-2.5 rounded-lg border text-left text-xs font-medium transition-all flex items-center justify-between',
                                        triggerType === 'inactive_40d'
                                            ? 'border-indigo-600 bg-indigo-50/50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300'
                                            : 'border-border text-muted-foreground hover:bg-muted/40',
                                    ]"
                                    @click="triggerType = 'inactive_40d'"
                                >
                                    <span>⚠️ Inactive > 40 ngày</span>
                                    <IconCheck v-if="triggerType === 'inactive_40d'" class="size-4 text-indigo-600" />
                                </button>

                                <button
                                    type="button"
                                    :class="[
                                        'p-2.5 rounded-lg border text-left text-xs font-medium transition-all flex items-center justify-between',
                                        triggerType === 'has_phone'
                                            ? 'border-indigo-600 bg-indigo-50/50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300'
                                            : 'border-border text-muted-foreground hover:bg-muted/40',
                                    ]"
                                    @click="triggerType = 'has_phone'"
                                >
                                    <span>📞 Có Số điện thoại</span>
                                    <IconCheck v-if="triggerType === 'has_phone'" class="size-4 text-indigo-600" />
                                </button>

                                <button
                                    type="button"
                                    :class="[
                                        'p-2.5 rounded-lg border text-left text-xs font-medium transition-all flex items-center justify-between',
                                        triggerType === 'segment'
                                            ? 'border-indigo-600 bg-indigo-50/50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300'
                                            : 'border-border text-muted-foreground hover:bg-muted/40',
                                    ]"
                                    @click="triggerType = 'segment'"
                                >
                                    <span>🎯 Phân khúc tùy chọn</span>
                                    <IconCheck v-if="triggerType === 'segment'" class="size-4 text-indigo-600" />
                                </button>
                            </div>

                            <!-- Selector nếu chọn Phân khúc tùy chọn -->
                            <div v-if="triggerType === 'segment'" class="mt-2">
                                <select
                                    v-model="selectedSegmentId"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs"
                                >
                                    <option value="">-- Chọn phân khúc --</option>
                                    <option v-for="seg in props.segments" :key="seg.id" :value="seg.id">
                                        {{ seg.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- ĐÍNH KÈM HÌNH ẢNH / BANNER ƯU ĐÃI -->
                        <div>
                            <Label class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <IconPhoto class="size-4 text-indigo-600" />
                                    <span>Đính kèm hình ảnh gửi hàng loạt (Banner/Voucher)</span>
                                </span>
                                <span v-if="attachedImage" class="text-xs text-red-500 cursor-pointer hover:underline" @click="removeAttachedImage">
                                    Xóa ảnh
                                </span>
                            </Label>

                            <div v-if="!imagePreviewUrl" class="mt-1.5">
                                <label class="border-2 border-dashed border-border hover:border-indigo-500 rounded-lg p-3 flex items-center justify-center gap-2 cursor-pointer transition-colors bg-muted/10">
                                    <IconUpload class="size-4 text-muted-foreground" />
                                    <span class="text-xs text-muted-foreground font-medium">Tải lên hình ảnh (PNG, JPG, WEBP)</span>
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp,image/gif"
                                        class="hidden"
                                        @change="handleImageChange"
                                    />
                                </label>
                            </div>

                            <div v-else class="mt-2 relative rounded-lg border overflow-hidden max-w-[200px] shadow-xs">
                                <img :src="imagePreviewUrl" alt="Preview" class="w-full h-28 object-cover" />
                                <div class="p-1 bg-background/90 text-[10px] text-center font-mono truncate">
                                    {{ attachedImage?.name }}
                                </div>
                            </div>
                        </div>

                        <!-- Khung soạn thảo tin nhắn -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <Label for="message-template">Nội dung tin nhắn</Label>
                                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <span>Chèn biến:</span>
                                    <button
                                        type="button"
                                        class="px-1.5 py-0.5 bg-muted rounded hover:bg-muted/80 text-[11px] font-mono"
                                        @click="insertVariable('name')"
                                    >
                                        {name}
                                    </button>
                                    <button
                                        type="button"
                                        class="px-1.5 py-0.5 bg-muted rounded hover:bg-muted/80 text-[11px] font-mono"
                                        @click="insertVariable('phone')"
                                    >
                                        {phone}
                                    </button>
                                </div>
                            </div>

                            <textarea
                                id="message-template"
                                v-model="messageTemplate"
                                rows="4"
                                placeholder="Nhập nội dung tin nhắn gửi khách hàng..."
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 leading-relaxed"
                            ></textarea>
                        </div>

                        <!-- Tùy chọn an toàn chống spam -->
                        <div class="grid grid-cols-2 gap-4 p-3 bg-muted/20 border border-border rounded-lg text-xs">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="aiSpinEnabled"
                                    type="checkbox"
                                    class="rounded border-input text-indigo-600 focus:ring-indigo-500"
                                />
                                <span>AI Spin Text (Đổi từ đồng nghĩa)</span>
                            </label>

                            <div class="flex items-center gap-2">
                                <span class="text-muted-foreground">Giãn cách:</span>
                                <select
                                    v-model="delaySeconds"
                                    class="rounded border border-input bg-background px-2 py-1 text-xs"
                                >
                                    <option :value="2">2 giây/tin</option>
                                    <option :value="3">3 giây/tin (Khuyên dùng)</option>
                                    <option :value="5">5 giây/tin</option>
                                </select>
                            </div>
                        </div>

                        <Button
                            :disabled="isSending || previewCount === 0"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white flex items-center justify-center gap-2 py-2.5"
                            @click="submitCampaign"
                        >
                            <IconClock v-if="timingMode === 'schedule'" class="size-4" />
                            <IconSend v-else class="size-4" />
                            {{ 
                                isSending 
                                    ? 'Đang xử lý...' 
                                    : timingMode === 'schedule'
                                        ? `Lên lịch gửi cho ${previewCount} khách hàng`
                                        : `Gửi ngay cho ${previewCount} khách hàng`
                            }}
                        </Button>
                    </div>
                </div>

                <!-- Right: Campaigns List / Sent Messages Tabs -->
                <div class="lg:col-span-6 bg-card border border-border rounded-xl p-5 shadow-xs space-y-4">
                    <!-- Tab Switcher Header -->
                    <div class="flex items-center justify-between border-b border-border pb-3">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5',
                                    activeRightTab === 'campaigns'
                                        ? 'bg-indigo-600 text-white shadow-2xs'
                                        : 'text-muted-foreground hover:text-foreground hover:bg-muted/50',
                                ]"
                                @click="activeRightTab = 'campaigns'"
                            >
                                <IconHistory class="size-3.5" />
                                <span>Chiến dịch ({{ props.campaigns.total ?? props.campaigns.data.length }})</span>
                            </button>

                            <button
                                type="button"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5',
                                    activeRightTab === 'sent_messages'
                                        ? 'bg-indigo-600 text-white shadow-2xs'
                                        : 'text-muted-foreground hover:text-foreground hover:bg-muted/50',
                                ]"
                                @click="activeRightTab = 'sent_messages'"
                            >
                                <IconChecks class="size-3.5" />
                                <span>Tin nhắn gửi thành công ({{ props.sentMessages?.total ?? props.sentMessages?.data?.length ?? 0 }})</span>
                            </button>
                        </div>
                    </div>

                    <!-- Tab 1: Campaigns List -->
                    <div v-if="activeRightTab === 'campaigns'" class="divide-y divide-border overflow-y-auto max-h-[580px]">
                        <div
                            v-for="camp in props.campaigns.data"
                            :key="camp.id"
                            class="py-3.5 space-y-2"
                        >
                            <div class="flex items-center justify-between">
                                <div class="font-medium text-sm text-foreground flex items-center gap-2">
                                    <span>{{ camp.name }}</span>
                                    <Badge
                                        :variant="camp.status === 'completed' ? 'default' : camp.status === 'scheduled' ? 'outline' : 'secondary'"
                                        :class="[
                                            'text-[10px] py-0 px-1.5',
                                            camp.status === 'scheduled' ? 'border-amber-500 text-amber-600 bg-amber-50 dark:bg-amber-950/40' : ''
                                        ]"
                                    >
                                        {{ 
                                            camp.status === 'completed' 
                                                ? '✓ Đã hoàn thành' 
                                                : camp.status === 'scheduled' 
                                                    ? '🕒 Đã lên lịch' 
                                                    : 'Đang gửi' 
                                        }}
                                    </Badge>
                                </div>
                                <span class="text-xs text-muted-foreground flex items-center gap-1">
                                    <span v-if="camp.scheduled_at && camp.status === 'scheduled'" class="text-amber-600 font-medium">
                                        Hẹn: {{ formatDate(camp.scheduled_at) }}<span v-if="camp.repeat_daily"> · Lặp hằng ngày</span>
                                    </span>
                                    <span v-else>
                                        {{ formatDate(camp.created_at) }}
                                    </span>
                                </span>
                            </div>

                            <div v-if="camp.image_url" class="rounded border overflow-hidden max-w-[150px] max-h-20">
                                <img :src="camp.image_url" alt="Banner" class="w-full h-full object-cover" />
                            </div>

                            <p class="text-xs text-muted-foreground line-clamp-2 bg-muted/20 p-2 rounded border border-border/50 font-sans">
                                "{{ camp.message_template }}"
                            </p>

                            <div class="flex items-center justify-between text-xs text-muted-foreground pt-1">
                                <div class="flex items-center gap-1.5">
                                    <Badge variant="outline" class="text-[10px]">
                                        {{ camp.trigger_type === 'all' ? 'Toàn bộ' : camp.trigger_type === 'inactive_30d' ? 'Inactive > 30d' : camp.trigger_type === 'inactive_40d' ? 'Inactive > 40d' : 'Có SĐT' }}
                                    </Badge>
                                    <span v-if="camp.image_url" class="text-[10px] text-indigo-600 font-medium flex items-center gap-1">
                                        <IconPhoto class="size-3" /> Kèm ảnh
                                    </span>
                                </div>
                                <span class="font-medium text-emerald-600">
                                    Đã gửi: {{ camp.sent_count || camp.messages_count }} tin nhắn
                                </span>
                            </div>
                        </div>

                        <div v-if="props.campaigns.data.length === 0" class="py-12 text-center text-muted-foreground text-sm">
                            Chưa có chiến dịch nào được gửi. Hãy tạo chiến dịch đầu tiên ở cột bên trái!
                        </div>
                    </div>

                    <!-- Tab 2: Checklist Table Khách Hàng & Retry -->
                    <div v-else class="space-y-3">
                        <!-- Action Bar for Checklist Table: Select All & Batch Retry -->
                        <div class="flex items-center justify-between p-2.5 bg-muted/30 border border-border rounded-lg text-xs">
                            <div class="flex items-center gap-2">
                                <input
                                    id="select-all-messages"
                                    type="checkbox"
                                    :checked="selectedMessageIds.length > 0 && selectedMessageIds.length === (props.sentMessages?.data?.length || 0)"
                                    class="rounded border-input text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                    @change="toggleSelectAll"
                                />
                                <label for="select-all-messages" class="cursor-pointer font-medium text-foreground select-none">
                                    Chọn tất cả ({{ selectedMessageIds.length }}/{{ props.sentMessages?.data?.length || 0 }})
                                </label>
                            </div>

                            <div class="flex items-center gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    :disabled="selectedMessageIds.length === 0 || isRetrying"
                                    class="h-7 text-xs gap-1.5 border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950 dark:border-indigo-800 dark:text-indigo-300"
                                    @click="retrySelectedMessages"
                                >
                                    <IconRotateClockwise class="size-3.5" :class="{ 'animate-spin': isRetrying }" />
                                    <span>Gửi lại (Retry) {{ selectedMessageIds.length ? `(${selectedMessageIds.length})` : '' }}</span>
                                </Button>
                            </div>
                        </div>

                        <!-- The Checklist Table -->
                        <div class="border border-border rounded-lg overflow-hidden max-h-[500px] overflow-y-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-muted/60 text-muted-foreground uppercase text-[10px] font-semibold sticky top-0 border-b border-border z-10 backdrop-blur-xs">
                                    <tr>
                                        <th class="p-2.5 w-8 text-center">
                                            <span class="sr-only">Check</span>
                                        </th>
                                        <th class="p-2.5">Khách hàng</th>
                                        <th class="p-2.5">Tin nhắn gửi</th>
                                        <th class="p-2.5 text-center">Trạng thái</th>
                                        <th class="p-2.5 text-right">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="msg in props.sentMessages?.data"
                                        :key="msg.id"
                                        :class="[
                                            'transition-colors',
                                            selectedMessageIds.includes(msg.id) ? 'bg-indigo-50/50 dark:bg-indigo-950/30' : 'hover:bg-muted/20',
                                        ]"
                                    >
                                        <!-- Checkbox -->
                                        <td class="p-2.5 text-center">
                                            <input
                                                type="checkbox"
                                                :checked="selectedMessageIds.includes(msg.id)"
                                                class="rounded border-input text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                                @change="toggleSelectOne(msg.id)"
                                            />
                                        </td>

                                        <!-- Khách hàng -->
                                        <td class="p-2.5 max-w-[130px]">
                                            <div class="font-medium text-foreground truncate">
                                                {{ msg.contact?.display_name || msg.contact?.name || 'Khách hàng' }}
                                            </div>
                                            <div v-if="msg.contact?.phone" class="text-[11px] font-mono text-muted-foreground">
                                                {{ msg.contact.phone }}
                                            </div>
                                            <div class="text-[10px] text-muted-foreground truncate">
                                                {{ msg.campaign?.name || 'Chiến dịch' }}
                                            </div>
                                        </td>

                                        <!-- Nội dung tin nhắn & ảnh preview -->
                                        <td class="p-2.5 max-w-[200px]">
                                            <p class="line-clamp-2 text-foreground font-sans leading-relaxed">
                                                {{ msg.sent_body }}
                                            </p>
                                            <div v-if="msg.image_url" class="mt-1 flex items-center gap-1 text-[10px] text-indigo-600 font-medium">
                                                <IconPhoto class="size-3" />
                                                <span>Có ảnh đính kèm</span>
                                            </div>
                                        </td>

                                        <!-- Trạng thái & Thời gian -->
                                        <td class="p-2.5 text-center whitespace-nowrap">
                                            <Badge
                                                :variant="msg.status === 'sent' ? 'outline' : 'destructive'"
                                                :class="[
                                                    'text-[10px] py-0 px-1.5',
                                                    msg.status === 'sent'
                                                        ? 'text-emerald-700 border-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                        : 'text-red-700 border-red-300 bg-red-50',
                                                ]"
                                            >
                                                {{ msg.status === 'sent' ? '✓ Đã gửi' : '✗ Thất bại' }}
                                            </Badge>
                                            <div class="text-[10px] text-muted-foreground font-mono mt-0.5">
                                                {{ formatDate(msg.sent_at) }}
                                            </div>
                                        </td>

                                        <!-- Hành động Retry đơn lẻ -->
                                        <td class="p-2.5 text-right whitespace-nowrap">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                class="h-7 px-2 text-xs text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 dark:hover:bg-indigo-950 gap-1"
                                                :disabled="isRetrying"
                                                title="Gửi lại tin nhắn này"
                                                @click="retrySingleMessage(msg.id)"
                                            >
                                                <IconRotateClockwise class="size-3" />
                                                <span>Retry</span>
                                            </Button>
                                        </td>
                                    </tr>

                                    <tr v-if="!props.sentMessages?.data?.length">
                                        <td colspan="5" class="py-12 text-center text-muted-foreground text-xs">
                                            Chưa có dữ liệu tin nhắn gửi nào trong hệ thống.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
