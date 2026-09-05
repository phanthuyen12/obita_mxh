<script setup lang="ts">
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
import {
    IconAddressBook,
    IconFilter,
    IconPhone,
    IconPlus,
    IconSearch,
    IconTag,
    IconTrash,
} from '@tabler/icons-vue';
import { reactive, ref } from 'vue';

import {
    index as leadsIndex,
    update as updateLead,
} from '@/actions/App/Http/Controllers/App/Omnichat/LeadController';
import {
    destroy as destroyTag,
    store as storeTag,
} from '@/actions/App/Http/Controllers/App/Omnichat/TagController';
import LabelBadge from '@/components/labels/LabelBadge.vue';
import ProviderIcon from '@/components/omnichat/shared/ProviderIcon.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as inbox } from '@/routes/app/omnichat';

type Tag = { id: string; name: string; color: string };
type Lead = {
    id: string;
    display_name: string;
    avatar_url: string | null;
    phone: string;
    email: string | null;
    lead_stage: string;
    phone_detected_at: string | null;
    conversation_count: number;
    latest_conversation_id: string | null;
    provider: string | null;
    tags: Tag[];
};
type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    leads: { data: Lead[]; links: PaginationLink[]; total: number };
    tags: Tag[];
    providers: Array<{ value: string; label: string }>;
    filters: { search: string; stage: string; provider: string; tagId: string };
}>();

const filters = reactive({ ...props.filters });
const newTagName = ref('');
const newTagColor = ref('#64748B');
const tagRequest = useHttp<{ name: string; color: string }, { tag: Tag }>({
    name: '',
    color: '#64748B',
});
const stageRequest = useHttp<
    { lead_stage: string },
    { lead: Pick<Lead, 'id' | 'lead_stage'> }
>({
    lead_stage: 'new',
});

