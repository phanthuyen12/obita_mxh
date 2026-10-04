<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    IconBook,
    IconBrandFacebook,
    IconBrandInstagram,
    IconBrandTelegram,
    IconCheck,
    IconCurrencyDollar,
    IconDeviceFloppy,
    IconKey,
    IconLink,
    IconMessageChatbot,
    IconPhoto,
    IconPlus,
    IconRefresh,
    IconRobot,
    IconSend,
    IconSettings,
    IconSparkles,
    IconTrash,
    IconUpload,
    IconWorld,
} from '@tabler/icons-vue';
import axios from 'axios';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

import { retryKnowledgeSync as retryKnowledgeSyncRoute } from '@/actions/App/Http/Controllers/App/Omnichat/AiTrainController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

interface KnowledgeItem {
    id: string;
    title: string;
    file_type: string;
    file_path?: string | null;
    dify_document_id?: string | null;
    token_count: number;
    status: string;
    created_at: string;
}

interface ProductItem {
    id: string;
    sku: string;
    name: string;
    unit: string;
    price: string | number;
    sale_price: string | number | null;
    stock_quantity: number;
    is_in_stock: boolean;
}

interface BotItem {
    id: string;
    name: string;
    response_language: string;
    persona_tone: 'friendly' | 'professional' | 'enthusiastic';
    bot_role: 'sales_consultant' | 'cskh' | 'technical';
    greeting_message: string | null;
    system_prompt: string | null;
    objection_rules?: Array<{ rule: string; response: string }>;
    dify_base_url: string | null;
    dify_dataset_id?: string | null;
    dataset_key_set?: boolean;
    key_set: boolean;
    is_active: boolean;
    is_default: boolean;
}

interface ChannelItem {
    id: string;
    name: string;
    provider: string;
    type: 'social_account' | 'channel';
    ai_enabled: boolean;
    bot_id: string | null;
}

const props = defineProps<{
    knowledges: KnowledgeItem[];
    products: {
        data: ProductItem[];
        total: number;
    };
    bots: BotItem[];
    channels?: ChannelItem[];
    workspaceId: string;
}>();

const knowledgesList = ref([...props.knowledges]);
const productsList = ref([...props.products.data]);
const currentTab = ref<'knowledge' | 'products' | 'bot_config' | 'sandbox' | 'channels'>('knowledge');
const channelsList = ref<ChannelItem[]>(props.channels ? [...props.channels] : []);
const updatingChannelId = ref<string | null>(null);

const toggleChannelAi = async (channel: ChannelItem) => {
    updatingChannelId.value = channel.id;
    const newAiState = !channel.ai_enabled;
    try {
        const { data } = await axios.put(`/omnichat/livechat/channel-ai/${channel.id}`, {
            ai_enabled: newAiState,
            bot_id: channel.bot_id || undefined,
        });
        channel.ai_enabled = Boolean(data.enabled ?? newAiState);
        toast.success(`Đã ${channel.ai_enabled ? 'bật' : 'tắt'} AI trả lời tự động cho ${channel.name}`);
    } catch {
        toast.error(`Lỗi cập nhật cấu hình cho ${channel.name}`);
    } finally {
        updatingChannelId.value = null;
    }
};

const assignBotToChannel = async (channel: ChannelItem, botId: string | null) => {
    updatingChannelId.value = channel.id;
    try {
        await axios.put(`/omnichat/livechat/channel-ai/${channel.id}`, {
            ai_enabled: channel.ai_enabled,
            bot_id: botId || null,
        });
        channel.bot_id = botId;
        const assignedBot = botsList.value.find(b => b.id === botId);
        toast.success(`Đã gán bot "${assignedBot ? assignedBot.name : 'Mặc định'}" cho ${channel.name}`);
    } catch {
        toast.error(`Lỗi gán bot cho ${channel.name}`);
    } finally {
        updatingChannelId.value = null;
    }
};

// Knowledge Form
const newDocTitle = ref('');
const newDocContent = ref('');
const docFile = ref<File | null>(null);
const isUploadingDoc = ref(false);
const syncingKnowledgeId = ref<string | null>(null);

const handleFileUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files?.[0]) {
        docFile.value = target.files[0];
        if (!newDocTitle.value) {
            newDocTitle.value = target.files[0].name.replace(/\.[^/.]+$/, '');
        }
    }
};

const submitKnowledge = async () => {
    if (!newDocTitle.value.trim()) {
        toast.error('Vui lòng nhập tên tài liệu hoặc hình ảnh');
        return;
    }

    isUploadingDoc.value = true;
    try {
        const formData = new FormData();
        formData.append('title', newDocTitle.value);
        if (newDocContent.value) formData.append('content', newDocContent.value);
        if (docFile.value) formData.append('file', docFile.value);
        if (selectedBotId.value) formData.append('bot_id', selectedBotId.value);

        const { data } = await axios.post('/omnichat/ai-train/knowledge', formData);
        toast.success(data.message || 'Đã nạp tri thức thành công!');
        knowledgesList.value.unshift(data.knowledge);
        newDocTitle.value = '';
        newDocContent.value = '';
        docFile.value = null;
    } catch {
        toast.error('Lỗi khi nạp dữ liệu');
    } finally {
        isUploadingDoc.value = false;
    }
};

