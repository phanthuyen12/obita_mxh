<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import { update as updateConversationTags } from '@/actions/App/Http/Controllers/App/Omnichat/ConversationTagController';
import { update as updateLead } from '@/actions/App/Http/Controllers/App/Omnichat/LeadController';
import { store as storeTag } from '@/actions/App/Http/Controllers/App/Omnichat/TagController';
import InputError from '@/components/InputError.vue';
import ProviderIcon from '@/components/omnichat/shared/ProviderIcon.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Textarea } from '@/components/ui/textarea';
import {
    IconCalendar,
    IconChevronUp,
    IconClock,
    IconMail,
    IconMessageCircle,
    IconPhone,
    IconPlus,
    IconTag,
    IconUser,
} from '@tabler/icons-vue';

type Props = {
    conversation: {
        id: string;
        contact: {
            id: string;
            display_name: string;
            avatar_url: string | null;
            email: string | null;
            phone: string | null;
            notes: string | null;
            status: string;
            created_at: string;
            total_conversations: number;
            last_contact_at: string | null;
        };
        channel: { provider: string };
        labels: Array<{ id: string; name: string; color: string }>;
    } | null;
    availableTags: Array<{ id: string; name: string; color: string }>;
};

const props = defineProps<Props>();
const localTags = ref([...props.availableTags]);
const newTagName = ref('');
const newTagColor = ref('#64748B');
const profileRequest = useHttp<{
    display_name: string;
    email: string;
    phone: string;
    notes: string;
}>({ display_name: '', email: '', phone: '', notes: '' });
const selectedTagIds = ref<string[]>(
    props.conversation?.labels.map((tag) => tag.id) ?? [],
);
const tagRequest = useHttp<
    { tag_ids: string[] },
    { tags: Props['availableTags'] }
>({ tag_ids: [] });
const createTagRequest = useHttp<
    { name: string; color: string },
    { tag: Props['availableTags'][number] }
>({ name: '', color: '#64748B' });

watch(
    () => props.conversation,
    (conversation) => {
        localTags.value = [...props.availableTags];
        selectedTagIds.value = conversation?.labels.map((tag) => tag.id) ?? [];
        profileRequest.display_name = conversation?.contact.display_name ?? '';
        profileRequest.email = conversation?.contact.email ?? '';
        profileRequest.phone = conversation?.contact.phone ?? '';
        profileRequest.notes = conversation?.contact.notes ?? '';
        profileRequest.clearErrors();
    },
    { immediate: true },
);

watch(
    () => props.availableTags,
    (tags) => {
        localTags.value = [...tags];
    },
);

const saveProfile = async () => {
    if (!props.conversation || profileRequest.processing) return;

    await profileRequest.patch(updateLead.url(props.conversation.contact.id));
    router.reload({ only: ['conversations', 'selectedConversation'] });
};

const toggleTag = async (tagId: string) => {
    if (!props.conversation || tagRequest.processing) return;

    selectedTagIds.value = selectedTagIds.value.includes(tagId)
        ? selectedTagIds.value.filter((id) => id !== tagId)
        : [...selectedTagIds.value, tagId];
    tagRequest.tag_ids = selectedTagIds.value;
    await tagRequest.put(updateConversationTags.url(props.conversation.id));
    router.reload({ only: ['conversations', 'selectedConversation'] });
};

const createTag = async (): Promise<void> => {
    if (!props.conversation || createTagRequest.processing) return;

    const name = newTagName.value.trim();
    if (!name) return;

    createTagRequest.name = name;
    createTagRequest.color = newTagColor.value;

    const response = await createTagRequest.post(storeTag.url());
    const tag = response.tag;

    localTags.value = [...localTags.value, tag];
    newTagName.value = '';
    selectedTagIds.value = [...selectedTagIds.value, tag.id];
    tagRequest.tag_ids = selectedTagIds.value;
    await tagRequest.put(updateConversationTags.url(props.conversation.id));
    router.reload({
        only: ['conversations', 'selectedConversation', 'filterOptions'],
    });
};

