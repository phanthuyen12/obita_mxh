<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    IconCalendarTime,
    IconCopy,
    IconDeviceFloppy,
    IconEye,
    IconEyeOff,
    IconPlugConnected,
    IconRefresh,
} from '@tabler/icons-vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

interface PageAiCare {
    enabled: boolean;
    provider?: string;
    bot_name?: string;
    dify_api_key?: string;
    dify_base_url?: string;
    reply_mode?: string;
    reply_delay_seconds: number;
    operating_hours: {
        mode: '24/7' | 'custom';
        days: number[];
        start_time: string;
        end_time: string;
        timezone: string;
    };
    off_hours_behavior: string;
    off_hours_message: string;
    auto_tag_leads: boolean;
    lead_keywords: string[];
}

interface PageItem {
    id: string;
    display_name: string;
    username: string;
    platform: string;
    avatar_url: string | null;
    ai_care: PageAiCare;
}

const props = defineProps<{
    page: PageItem;
}>();

const emit = defineEmits<{
    (e: 'apply-to-all', careData: PageAiCare): void;
}>();

const form = ref<PageAiCare>({
    enabled: props.page.ai_care?.enabled ?? false,
    provider: 'dify',
    bot_name:
        props.page.ai_care?.bot_name ||
        'AI Chăm sóc ' + props.page.display_name,
    dify_api_key: props.page.ai_care?.dify_api_key || '',
    dify_base_url:
        props.page.ai_care?.dify_base_url ||
        'https://kingai.tnicorporation.com/v1',
    reply_mode: props.page.ai_care?.reply_mode || 'all',
    reply_delay_seconds: props.page.ai_care?.reply_delay_seconds ?? 3,
    operating_hours: {
        mode: props.page.ai_care?.operating_hours?.mode || '24/7',
        days: props.page.ai_care?.operating_hours?.days || [
            1, 2, 3, 4, 5, 6, 7,
        ],
        start_time: props.page.ai_care?.operating_hours?.start_time || '08:00',
        end_time: props.page.ai_care?.operating_hours?.end_time || '22:00',
        timezone:
            props.page.ai_care?.operating_hours?.timezone || 'Asia/Ho_Chi_Minh',
    },
    off_hours_behavior: props.page.ai_care?.off_hours_behavior || 'ai_reply',
    off_hours_message:
        props.page.ai_care?.off_hours_message ||
        'Dạ xin chào! Hiện tại đang ngoài giờ làm việc chính thức, chúng tôi sẽ phản hồi sớm nhất.',
    auto_tag_leads: props.page.ai_care?.auto_tag_leads ?? true,
    lead_keywords: props.page.ai_care?.lead_keywords || [
        'cà phê',
        'kingcoffee',
        'báo giá',
        'tư vấn',
        'mua hàng',
        'sđt',
        'đặt hàng',
        'pha máy',
    ],
});

// Sync form whenever props change
watch(
    () => props.page.ai_care,
    (newVal) => {
        if (newVal) {
            form.value = {
                ...form.value,
                ...newVal,
                operating_hours: {
                    ...form.value.operating_hours,
                    ...(newVal.operating_hours || {}),
                },
            };
        }
    },
    { deep: true },
);

const isSaving = ref(false);
const isTestingDify = ref(false);
const showApiKey = ref(false);
const difyTestResult = ref<{ success: boolean; message: string } | null>(null);
const keywordInput = ref('');

const weekDays = [
    { day: 1, label: 'Thứ 2' },
    { day: 2, label: 'Thứ 3' },
    { day: 3, label: 'Thứ 4' },
    { day: 4, label: 'Thứ 5' },
    { day: 5, label: 'Thứ 6' },
    { day: 6, label: 'Thứ 7' },
    { day: 7, label: 'Chủ Nhật' },
];

const toggleDay = (day: number) => {
    const days = form.value.operating_hours.days;
    if (days.includes(day)) {
        if (days.length > 1) {
            form.value.operating_hours.days = days.filter((d) => d !== day);
        }
    } else {
        form.value.operating_hours.days = [...days, day].sort();
    }
};

const addKeyword = () => {
    if (!keywordInput.value.trim()) return;
    if (!form.value.lead_keywords.includes(keywordInput.value.trim())) {
        form.value.lead_keywords.push(keywordInput.value.trim());
    }
    keywordInput.value = '';
};

const removeKeyword = (index: number) => {
    form.value.lead_keywords.splice(index, 1);
};