const retryKnowledgeSync = async (item: KnowledgeItem) => {
    syncingKnowledgeId.value = item.id;

    try {
        const { data } = await axios.post(retryKnowledgeSyncRoute.url({ knowledge: item.id }));
        const knowledgeIndex = knowledgesList.value.findIndex((knowledge) => knowledge.id === item.id);

        if (knowledgeIndex !== -1) {
            knowledgesList.value[knowledgeIndex] = data.knowledge;
        }

        toast.success(data.message || 'Đã đồng bộ tài liệu lên Dify.');
    } catch (error) {
        const message = axios.isAxiosError(error)
            ? error.response?.data?.message
            : null;

        toast.error(message || 'Không thể đồng bộ tài liệu lên Dify.');
    } finally {
        syncingKnowledgeId.value = null;
    }
};

const deleteKnowledge = async (id: string) => {
    if (!confirm('Bạn có chắc chắn muốn xóa tài liệu/hình ảnh tri thức này?')) return;
    try {
        await axios.delete(`/omnichat/ai-train/knowledge/${id}`);
        toast.success('Đã xóa tài liệu tri thức');
        const idx = knowledgesList.value.findIndex(k => k.id === id);
        if (idx !== -1) knowledgesList.value.splice(idx, 1);
    } catch {
        toast.error('Lỗi khi xóa tài liệu');
    }
};

// Product Form
const newSku = ref('');
const newName = ref('');
const newPrice = ref<number | ''>('');
const newSalePrice = ref<number | ''>('');
const newStock = ref<number>(100);
const newUnit = ref('hộp');
const isAddingProduct = ref(false);

const submitProduct = async () => {
    if (!newSku.value || !newName.value || newPrice.value === '') {
        toast.error('Vui lòng điền đầy đủ Mã SKU, Tên SP và Giá');
        return;
    }

    isAddingProduct.value = true;
    try {
        const { data } = await axios.post('/omnichat/ai-train/product', {
            sku: newSku.value,
            name: newName.value,
            unit: newUnit.value,
            price: newPrice.value,
            sale_price: newSalePrice.value || null,
            stock_quantity: newStock.value,
        });
        toast.success('Đã thêm sản phẩm vào bảng giá AI!');
        productsList.value.unshift(data.product);
        newSku.value = '';
        newName.value = '';
        newPrice.value = '';
        newSalePrice.value = '';
    } catch {
        toast.error('Lỗi khi lưu sản phẩm');
    } finally {
        isAddingProduct.value = false;
    }
};

// ========================
// Bot Config / Customization
// ========================
const botsList = ref<BotItem[]>([...props.bots]);
const selectedBotId = ref<string>(props.bots.find(b => b.is_default)?.id || props.bots[0]?.id || '');
const activeBot = ref<Partial<BotItem> & { dify_api_key?: string; dify_dataset_api_key?: string }>({
    name: 'Trợ lý Bán Hàng AI King',
    response_language: 'vi',
    persona_tone: 'friendly',
    bot_role: 'sales_consultant',
    greeting_message: 'Dạ em chào anh/chị ạ! Em là chuyên viên tư vấn của shop. Em có thể hỗ trợ anh/chị chọn sản phẩm, tra cứu bảng giá hoặc hướng dẫn đặt hàng nhận ưu đãi hôm nay ạ!',
    system_prompt: 'Bạn là chuyên viên tư vấn bán hàng chuyên nghiệp, tận tâm và thân thiện. Nhiệm vụ chính của bạn là giải đáp thắc mắc về sản phẩm, báo giá chính xác theo bảng giá của shop, xử lý từ chối và khéo léo xin số điện thoại để chốt đơn chuyển giao cho nhân sự chăm sóc.',
    dify_base_url: 'https://kingai.tnicorporation.com/v1',
    dify_api_key: '',
    dify_dataset_id: '',
    dify_dataset_api_key: '',
    is_active: true,
    is_default: true,
});

if (props.bots.length > 0) {
    const defaultOne = props.bots.find(b => b.is_default) || props.bots[0];
    activeBot.value = {
        ...defaultOne,
        dify_api_key: '',
        dify_dataset_api_key: '',
    };
    selectedBotId.value = defaultOne.id;
}

const selectBotToEdit = (bot: BotItem) => {
    selectedBotId.value = bot.id;
    activeBot.value = {
        ...bot,
        dify_api_key: '',
        dify_dataset_api_key: '',
    };
};

const initNewBot = () => {
    selectedBotId.value = '';
    activeBot.value = {
        name: 'Trợ lý AI Mới',
        response_language: 'vi',
        persona_tone: 'friendly',
        bot_role: 'sales_consultant',
        greeting_message: 'Dạ em chào anh/chị! Em có thể giúp gì cho mình hôm nay ạ?',
        system_prompt: 'Bạn là nhân viên tư vấn nhiệt tình, thân thiện, ưu tiên tư vấn sản phẩm và chốt đơn.',
        dify_base_url: 'https://kingai.tnicorporation.com/v1',
        dify_api_key: '',
        dify_dataset_id: '',
        dify_dataset_api_key: '',
        is_active: true,
        is_default: botsList.value.length === 0,
    };
};

const isSavingBot = ref(false);
const isTestingDify = ref(false);
const difyTestResult = ref<{ success: boolean; message: string } | null>(null);

const testDifyConnection = async () => {
    isTestingDify.value = true;
    difyTestResult.value = null;
    try {
        const { data } = await axios.post('/omnichat/ai-train/test-dify', {
            bot_id: selectedBotId.value || undefined,
            dify_api_key: activeBot.value.dify_api_key || undefined,
            dify_base_url: activeBot.value.dify_base_url || undefined,
        });
        difyTestResult.value = data;
        if (data.success) {
            toast.success(data.message);
        } else {
            toast.error(data.message);
        }
    } catch {
        difyTestResult.value = { success: false, message: 'Không thể kết nối đến máy chủ AI Dify.' };
        toast.error('Lỗi khi kiểm tra kết nối Dify');
    } finally {
        isTestingDify.value = false;
    }
};