const formatDateTime = (value: string | null): string => {
    if (!value) return 'Chưa có dữ liệu';
    return new Intl.DateTimeFormat('vi-VN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};
</script>

<template>
    <div
        v-if="conversation"
        class="flex h-full min-h-0 flex-col overflow-hidden border-l border-border bg-card"
    >
        <div
            class="flex h-16 shrink-0 items-center justify-between border-b border-border px-4"
        >
            <h2 class="text-sm font-semibold">Hồ sơ khách hàng</h2>
            <IconChevronUp class="size-4 text-muted-foreground" />
        </div>
        <ScrollArea class="min-h-0 flex-1">
            <div>
                <div class="border-b border-border px-4 py-5 text-center">
                    <Avatar
                        :src="conversation.contact.avatar_url"
                        :name="conversation.contact.display_name"
                        class="mx-auto size-16 ring-4 ring-muted"
                    />
                    <h3 class="mt-3 text-lg font-semibold">
                        {{ conversation.contact.display_name }}
                    </h3>
                    <Badge
                        variant="outline"
                        class="mt-2 h-6 rounded-full text-[11px]"
                        ><IconUser class="size-3" />
                        {{
                            conversation.contact.status === 'active'
                                ? 'Đang hoạt động'
                                : conversation.contact.status
                        }}</Badge
                    >
                </div>
                <div class="space-y-4 border-b border-border p-4">
                    <h4
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Thông tin khách hàng
                    </h4>
                    <div class="space-y-1.5">
                        <label
                            for="contact-name"
                            class="text-xs text-muted-foreground"
                            >Tên khách hàng</label
                        >
                        <Input
                            id="contact-name"
                            v-model="profileRequest.display_name"
                            maxlength="255"
                        />
                        <InputError
                            :message="profileRequest.errors.display_name"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label
                            for="contact-email"
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <IconMail class="size-3.5" /> Email
                        </label>
                        <Input
                            id="contact-email"
                            v-model="profileRequest.email"
                            type="email"
                            maxlength="255"
                            placeholder="Chưa cập nhật"
                        />
                        <InputError :message="profileRequest.errors.email" />
                    </div>
                    <div class="space-y-1.5">
                        <label
                            for="contact-phone"
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <IconPhone class="size-3.5" /> Số điện thoại
                        </label>
                        <Input
                            id="contact-phone"
                            v-model="profileRequest.phone"
                            type="tel"
                            maxlength="32"
                            placeholder="Chưa cập nhật"
                        />
                        <InputError :message="profileRequest.errors.phone" />
                    </div>
                    <div class="flex items-start gap-2 text-sm">
                        <IconCalendar
                            class="mt-0.5 size-4 text-muted-foreground"
                        />
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Khách hàng từ
                            </p>
                            <p>
                                {{
                                    formatDateTime(
                                        conversation.contact.created_at,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2 text-sm">
                        <IconMessageCircle
                            class="mt-0.5 size-4 text-muted-foreground"
                        />
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Tổng cuộc trò chuyện
                            </p>
                            <p>
                                {{ conversation.contact.total_conversations }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2 text-sm">
                        <IconClock
                            class="mt-0.5 size-4 text-muted-foreground"
                        />
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Liên hệ gần nhất
                            </p>
                            <p>
                                {{
                                    formatDateTime(
                                        conversation.contact.last_contact_at,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="space-y-3 border-b border-border p-4">
                    <h4
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Danh tính đã kết nối
                    </h4>
                    <div
                        class="flex items-center gap-2 rounded-lg border border-border bg-muted/40 p-2.5"
                    >
                        <ProviderIcon
                            :provider="conversation.channel.provider"
                            class="size-4 text-muted-foreground"
                        /><span class="text-sm font-medium capitalize">{{
                            conversation.channel.provider
                        }}</span
                        ><Badge variant="success" class="ml-auto h-5 text-xs"
                            >Đang hoạt động</Badge
                        >
                    </div>
                </div>
                <div class="space-y-3 border-b border-border p-4">
                    <div class="flex items-center gap-2">
                        <IconTag class="size-4 text-muted-foreground" />
                        <h4
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Thẻ hội thoại
                        </h4>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="ml-auto h-7 px-2 text-xs"
                            @click="newTagName = newTagName ? '' : ' '"
                        >
                            <IconPlus class="size-3.5" />
                            Tạo thẻ
                        </Button>
                    </div>
                    <div v-if="newTagName !== ''" class="flex gap-2">
                        <Input
                            v-model="newTagName"
                            maxlength="80"
                            placeholder="Tên thẻ mới"
                            class="h-8 text-xs"
                            @keyup.enter="createTag"
                        />
                        <input
                            v-model="newTagColor"
                            type="color"
                            class="h-8 w-10 shrink-0 cursor-pointer rounded-md border border-input bg-background p-1"
                        />
                        <Button
                            type="button"
                            size="sm"
                            class="h-8 shrink-0 px-2 text-xs"
                            :disabled="
                                createTagRequest.processing ||
                                !newTagName.trim()
                            "
                            @click="createTag"
                        >
                            Lưu
                        </Button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tag in localTags"
                            :key="tag.id"
                            type="button"
                            class="rounded-full border px-2.5 py-1 text-xs font-medium transition"
                            :class="
                                selectedTagIds.includes(tag.id)
                                    ? 'border-transparent text-white'
                                    : 'border-border text-muted-foreground hover:bg-muted'
                            "
                            :style="
                                selectedTagIds.includes(tag.id)
                                    ? { backgroundColor: tag.color }
                                    : undefined
                            "
                            @click="toggleTag(tag.id)"
                        >
                            {{ tag.name }}
                        </button>
                        <p
                            v-if="localTags.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Chưa có thẻ. Hãy tạo thẻ đầu tiên.
                        </p>
                    </div>
                </div>
                <div class="space-y-3 p-4">
                    <h4
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Ghi chú
                    </h4>
                    <Textarea
                        v-model="profileRequest.notes"
                        :rows="4"
                        maxlength="5000"
                        placeholder="Thêm ghi chú về khách hàng..."
                    />
                    <InputError :message="profileRequest.errors.notes" />
                    <Button
                        size="sm"
                        class="w-full"
                        :disabled="profileRequest.processing"
                        @click="saveProfile"
                    >
                        {{
                            profileRequest.processing
                                ? 'Đang lưu...'
                                : 'Lưu thông tin khách hàng'
                        }}
                    </Button>
                </div>
            </div>
        </ScrollArea>
    </div>
</template>