const testDifyConnection = async () => {
    isTestingDify.value = true;
    difyTestResult.value = null;

    try {
        const response = await fetch('/settings/account/ai/test-dify', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content || '',
            },
            body: JSON.stringify({
                dify_api_key: form.value.dify_api_key || '',
                dify_base_url: form.value.dify_base_url || '',
            }),
        });

        const data = await response.json();
        difyTestResult.value = data;
        if (data.success) {
            toast.success(data.message || 'Kết nối Dify Chatbot thành công!');
        } else {
            toast.error(data.message || 'Không thể kết nối đến Dify.');
        }
    } catch (e: any) {
        difyTestResult.value = {
            success: false,
            message: 'Lỗi mạng hoặc server không phản hồi: ' + e.message,
        };
        toast.error('Lỗi kiểm tra kết nối Dify.');
    } finally {
        isTestingDify.value = false;
    }
};

const saveConfig = () => {
    isSaving.value = true;
    router.put(
        `/settings/account/ai/pages/${props.page.id}`,
        form.value as any,
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    `Đã lưu cấu hình AI cho ${props.page.display_name} thành công!`,
                );
            },
            onError: (errors) => {
                console.error('Save page AI error:', errors);
                const errorMsg = Object.values(errors).flat().join('; ');
                toast.error(
                    `Lỗi khi lưu cấu hình: ${errorMsg || 'Vui lòng kiểm tra lại dữ liệu.'}`,
                );
            },
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
};

const applyToAll = () => {
    if (
        confirm(
            `Bạn có chắc chắn muốn áp dụng toàn bộ cấu hình Dify & Lịch hoạt động này cho TẤT CẢ các Page trong Workspace không?`,
        )
    ) {
        emit('apply-to-all', form.value);
    }
};
</script>