const saveBotConfig = async () => {
    if (!activeBot.value.name?.trim()) {
        toast.error('Vui lòng nhập tên Bot AI');
        return;
    }

    isSavingBot.value = true;
    try {
        const payload: Record<string, any> = {
            name: activeBot.value.name,
            response_language: activeBot.value.response_language || 'vi',
            persona_tone: activeBot.value.persona_tone,
            bot_role: activeBot.value.bot_role,
            greeting_message: activeBot.value.greeting_message,
            system_prompt: activeBot.value.system_prompt,
            dify_base_url: activeBot.value.dify_base_url,
            dify_dataset_id: activeBot.value.dify_dataset_id,
            is_active: activeBot.value.is_active ?? true,
            is_default: activeBot.value.is_default ?? false,
        };

        if (activeBot.value.dify_api_key) {
            payload.dify_api_key = activeBot.value.dify_api_key;
        }

        if (activeBot.value.dify_dataset_api_key) {
            payload.dify_dataset_api_key = activeBot.value.dify_dataset_api_key;
        }

        if (selectedBotId.value) {
            // Update existing bot
            const { data } = await axios.put(`/omnichat/bots/${selectedBotId.value}`, payload);
            toast.success('Đã cập nhật cấu hình Bot AI thành công!');
            const idx = botsList.value.findIndex(b => b.id === selectedBotId.value);
            if (idx !== -1) {
                botsList.value[idx] = data.bot;
            }
        } else {
            // Create new bot
            if (!payload.dify_api_key) {
                toast.error('Vui lòng nhập Dify API Key để tạo bot kết nối');
                isSavingBot.value = false;
                return;
            }
            const { data } = await axios.post('/omnichat/bots', payload);
            toast.success('Đã tạo Bot AI mới thành công!');
            botsList.value.unshift(data.bot);
            selectedBotId.value = data.bot.id;
        }
    } catch (err: any) {
        toast.error(err.response?.data?.message || 'Lỗi khi lưu cấu hình Bot');
    } finally {
        isSavingBot.value = false;
    }
};

// ========================
// Sandbox Chat
// ========================
interface ChatMsg {
    sender: 'user' | 'bot';
    text: string;
    source?: string;
}

const chatInput = ref('');
const chatMessages = ref<ChatMsg[]>([
    {
        sender: 'bot',
        text: 'Xin chào anh/chị! Em là trợ lý AI Sale chuyên viên tư vấn. Em đã được học kiến thức sản phẩm và bảng giá của shop. Anh/chị hãy chat thử hỏi giá hoặc hỏi tư vấn nhé!',
    },
]);
const isWaitingAi = ref(false);

const sendSandboxMessage = async () => {
    const text = chatInput.value.trim();
    if (!text || isWaitingAi.value) return;

    chatMessages.value.push({ sender: 'user', text });
    chatInput.value = '';
    isWaitingAi.value = true;

    try {
        const { data } = await axios.post('/omnichat/ai-train/sandbox', {
            message: text,
            bot_id: selectedBotId.value || undefined,
        });
        chatMessages.value.push({
            sender: 'bot',
            text: data.reply,
            source: data.source,
        });
    } catch {
        chatMessages.value.push({
            sender: 'bot',
            text: 'Dạ hiện tại hệ thống kiểm tra đang bận, anh/chị thử lại câu khác nhé!',
        });
    } finally {
        isWaitingAi.value = false;
    }
};

const formatCurrency = (val: string | number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(val));
};
</script>