const applyFilters = () => {
    router.get(
        leadsIndex.url(),
        {
            search: filters.search || undefined,
            stage: filters.stage || undefined,
            provider: filters.provider || undefined,
            tag: filters.tagId || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const resetFilters = () => {
    Object.assign(filters, { search: '', stage: '', provider: '', tagId: '' });
    applyFilters();
};

const createTag = async () => {
    const name = newTagName.value.trim();
    if (!name || tagRequest.processing) return;

    tagRequest.name = name;
    tagRequest.color = newTagColor.value;
    await tagRequest.post(storeTag.url());
    newTagName.value = '';
    router.reload({ only: ['tags'] });
};

const removeTag = async (tag: Tag) => {
    if (!window.confirm(`Xóa thẻ “${tag.name}” khỏi tất cả hội thoại?`)) return;
    await tagRequest.delete(destroyTag.url(tag.id));
    router.reload({ only: ['tags', 'leads'] });
};

const changeStage = async (lead: Lead, event: Event) => {
    const stage = (event.target as HTMLSelectElement).value;
    stageRequest.lead_stage = stage;
    await stageRequest.patch(updateLead.url(lead.id));
    lead.lead_stage = stage;
};

const formatDate = (value: string | null): string =>
    value
        ? new Intl.DateTimeFormat('vi-VN', {
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';

const stages = [
    { value: 'new', label: 'Mới' },
    { value: 'qualified', label: 'Đủ điều kiện' },
    { value: 'contacted', label: 'Đã liên hệ' },
    { value: 'converted', label: 'Đã chuyển đổi' },
    { value: 'lost', label: 'Không tiềm năng' },
];
</script>

<template>
    <Head title="Khách hàng tiềm năng" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 sm:p-6">
            <div
                class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
            >
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold">
                        <IconAddressBook class="size-6 text-primary" />
                        Khách hàng tiềm năng
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Tự động thu thập khách để lại số điện thoại trong hội
                        thoại.
                    </p>
                </div>
                <Badge variant="secondary" class="w-fit"
                    >{{ leads.total }} khách hàng</Badge
                >
            </div>

            <section class="rounded-xl border border-border bg-card p-4">
                <div class="mb-3 flex items-center gap-2">
                    <IconFilter class="size-4" />
                    <h2 class="text-sm font-semibold">Bộ lọc</h2>
                </div>
                <div class="grid gap-3 md:grid-cols-5">
                    <div class="relative md:col-span-2">
                        <IconSearch
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="filters.search"
                            class="pl-9"
                            placeholder="Tên, số điện thoại, email"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <select
                        v-model="filters.stage"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Tất cả trạng thái</option>
                        <option
                            v-for="stage in stages"
                            :key="stage.value"
                            :value="stage.value"
                        >
                            {{ stage.label }}
                        </option>
                    </select>
                    <select
                        v-model="filters.provider"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Tất cả nền tảng</option>
                        <option
                            v-for="provider in providers"
                            :key="provider.value"
                            :value="provider.value"
                        >
                            {{ provider.label }}
                        </option>
                    </select>
                    <select
                        v-model="filters.tagId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Tất cả thẻ</option>
                        <option
                            v-for="tag in tags"
                            :key="tag.id"
                            :value="tag.id"
                        >
                            {{ tag.name }}
                        </option>
                    </select>
                </div>
                <div class="mt-3 flex gap-2">
                    <Button size="sm" @click="applyFilters">Áp dụng</Button>
                    <Button size="sm" variant="ghost" @click="resetFilters"
                        >Xóa lọc</Button
                    >
                </div>
            </section>

            <section class="rounded-xl border border-border bg-card p-4">
                <div class="mb-3 flex items-center gap-2">
                    <IconTag class="size-4" />
                    <h2 class="text-sm font-semibold">Quản lý thẻ hội thoại</h2>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Input
                        v-model="newTagName"
                        maxlength="80"
                        placeholder="Tên thẻ mới"
                        class="sm:max-w-xs"
                        @keyup.enter="createTag"
                    />
                    <input
                        v-model="newTagColor"
                        type="color"
                        class="h-9 w-14 cursor-pointer rounded-md border border-input bg-background p-1"
                    />
                    <Button
                        size="sm"
                        :disabled="tagRequest.processing || !newTagName.trim()"
                        @click="createTag"
                    >
                        <IconPlus class="size-4" /> Thêm thẻ
                    </Button>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <div
                        v-for="tag in tags"
                        :key="tag.id"
                        class="flex items-center gap-1 rounded-full border border-border py-1 pr-1 pl-2"
                    >
                        <LabelBadge :label="tag" />
                        <button
                            class="rounded-full p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            @click="removeTag(tag)"
                        >
                            <IconTrash class="size-3" />
                        </button>
                    </div>
                    <p
                        v-if="tags.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Chưa có thẻ.
                    </p>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-xl border border-border bg-card"
            >
                <div
                    v-if="leads.data.length === 0"
                    class="px-6 py-16 text-center"
                >
                    <IconPhone
                        class="mx-auto size-10 text-muted-foreground/50"
                    />
                    <p class="mt-3 font-medium">
                        Chưa tìm thấy khách hàng tiềm năng
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Khách sẽ xuất hiện khi họ gửi số điện thoại trong chat.
                    </p>
                </div>

                <div v-else class="divide-y divide-border">
                    <div
                        v-for="lead in leads.data"
                        :key="lead.id"
                        class="grid gap-4 p-4 hover:bg-muted/30 md:grid-cols-[minmax(220px,1.4fr)_minmax(170px,1fr)_160px_minmax(180px,1fr)_110px] md:items-center"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <Avatar
                                :src="lead.avatar_url"
                                :name="lead.display_name"
                                class="size-10 shrink-0"
                            />
                            <div class="min-w-0">
                                <p class="truncate font-medium">
                                    {{ lead.display_name }}
                                </p>
                                <p
                                    class="flex items-center gap-1 text-sm text-muted-foreground"
                                >
                                    <IconPhone class="size-3.5" />
                                    {{ lead.phone }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <ProviderIcon
                                v-if="lead.provider"
                                :provider="lead.provider"
                                class="size-4"
                            />
                            <span class="capitalize">{{
                                lead.provider || '—'
                            }}</span>
                            <span class="text-muted-foreground"
                                >· {{ lead.conversation_count }} chat</span
                            >
                        </div>
                        <select
                            :value="lead.lead_stage"
                            class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                            @change="changeStage(lead, $event)"
                        >
                            <option
                                v-for="stage in stages"
                                :key="stage.value"
                                :value="stage.value"
                            >
                                {{ stage.label }}
                            </option>
                        </select>
                        <div class="flex flex-wrap gap-1">
                            <LabelBadge
                                v-for="tag in lead.tags.slice(0, 3)"
                                :key="tag.id"
                                :label="tag"
                            />
                            <span
                                v-if="lead.tags.length === 0"
                                class="text-xs text-muted-foreground"
                                >Chưa gắn thẻ</span
                            >
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-muted-foreground">
                                {{ formatDate(lead.phone_detected_at) }}
                            </p>
                            <Button
                                v-if="lead.latest_conversation_id"
                                as-child
                                variant="link"
                                size="sm"
                                class="h-7 px-0"
                            >
                                <Link
                                    :href="
                                        inbox({
                                            query: {
                                                conversation:
                                                    lead.latest_conversation_id,
                                            },
                                        }).url
                                    "
                                    >Mở chat</Link
                                >
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="leads.links.length > 3"
                    class="flex flex-wrap justify-center gap-1 border-t border-border p-3"
                >
                    <Button
                        v-for="link in leads.links"
                        :key="link.label"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        :disabled="!link.url"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            v-html="link.label"
                        />
                        <span v-else v-html="link.label" />
                    </Button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