<template>
    <div
        class="space-y-5 rounded-2xl border-2 bg-card p-6 shadow-2xs transition-colors"
        :class="
            form.enabled
                ? 'border-primary/40 bg-card'
                : 'border-foreground/15 bg-muted/10'
        "
    >
        <!-- Page Card Header -->
        <div
            class="flex flex-wrap items-center justify-between gap-4 border-b border-foreground/10 pb-4"
        >
            <div class="flex items-center gap-3">
                <div
                    class="size-12 shrink-0 overflow-hidden rounded-full border-2 border-foreground"
                >
                    <img
                        v-if="page.avatar_url"
                        :src="page.avatar_url"
                        :alt="page.display_name"
                        class="size-full object-cover"
                    />
                    <div
                        v-else
                        class="flex size-full items-center justify-center bg-primary text-sm font-bold text-primary-foreground"
                    >
                        {{ page.display_name.slice(0, 1) }}
                    </div>
                </div>

                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-foreground">
                            {{ page.display_name }}
                        </h3>
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase"
                            :class="
                                form.enabled
                                    ? 'border border-emerald-300 bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                    : 'border bg-muted text-muted-foreground'
                            "
                        >
                            {{ form.enabled ? 'AI Đang Bật' : 'AI Đang Tắt' }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Nền tảng:
                        <span
                            class="font-semibold text-foreground capitalize"
                            >{{ page.platform }}</span
                        >
                        • ID: {{ page.id }}
                    </p>
                </div>
            </div>

            <!-- Master AI Enable/Disable Switch -->
            <div
                class="flex cursor-pointer items-center gap-3 rounded-xl border p-2.5 transition-colors select-none"
                :class="
                    form.enabled
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-foreground/15 bg-background text-muted-foreground hover:border-foreground/30'
                "
                @click="form.enabled = !form.enabled"
            >
                <div class="text-right">
                    <div class="text-xs font-bold text-foreground">
                        {{
                            form.enabled
                                ? 'Tự động phản hồi: BẬT'
                                : 'Tự động phản hồi: TẮT'
                        }}
                    </div>
                </div>
                <Switch
                    :checked="form.enabled"
                    @click.stop
                    @update:checked="form.enabled = Boolean($event)"
                />
            </div>
        </div>

        <!-- Configuration Body -->
        <div class="space-y-5">
            <!-- 1. DIFY CHATBOT INTEGRATION -->
            <div
                class="space-y-3.5 rounded-xl border-2 border-primary/25 bg-primary/5 p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <div
                            class="rounded-lg bg-primary p-2 text-primary-foreground"
                        >
                            <IconPlugConnected class="size-4" />
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-foreground">
                                Kết nối Dify Chatbot
                            </h4>
                            <p class="text-[11px] text-muted-foreground">
                                Dify tự động xử lý Prompt, Persona, Knowledge
                                Base sản phẩm và AI Model.
                            </p>
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="isTestingDify"
                        class="h-8 cursor-pointer gap-1.5 border-primary/30 text-xs font-bold text-primary hover:bg-primary/10"
                        @click="testDifyConnection"
                    >
                        <IconRefresh
                            class="size-3.5"
                            :class="isTestingDify ? 'animate-spin' : ''"
                        />
                        {{
                            isTestingDify
                                ? 'Đang kiểm tra...'
                                : 'Kiểm tra kết nối Dify'
                        }}
                    </Button>
                </div>

                <!-- Test Feedback Alert -->
                <div
                    v-if="difyTestResult"
                    class="rounded-lg border p-2.5 text-xs font-medium"
                    :class="
                        difyTestResult.success
                            ? 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                            : 'border-red-300 bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300'
                    "
                >
                    {{ difyTestResult.message }}
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label
                            :for="`dify-key-${page.id}`"
                            class="flex items-center justify-between text-xs font-bold"
                        >
                            <span
                                >Dify App Secret API Key
                                <span class="font-bold text-rose-600"
                                    >*</span
                                ></span
                            >
                            <button
                                type="button"
                                class="flex cursor-pointer items-center gap-1 text-[10px] font-normal text-muted-foreground hover:text-foreground"
                                @click="showApiKey = !showApiKey"
                            >
                                <component
                                    :is="showApiKey ? IconEyeOff : IconEye"
                                    class="size-3"
                                />
                                {{ showApiKey ? 'Ẩn' : 'Hiện' }}
                            </button>
                        </Label>
                        <Input
                            :id="`dify-key-${page.id}`"
                            v-model="form.dify_api_key"
                            :type="showApiKey ? 'text' : 'password'"
                            placeholder="app-xxxxxxxxxxxxxxxxxxxxxxxx"
                            class="font-mono text-xs"
                        />
                        <p class="text-[10px] text-muted-foreground">
                            Lấy API Key trong Dify:
                            <em
                                >Dify Studio &rarr; Ứng dụng &rarr; Truy cập API
                                &rarr; Khóa API</em
                            >.
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label
                            :for="`dify-url-${page.id}`"
                            class="text-xs font-bold"
                        >
                            Dify Base URL
                        </Label>
                        <Input
                            :id="`dify-url-${page.id}`"
                            v-model="form.dify_base_url"
                            placeholder="https://kingai.tnicorporation.com/v1"
                            class="text-xs"
                        />
                        <p class="text-[10px] text-muted-foreground">
                            Mặc định:
                            <code>https://kingai.tnicorporation.com/v1</code>
                        </p>
                    </div>
                </div>
            </div>

            <!-- 2. LỊCH & KHUNG GIỜ HOẠT ĐỘNG AI -->
            <div
                class="space-y-3.5 rounded-xl border border-foreground/15 bg-background p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <div class="rounded-lg bg-muted p-1.5 text-foreground">
                            <IconCalendarTime class="size-4 text-primary" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-foreground">
                                Lịch & Khung giờ hoạt động (Operating Schedule)
                            </h4>
                            <p class="text-[11px] text-muted-foreground">
                                Thiết lập thời gian AI tự động trả lời tin nhắn
                                của khách.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            class="cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-colors"
                            :class="[
                                form.operating_hours.mode === '24/7'
                                    ? 'bg-foreground text-background shadow-xs'
                                    : 'bg-muted text-muted-foreground hover:text-foreground',
                            ]"
                            @click="form.operating_hours.mode = '24/7'"
                        >
                            Trực 24/7 liên tục
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-lg px-3 py-1 text-xs font-bold transition-colors"
                            :class="[
                                form.operating_hours.mode === 'custom'
                                    ? 'bg-foreground text-background shadow-xs'
                                    : 'bg-muted text-muted-foreground hover:text-foreground',
                            ]"
                            @click="form.operating_hours.mode = 'custom'"
                        >
                            Theo giờ làm việc
                        </button>
                    </div>
                </div>

                <!-- Custom hours settings -->
                <div
                    v-if="form.operating_hours.mode === 'custom'"
                    class="space-y-3 border-t border-foreground/10 pt-3"
                >
                    <div class="space-y-1.5">
                        <Label class="text-xs font-bold"
                            >Các ngày hoạt động trong tuần</Label
                        >
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="d in weekDays"
                                :key="d.day"
                                type="button"
                                class="cursor-pointer rounded-md border px-2.5 py-1 text-xs font-bold transition-colors"
                                :class="[
                                    form.operating_hours.days.includes(d.day)
                                        ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                                        : 'border-foreground/15 bg-card text-muted-foreground hover:text-foreground',
                                ]"
                                @click="toggleDay(d.day)"
                            >
                                {{ d.label }}
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div class="space-y-1">
                            <Label class="text-xs font-bold"
                                >Giờ bắt đầu trực</Label
                            >
                            <Input
                                v-model="form.operating_hours.start_time"
                                type="time"
                                class="h-8 text-xs font-bold"
                            />
                        </div>

                        <div class="space-y-1">
                            <Label class="text-xs font-bold"
                                >Giờ kết thúc trực</Label
                            >
                            <Input
                                v-model="form.operating_hours.end_time"
                                type="time"
                                class="h-8 text-xs font-bold"
                            />
                        </div>

                        <div class="col-span-2 space-y-1 sm:col-span-1">
                            <Label class="text-xs font-bold">Múi giờ</Label>
                            <Input
                                v-model="form.operating_hours.timezone"
                                readonly
                                class="h-8 bg-muted text-xs font-medium text-muted-foreground"
                            />
                        </div>
                    </div>

                    <div class="grid gap-3 pt-1 sm:grid-cols-2">
                        <div class="space-y-1">
                            <Label class="text-xs font-bold"
                                >Xử lý ngoài giờ</Label
                            >
                            <select
                                v-model="form.off_hours_behavior"
                                class="h-8 w-full rounded-md border border-input bg-background px-2.5 text-xs font-medium text-foreground focus:ring-1 focus:ring-ring focus:outline-hidden"
                            >
                                <option value="ai_reply">
                                    🤖 AI vẫn trả lời kèm thông báo ngoài giờ
                                </option>
                                <option value="custom_message">
                                    ✉️ Chỉ gửi tin nhắn hẹn phản hồi sau
                                </option>
                                <option value="disabled">
                                    ⛔ Tắt bot hoàn toàn ngoài giờ
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <Label class="text-xs font-bold"
                                >Nội dung tin nhắn ngoài giờ</Label
                            >
                            <Input
                                v-model="form.off_hours_message"
                                placeholder="Cảm ơn bạn! Hiện tại shop đang ngoài giờ làm việc..."
                                class="h-8 text-xs"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. NÂNG CAO: ĐỘ TRỄ & TỪ KHÓA LEAD -->
            <div class="grid gap-3.5 md:grid-cols-2">
                <!-- Response Delay -->
                <div
                    class="space-y-2 rounded-xl border border-foreground/15 bg-background p-3.5"
                >
                    <Label class="text-xs font-bold"
                        >Độ trễ phản hồi (Response Delay)</Label
                    >
                    <div class="flex items-center gap-3">
                        <select
                            v-model.number="form.reply_delay_seconds"
                            class="h-8 w-32 rounded-md border border-input bg-background px-2 text-xs font-medium text-foreground"
                        >
                            <option :value="0">0 giây (Tức thì)</option>
                            <option :value="2">2 giây</option>
                            <option :value="3">3 giây (Chuẩn)</option>
                            <option :value="5">5 giây</option>
                            <option :value="10">10 giây</option>
                        </select>
                        <span class="text-[11px] text-muted-foreground">
                            Tạo cảm giác tự nhiên như nhân viên đang gõ.
                        </span>
                    </div>
                </div>

                <!-- Auto tag leads & Keywords -->
                <div
                    class="space-y-2 rounded-xl border border-foreground/15 bg-background p-3.5"
                >
                    <div class="flex items-center justify-between">
                        <Label class="text-xs font-bold"
                            >Tự động gắn Tag Khách Tiềm Năng (Lead)</Label
                        >
                        <Switch
                            :checked="form.auto_tag_leads"
                            @update:checked="form.auto_tag_leads = $event"
                        />
                    </div>

                    <div class="flex gap-2">
                        <Input
                            v-model="keywordInput"
                            placeholder="Thêm từ khóa (e.g. sđt, báo giá, mua hàng)..."
                            class="h-7 text-xs"
                            @keydown.enter.prevent="addKeyword"
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="h-7 px-2.5 text-xs font-bold"
                            @click="addKeyword"
                        >
                            Thêm
                        </Button>
                    </div>

                    <div class="flex flex-wrap gap-1 pt-0.5">
                        <span
                            v-for="(kw, idx) in form.lead_keywords"
                            :key="kw"
                            class="inline-flex items-center gap-1 rounded border bg-muted px-2 py-0.5 text-[10px] font-semibold text-foreground"
                        >
                            {{ kw }}
                            <button
                                type="button"
                                class="cursor-pointer font-bold text-muted-foreground hover:text-foreground"
                                @click="removeKeyword(idx)"
                            >
                                &times;
                            </button>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card Footer Actions -->
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-t border-foreground/10 pt-4"
        >
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="cursor-pointer gap-1.5 border-foreground/20 text-xs font-semibold"
                @click="applyToAll"
            >
                <IconCopy class="size-4" />
                Áp dụng cấu hình này cho tất cả Page
            </Button>

            <Button
                type="button"
                size="sm"
                :disabled="isSaving"
                class="cursor-pointer gap-1.5 font-bold"
                @click="saveConfig"
            >
                <IconDeviceFloppy class="size-4" />
                {{
                    isSaving
                        ? 'Đang lưu...'
                        : `Lưu cấu hình ${page.display_name}`
                }}
            </Button>
        </div>
    </div>
</template>