<template>
    <AppLayout>
        <Head title="Huấn luyện Bot AI Sale" />

        <div class="px-4 py-6 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-border pb-5">
                <div>
                    <div class="flex items-center gap-2">
                        <IconSparkles class="size-6 text-indigo-500" />
                        <h1 class="text-2xl font-bold tracking-tight text-foreground">
                            Huấn luyện Bot AI Sale (Sale Agent)
                        </h1>
                    </div>
                    <p class="text-sm text-muted-foreground mt-1">
                        Tùy chỉnh tính cách Bot, kết nối Dify/LLM, nạp tài liệu sản phẩm và kiểm tra kịch bản bán hàng.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-1.5"
                        @click="currentTab = 'channels'"
                    >
                        <IconBrandFacebook class="size-4 text-blue-600" />
                        Gán Page & Kênh
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-1.5"
                        @click="currentTab = 'bot_config'"
                    >
                        <IconSettings class="size-4" />
                        Tùy chỉnh Bot & Prompt
                    </Button>
                    <Button
                        size="sm"
                        class="gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white"
                        @click="currentTab = 'sandbox'"
                    >
                        <IconMessageChatbot class="size-4" />
                        Thử nghiệm Chat
                    </Button>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="flex border-b border-border space-x-2 overflow-x-auto">
                <button
                    v-for="tab in [
                        { key: 'knowledge', label: '1. Tri thức & Tài liệu (RAG)', icon: IconBook },
                        { key: 'products', label: '2. Bảng giá & Tồn kho', icon: IconCurrencyDollar },
                        { key: 'bot_config', label: '3. Tùy chỉnh Bot & Kịch bản (Dify / Prompt)', icon: IconRobot },
                        { key: 'sandbox', label: '4. Chat Thử Nghiệm (Sandbox)', icon: IconMessageChatbot },
                        { key: 'channels', label: '5. Cấu hình cho Page / Kênh (Page AI Care)', icon: IconBrandFacebook },
                    ]"
                    :key="tab.key"
                    :class="[
                        'flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                        currentTab === tab.key
                            ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400 font-semibold'
                            : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border',
                    ]"
                    @click="currentTab = tab.key as any"
                >
                    <component :is="tab.icon" class="size-4" />
                    {{ tab.label }}
                    <span v-if="tab.key === 'channels' && channelsList.length > 0" class="text-xs bg-muted px-1.5 py-0.5 rounded-full text-foreground font-normal">
                        {{ channelsList.length }}
                    </span>
                </button>
            </div>

            <!-- TAB 1: KNOWLEDGE BASE -->
            <div v-if="currentTab === 'knowledge'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1 bg-card border border-border rounded-xl p-5 shadow-xs space-y-4">
                    <HeadingSmall
                        title="Nạp tài liệu & Hình ảnh mới"
                        description="Hỗ trợ file PDF, Word, TXT hoặc Hình ảnh (PNG, JPG, WEBP) và văn bản chính sách."
                    />

                    <div class="space-y-3">
                        <div>
                            <Label for="doc-title">Tên tài liệu / Mô tả hình ảnh</Label>
                            <Input
                                id="doc-title"
                                v-model="newDocTitle"
                                placeholder="VD: Ảnh catalog sản phẩm hoặc Bảng quyền lợi 2026"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label>Tải lên File hoặc Hình ảnh</Label>
                            <input
                                type="file"
                                accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg,.webp,.gif"
                                class="mt-1 block w-full text-sm text-muted-foreground file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-950 dark:file:text-indigo-300"
                                @change="handleFileUpload"
                            />
                            <p class="text-[11px] text-muted-foreground mt-1">
                                Hỗ trợ: PDF, Word, Text, JPG, PNG, WEBP (tự động đồng bộ sang Dify Knowledge)
                            </p>
                        </div>

                        <div>
                            <Label for="doc-content">Hoặc dán nội dung văn bản giải thích</Label>
                            <textarea
                                id="doc-content"
                                v-model="newDocContent"
                                rows="4"
                                placeholder="Dán thông tin chi tiết sản phẩm, ưu điểm cạnh tranh, mô tả chi tiết hình ảnh..."
                                class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            ></textarea>
                        </div>

                        <Button
                            :disabled="isUploadingDoc"
                            class="w-full flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white"
                            @click="submitKnowledge"
                        >
                            <IconUpload class="size-4" />
                            {{ isUploadingDoc ? 'Đang nạp dữ liệu & upload Dify...' : 'Nạp tri thức cho AI' }}
                        </Button>
                    </div>
                </div>

                <div class="lg:col-span-2 bg-card border border-border rounded-xl p-5 shadow-xs">
                    <HeadingSmall
                        title="Kho tài liệu & Hình ảnh đã nạp"
                        description="Danh sách các bộ tài liệu/hình ảnh AI đang đọc hiểu để tư vấn cho khách hàng."
                    />

                    <div class="mt-4 divide-y divide-border">
                        <div
                            v-for="item in knowledgesList"
                            :key="item.id"
                            class="py-3 flex items-center justify-between gap-3"
                        >
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-indigo-50 dark:bg-indigo-950/50 rounded-lg text-indigo-600">
                                    <IconPhoto v-if="['png', 'jpg', 'jpeg', 'webp', 'gif'].includes(item.file_type.toLowerCase())" class="size-5" />
                                    <IconBook v-else class="size-5" />
                                </div>
                                <div>
                                    <div class="font-medium text-foreground">{{ item.title }}</div>
                                    <div class="text-xs text-muted-foreground mt-0.5">
                                        Định dạng: {{ item.file_type.toUpperCase() }} • {{ item.token_count }} ký tự
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge
                                    v-if="item.dify_document_id"
                                    variant="outline"
                                    class="bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-400 text-[10px]"
                                >
                                    ✓ Đồng bộ Dify
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 text-[10px]"
                                >
                                    Chưa đồng bộ Dify
                                </Badge>
                                <Badge variant="outline" class="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 text-[10px]">
                                    Đã sẵn sàng
                                </Badge>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="syncingKnowledgeId === item.id"
                                    :title="syncingKnowledgeId === item.id ? 'Đang đồng bộ' : 'Đồng bộ lại lên Dify'"
                                    @click="retryKnowledgeSync(item)"
                                >
                                    <IconRefresh class="size-4" :class="{ 'animate-spin': syncingKnowledgeId === item.id }" />
                                    {{ syncingKnowledgeId === item.id ? 'Đang đồng bộ...' : item.dify_document_id ? 'Đồng bộ lại' : 'Đồng bộ Dify' }}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-muted-foreground hover:text-red-500"
                                    title="Xóa tri thức"
                                    @click="deleteKnowledge(item.id)"
                                >
                                    <IconTrash class="size-4" />
                                </Button>
                            </div>
                        </div>

                        <div v-if="knowledgesList.length === 0" class="py-8 text-center text-muted-foreground text-sm">
                            Chưa có tài liệu nào. Hãy nạp file đầu tiên bên cột trái!
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: PRODUCTS & PRICING -->
            <div v-else-if="currentTab === 'products'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1 bg-card border border-border rounded-xl p-5 shadow-xs space-y-4">
                    <HeadingSmall
                        title="Thêm sản phẩm & Bảng giá"
                        description="AI sẽ tra cứu trực tiếp bảng này khi khách hỏi giá hoặc hỏi tồn kho."
                    />

                    <div class="space-y-3">
                        <div>
                            <Label for="sku">Mã SKU</Label>
                            <Input id="sku" v-model="newSku" placeholder="VD: CF-3IN1-18G" class="mt-1" />
                        </div>
                        <div>
                            <Label for="name">Tên sản phẩm</Label>
                            <Input id="name" v-model="newName" placeholder="Cà phê sữa đá King 3in1" class="mt-1" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="price">Giá niêm yết</Label>
                                <Input id="price" v-model="newPrice" type="number" placeholder="65000" class="mt-1" />
                            </div>
                            <div>
                                <Label for="sale-price">Giá khuyến mãi</Label>
                                <Input id="sale-price" v-model="newSalePrice" type="number" placeholder="55000" class="mt-1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="stock">Số lượng tồn</Label>
                                <Input id="stock" v-model="newStock" type="number" placeholder="100" class="mt-1" />
                            </div>
                            <div>
                                <Label for="unit">Đơn vị</Label>
                                <Input id="unit" v-model="newUnit" placeholder="hộp" class="mt-1" />
                            </div>
                        </div>

                        <Button
                            :disabled="isAddingProduct"
                            class="w-full flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white"
                            @click="submitProduct"
                        >
                            <IconPlus class="size-4" />
                            {{ isAddingProduct ? 'Đang lưu...' : 'Lưu vào bảng giá' }}
                        </Button>
                    </div>
                </div>

                <div class="lg:col-span-2 bg-card border border-border rounded-xl p-5 shadow-xs">
                    <HeadingSmall
                        title="Bảng giá & Danh mục sản phẩm"
                        description="Dữ liệu chính xác 100% giúp Bot không bao giờ bịa giá (hallucination)."
                    />

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-border text-xs uppercase text-muted-foreground bg-muted/40">
                                <tr>
                                    <th class="py-2.5 px-3">Mã SKU</th>
                                    <th class="py-2.5 px-3">Tên sản phẩm</th>
                                    <th class="py-2.5 px-3">Giá bán</th>
                                    <th class="py-2.5 px-3">Giá ưu đãi</th>
                                    <th class="py-2.5 px-3">Tồn kho</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="p in productsList" :key="p.id">
                                    <td class="py-2.5 px-3 font-mono font-medium">{{ p.sku }}</td>
                                    <td class="py-2.5 px-3 text-foreground font-medium">{{ p.name }}</td>
                                    <td class="py-2.5 px-3">{{ formatCurrency(p.price) }}</td>
                                    <td class="py-2.5 px-3 text-emerald-600 font-semibold">
                                        {{ p.sale_price ? formatCurrency(p.sale_price) : '-' }}
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <Badge :variant="p.stock_quantity > 0 ? 'default' : 'destructive'">
                                            {{ p.stock_quantity }} {{ p.unit }}
                                        </Badge>
                                    </td>
                                </tr>
                                <tr v-if="productsList.length === 0">
                                    <td colspan="5" class="py-6 text-center text-muted-foreground">
                                        Chưa có sản phẩm nào. Hãy thêm sản phẩm ở cột bên trái!
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: CUSTOMIZE BOT & PROMPT / DIFY -->
            <div v-else-if="currentTab === 'bot_config'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Bot list & Selector -->
                <div class="lg:col-span-1 bg-card border border-border rounded-xl p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <HeadingSmall
                            title="Danh sách Bot AI"
                            description="Chọn Bot để tùy chỉnh hoặc thêm mới"
                        />
                        <Button size="sm" variant="outline" class="gap-1 text-xs" @click="initNewBot">
                            <IconPlus class="size-3.5" />
                            Tạo mới
                        </Button>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="bot in botsList"
                            :key="bot.id"
                            :class="[
                                'p-3 rounded-lg border text-sm cursor-pointer transition-all flex items-center justify-between',
                                selectedBotId === bot.id
                                    ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/20'
                                    : 'border-border hover:border-border/80 hover:bg-muted/40',
                            ]"
                            @click="selectBotToEdit(bot)"
                        >
                            <div class="space-y-1">
                                <div class="font-medium flex items-center gap-1.5">
                                    <IconRobot class="size-4 text-indigo-600" />
                                    <span>{{ bot.name }}</span>
                                    <Badge v-if="bot.is_default" variant="secondary" class="text-[10px] py-0 px-1">
                                        Mặc định
                                    </Badge>
                                </div>
                                <div class="text-xs text-muted-foreground flex items-center gap-2">
                                    <span>{{ bot.persona_tone === 'friendly' ? '😊 Thân thiện' : bot.persona_tone === 'professional' ? '👔 Chuyên nghiệp' : '🔥 Nhiệt tình' }}</span>
                                    <span>•</span>
                                    <span :class="bot.key_set ? 'text-emerald-600' : 'text-amber-600'">
                                        {{ bot.key_set ? '✓ Có API Key' : 'Chưa có Key' }}
                                    </span>
                                </div>
                            </div>
                            <IconCheck v-if="selectedBotId === bot.id" class="size-4 text-indigo-600" />
                        </div>

                        <div v-if="botsList.length === 0" class="text-center py-6 text-sm text-muted-foreground">
                            Chưa có Bot nào. Nhấn "Tạo mới" để bắt đầu!
                        </div>
                    </div>
                </div>

                <!-- Right: Form settings -->
                <div class="lg:col-span-2 bg-card border border-border rounded-xl p-6 shadow-xs space-y-6">
                    <div class="flex items-center justify-between border-b border-border pb-4">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">
                                {{ selectedBotId ? 'Chỉnh sửa Cấu hình Bot' : 'Tạo Bot AI mới' }}
                            </h2>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                Tùy chỉnh câu xưng hô chào mừng, giọng điệu, hướng dẫn hành vi bán hàng và kết nối Dify API.
                            </p>
                        </div>
                        <Badge v-if="activeBot.is_default" class="bg-indigo-50 text-indigo-700 border-indigo-200">
                            Bot chính tự động trả lời
                        </Badge>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Tên Bot -->
                        <div>
                            <Label for="bot-name">Tên hiển thị của Bot</Label>
                            <Input
                                id="bot-name"
                                v-model="activeBot.name"
                                placeholder="VD: Trợ lý Bán Hàng King Coffee"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <Label for="bot-language">Ngôn ngữ trả lời</Label>
                            <select
                                id="bot-language"
                                v-model="activeBot.response_language"
                                class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="vi">Tiếng Việt</option>
                                <option value="en">English</option>
                                <option value="zh">中文</option>
                                <option value="ja">日本語</option>
                                <option value="ko">한국어</option>
                                <option value="fr">Français</option>
                                <option value="es">Español</option>
                                <option value="th">ภาษาไทย</option>
                                <option value="id">Bahasa Indonesia</option>
                            </select>
                        </div>

                        <!-- Giọng điệu -->
                        <div>
                            <Label for="bot-tone">Giọng điệu / Phong cách (Tone of Voice)</Label>
                            <select
                                id="bot-tone"
                                v-model="activeBot.persona_tone"
                                class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="friendly">😊 Thân thiện, gần gũi (Em - Anh/Chị)</option>
                                <option value="professional">👔 Chuyên nghiệp, lịch sự (Chúng tôi - Quý khách)</option>
                                <option value="enthusiastic">🔥 Nhiệt tình, sôi nổi, nhiều icon</option>
                            </select>
                        </div>
                    </div>

                    <!-- Lời chào mừng -->
                    <div>
                        <Label for="bot-greeting">Lời chào mở đầu khi khách nhắn tin (Greeting Message)</Label>
                        <textarea
                            id="bot-greeting"
                            v-model="activeBot.greeting_message"
                            rows="2"
                            placeholder="Dạ em chào anh/chị ạ! Em có thể giúp gì cho mình hôm nay?"
                            class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <!-- System Prompt -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <Label for="bot-prompt">Kịch bản chỉ đạo AI (System Prompt / Instructions)</Label>
                            <span class="text-xs text-muted-foreground">Hướng dẫn phong cách tư vấn, quy tắc chốt đơn</span>
                        </div>
                        <textarea
                            id="bot-prompt"
                            v-model="activeBot.system_prompt"
                            rows="4"
                            placeholder="Bạn là chuyên viên tư vấn bán hàng. Luôn kiểm tra bảng giá trước khi báo giá. Khi khách chê giá cao, hãy giải thích về chất lượng nguyên liệu và cam kết bảo hành..."
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-sans focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <!-- Dify Connection Settings -->
                    <div class="p-4 rounded-xl border border-indigo-100 bg-indigo-50/30 dark:bg-indigo-950/20 dark:border-indigo-900 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <IconLink class="size-4 text-indigo-600" />
                                <span class="font-semibold text-sm text-foreground">Kết nối Dify Chatflow / Agent API</span>
                            </div>
                            <span class="text-xs text-muted-foreground">Tùy chọn kết nối bot ngoại vi Dify</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <Label for="dify-base-url">Dify API Base URL</Label>
                                <Input
                                    id="dify-base-url"
                                    v-model="activeBot.dify_base_url"
                                    placeholder="https://api.dify.ai/v1 hoặc https://kingai.tnicorporation.com/v1"
                                    class="mt-1 font-mono text-xs"
                                />
                            </div>
                            <div>
                                <Label for="dify-api-key">Dify App API Key (app-...)</Label>
                                <div class="relative mt-1">
                                    <Input
                                        id="dify-api-key"
                                        v-model="activeBot.dify_api_key"
                                        type="password"
                                        :placeholder="activeBot.key_set ? '•••••••••••••••• (Đã lưu)' : 'app-xxxx...'"
                                        class="font-mono text-xs pr-8"
                                    />
                                    <IconKey class="absolute right-2.5 top-2.5 size-4 text-muted-foreground" />
                                </div>
                            </div>
                        </div>

                        <!-- Knowledge Base / Dataset API Config -->
                        <div class="border-t border-indigo-100 dark:border-indigo-900/60 pt-3 space-y-2">
                            <div class="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                                <IconBook class="size-3.5 text-indigo-600" />
                                <span>Cấu hình Dify Knowledge Base (Dataset) - Đồng bộ tri thức</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <Label for="dify-dataset-id">Dataset ID (ID bộ tri thức trên Dify)</Label>
                                    <Input
                                        id="dify-dataset-id"
                                        v-model="activeBot.dify_dataset_id"
                                        placeholder="VD: 550e8400-e29b-41d4-a716-446655440000"
                                        class="mt-1 font-mono text-xs"
                                    />
                                </div>
                                <div>
                                    <Label for="dify-dataset-api-key">Dataset API Key (dataset-...)</Label>
                                    <div class="relative mt-1">
                                        <Input
                                            id="dify-dataset-api-key"
                                            v-model="activeBot.dify_dataset_api_key"
                                            type="password"
                                            :placeholder="activeBot.dataset_key_set ? '•••••••••••••••• (Đã lưu)' : 'dataset-xxxx... (hoặc để trống dùng App Key)'"
                                            class="font-mono text-xs pr-8"
                                        />
                                        <IconKey class="absolute right-2.5 top-2.5 size-4 text-muted-foreground" />
                                    </div>
                                </div>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Khi điền Dataset ID, mỗi tài liệu hoặc văn bản bạn nạp ở Tab 1 sẽ được đẩy trực tiếp lên Knowledge Base của Dify để AI đọc hiểu.
                            </p>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="isTestingDify"
                                class="gap-1.5 text-xs"
                                @click="testDifyConnection"
                            >
                                <IconLink class="size-3.5" />
                                {{ isTestingDify ? 'Đang kiểm tra kết nối...' : 'Kiểm tra kết nối Dify' }}
                            </Button>

                            <div v-if="difyTestResult" class="text-xs">
                                <span :class="difyTestResult.success ? 'text-emerald-600 font-medium' : 'text-red-500 font-medium'">
                                    {{ difyTestResult.message }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tùy chọn Mặc định & Trạng thái -->
                    <div class="flex flex-wrap items-center gap-6 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                            <input
                                v-model="activeBot.is_active"
                                type="checkbox"
                                class="rounded border-input text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>Kích hoạt Bot này</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                            <input
                                v-model="activeBot.is_default"
                                type="checkbox"
                                class="rounded border-input text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>Đặt làm Bot mặc định cho Workspace</span>
                        </label>
                    </div>

                    <!-- Submit action -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                        <Button
                            :disabled="isSavingBot"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white gap-2"
                            @click="saveBotConfig"
                        >
                            <IconDeviceFloppy class="size-4" />
                            {{ isSavingBot ? 'Đang lưu cấu hình...' : 'Lưu cấu hình Bot AI' }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- TAB 4: SANDBOX CHAT -->
            <div v-else-if="currentTab === 'sandbox'" class="max-w-3xl mx-auto bg-card border border-border rounded-xl shadow-xs overflow-hidden flex flex-col h-[650px]">
                <div class="p-4 border-b border-border flex items-center justify-between bg-muted/20">
                    <div class="flex items-center gap-3">
                        <div class="size-9 rounded-full bg-indigo-600 flex items-center justify-center text-white">
                            <IconRobot class="size-5" />
                        </div>
                        <div>
                            <div class="font-semibold text-foreground text-sm flex items-center gap-2">
                                <span>{{ activeBot.name || 'Bot AI Sale Simulator' }}</span>
                                <Badge variant="secondary" class="text-[10px]">
                                    {{ activeBot.persona_tone === 'friendly' ? 'Thân thiện' : activeBot.persona_tone === 'professional' ? 'Chuyên nghiệp' : 'Nhiệt tình' }}
                                </Badge>
                            </div>
                            <div class="text-xs text-muted-foreground">Đang áp dụng tri thức, bảng giá & prompt mới nhất</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <select
                            v-if="botsList.length > 1"
                            v-model="selectedBotId"
                            class="text-xs bg-background border border-input rounded-md px-2 py-1"
                        >
                            <option v-for="b in botsList" :key="b.id" :value="b.id">
                                {{ b.name }}
                            </option>
                        </select>
                        <Badge variant="outline" class="text-indigo-600 border-indigo-200">Sandbox Mode</Badge>
                    </div>
                </div>

                <!-- Chat history -->
                <div class="flex-1 p-4 overflow-y-auto space-y-3 bg-muted/5">
                    <div
                        v-for="(msg, idx) in chatMessages"
                        :key="idx"
                        :class="[
                            'flex flex-col max-w-[80%]',
                            msg.sender === 'user' ? 'ml-auto items-end' : 'mr-auto items-start',
                        ]"
                    >
                        <div
                            :class="[
                                'px-3.5 py-2.5 rounded-2xl text-sm leading-relaxed whitespace-pre-line',
                                msg.sender === 'user'
                                    ? 'bg-indigo-600 text-white rounded-br-xs'
                                    : 'bg-card border border-border text-foreground rounded-bl-xs shadow-2xs',
                            ]"
                        >
                            {{ msg.text }}
                        </div>
                        <span v-if="msg.source" class="text-[10px] text-muted-foreground mt-1">
                            Nguồn: {{ msg.source }}
                        </span>
                    </div>
                    <div v-if="isWaitingAi" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <span class="animate-pulse">Bot AI đang tra cứu tri thức & trả lời...</span>
                    </div>
                </div>

                <!-- Chat input -->
                <div class="p-3 border-t border-border flex gap-2 bg-card">
                    <Input
                        v-model="chatInput"
                        placeholder="Hỏi thử giá sản phẩm, chính sách bảo hành, hoặc chê đắt..."
                        @keydown.enter="sendSandboxMessage"
                    />
                    <Button
                        :disabled="isWaitingAi"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white"
                        @click="sendSandboxMessage"
                    >
                        <IconSend class="size-4" />
                    </Button>
                </div>
            </div>

            <!-- TAB 5: PAGE & CHANNEL AI ASSIGNMENT -->
            <div v-if="currentTab === 'channels'" class="space-y-6">
                <div class="bg-card border border-border rounded-xl p-6 shadow-xs space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border pb-4">
                        <div>
                            <HeadingSmall
                                title="Cấu hình Bot AI tự động chăm sóc theo từng Fanpage / Kênh"
                                description="Gán bot AI đã huấn luyện vào từng trang Facebook Fanpage, Instagram, Telegram hoặc LiveChat Website để tự động trực page và trả lời khách 24/7."
                            />
                        </div>
                        <Badge variant="outline" class="text-xs self-start sm:self-auto py-1 px-3">
                            {{ channelsList.filter(c => c.ai_enabled).length }} / {{ channelsList.length }} Kênh đang bật AI
                        </Badge>
                    </div>

                    <!-- Empty state -->
                    <div v-if="channelsList.length === 0" class="text-center py-12 text-muted-foreground">
                        <IconBrandFacebook class="size-12 mx-auto stroke-1 mb-2 opacity-50" />
                        <p class="font-medium text-foreground">Chưa có Trang / Kênh nào được kết nối</p>
                        <p class="text-xs mt-1">
                            Vui lòng kết nối Trang Facebook hoặc Kênh Telegram ở phần Kênh kết nối trước.
                        </p>
                    </div>

                    <!-- Channel Cards / Table -->
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div
                            v-for="channel in channelsList"
                            :key="channel.id"
                            :class="[
                                'relative p-5 rounded-xl border transition-all flex flex-col justify-between gap-4',
                                channel.ai_enabled
                                    ? 'border-indigo-500/40 bg-indigo-500/5 shadow-2xs'
                                    : 'border-border bg-card hover:border-border/80'
                            ]"
                        >
                            <!-- Header channel info -->
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        :class="[
                                            'p-2.5 rounded-xl flex items-center justify-center shrink-0',
                                            channel.provider === 'facebook'
                                                ? 'bg-blue-600/10 text-blue-600 dark:text-blue-400'
                                                : channel.provider === 'instagram'
                                                ? 'bg-pink-600/10 text-pink-600 dark:text-pink-400'
                                                : channel.provider === 'telegram'
                                                ? 'bg-sky-500/10 text-sky-500 dark:text-sky-400'
                                                : 'bg-emerald-500/10 text-emerald-500 dark:text-emerald-400'
                                        ]"
                                    >
                                        <IconBrandFacebook v-if="channel.provider === 'facebook'" class="size-6" />
                                        <IconBrandInstagram v-else-if="channel.provider === 'instagram'" class="size-6" />
                                        <IconBrandTelegram v-else-if="channel.provider === 'telegram'" class="size-6" />
                                        <IconWorld v-else class="size-6" />
                                    </div>
                                    <div>
                                        <div class="font-semibold text-foreground text-sm flex items-center gap-2">
                                            <span>{{ channel.name }}</span>
                                            <Badge
                                                :variant="channel.ai_enabled ? 'default' : 'secondary'"
                                                :class="[
                                                    'text-[10px] uppercase font-bold tracking-wider px-2 py-0.5',
                                                    channel.ai_enabled ? 'bg-emerald-600 hover:bg-emerald-600 text-white' : ''
                                                ]"
                                            >
                                                {{ channel.ai_enabled ? 'AI Đang trực' : 'Tắt AI' }}
                                            </Badge>
                                        </div>
                                        <div class="text-xs text-muted-foreground mt-0.5 capitalize">
                                            Nền tảng: {{ channel.provider }} • {{ channel.type === 'social_account' ? 'Mạng xã hội' : 'Kênh tích hợp' }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Toggle switch button -->
                                <Button
                                    size="sm"
                                    :variant="channel.ai_enabled ? 'destructive' : 'default'"
                                    :disabled="updatingChannelId === channel.id"
                                    class="text-xs shrink-0"
                                    @click="toggleChannelAi(channel)"
                                >
                                    <span v-if="updatingChannelId === channel.id" class="animate-pulse">Đang lưu...</span>
                                    <span v-else>{{ channel.ai_enabled ? 'Tắt AI trực page' : 'Bật AI trực page' }}</span>
                                </Button>
                            </div>

                            <!-- Bot assignment dropdown -->
                            <div class="bg-background/80 rounded-lg p-3.5 border border-border/80 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-medium text-muted-foreground">Bot AI được chỉ định:</span>
                                    <span v-if="channel.bot_id" class="text-indigo-600 dark:text-indigo-400 font-medium">
                                        {{ botsList.find(b => b.id === channel.bot_id)?.name || 'Bot tùy chỉnh' }}
                                    </span>
                                    <span v-else class="text-muted-foreground italic">Bot mặc định hệ thống</span>
                                </div>

                                <div class="flex gap-2">
                                    <select
                                        :value="channel.bot_id || ''"
                                        :disabled="updatingChannelId === channel.id"
                                        class="w-full text-xs bg-background border border-input rounded-md px-2.5 py-1.5 focus:outline-hidden focus:ring-1 focus:ring-indigo-500"
                                        @change="assignBotToChannel(channel, ($event.target as HTMLSelectElement).value || null)"
                                    >
                                        <option value="">-- Dùng Bot Mặc Định (Default Bot) --</option>
                                        <option
                                            v-for="bot in botsList"
                                            :key="bot.id"
                                            :value="bot.id"
                                        >
                                            {{ bot.name }} {{ bot.is_default ? '(Mặc định)' : '' }} - [{{ bot.persona_tone }}]
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Guidance notice -->
                    <div class="p-4 bg-muted/30 border border-border rounded-lg text-xs text-muted-foreground space-y-1">
                        <p class="font-medium text-foreground">💡 Lưu ý quan trọng khi bật AI Trực Page:</p>
                        <p>• Khi bật, tin nhắn của khách hàng nhắn đến Fanpage sẽ được kích hoạt tiến trình xử lý tự động qua Bot AI tương ứng.</p>
                        <p>• AI sẽ căn cứ vào hệ thống Tri thức (RAG), Bảng giá sản phẩm và Kịch bản chốt sale bạn đã cấu hình ở các Tab trên.</p>
                        <p>• Bất kỳ lúc nào nhân viên sales gửi tin nhắn thủ công cho khách, hệ thống sẽ tạm hoãn AI để nhân viên tiếp quản cuộc hội thoại.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
