<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import {
    store as campaignStore,
    testSend as campaignTestSend,
    update as campaignUpdate,
} from '@/routes/app/email-campaigns';

interface ContactList {
    id: string;
    name: string;
    contacts_count?: number;
}

interface Campaign {
    id: string;
    name: string;
    subject: string;
    preview_text: string | null;
    body_html: string;
    from_name: string | null;
    reply_to: string | null;
    email_contact_list_id: string | null;
    delay_seconds: number;
    scheduled_at: string | null;
}

interface Props {
    contactLists: ContactList[];
    campaign?: Campaign | null;
}

const props = withDefaults(defineProps<Props>(), { campaign: null });
const open = defineModel<boolean>('open', { default: false });
const htmlFileInput = ref<HTMLInputElement | null>(null);
const htmlFileName = ref('');

const isEditing = computed(() => props.campaign !== null);

// Built as strings so the Vue compiler does not read them as interpolation.
const open2 = '{' + '{';
const close2 = '}' + '}';
const nameVariable = `${open2} name ${close2}`;
const emailVariable = `${open2} email ${close2}`;
const fieldVariable = `${open2} fields.ten_cot ${close2}`;
const subjectPlaceholder = `Chào ${nameVariable}, ưu đãi dành riêng cho bạn`;
const bodyPlaceholder = `<p>Xin chào ${nameVariable},</p>`;
const showPreview = ref(false);
const testSending = ref(false);
const testMailError = ref('');
const welcomeTemplate = [
    '<div style="margin:0;padding:32px 16px;background-color:#f3f4f6;font-family:Arial,sans-serif;color:#1f2937;">',
    '<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;">',
    '<div style="padding:28px 32px;background:#111827;color:#ffffff;text-align:center;">',
    '<h1 style="margin:0;font-size:24px;">Chào mừng bạn đến với dịch vụ của chúng tôi</h1>',
    '</div>',
    '<div style="padding:32px;line-height:1.7;">',
    `<p>Xin chào ${nameVariable},</p>`,
    '<p>Cảm ơn bạn đã đăng ký. Chúng tôi rất vui được đồng hành và hỗ trợ bạn.</p>',
    '<p>Bạn có thể trả lời email này nếu cần trợ giúp.</p>',
    '<p>Trân trọng,<br><strong>Đội ngũ chăm sóc khách hàng</strong></p>',
    '</div>',
    '<div style="padding:16px 32px;background:#f9fafb;color:#6b7280;font-size:12px;text-align:center;">',
    'Bạn nhận được email này vì đã đăng ký dịch vụ của chúng tôi.',
    '</div>',
    '</div>',
    '</div>',
].join('');

const form = useForm({
    name: '',
    subject: '',
    preview_text: '',
    body_html: '',
    from_name: '',
    reply_to: '',
    email_contact_list_id: '',
    delay_seconds: 2,
    scheduled_at: '',
});

const fillFromCampaign = () => {
    if (props.campaign) {
        form.name = props.campaign.name;
        form.subject = props.campaign.subject;
        form.preview_text = props.campaign.preview_text ?? '';
        form.body_html = props.campaign.body_html;
        form.from_name = props.campaign.from_name ?? '';
        form.reply_to = props.campaign.reply_to ?? '';
        form.email_contact_list_id = props.campaign.email_contact_list_id ?? '';
        form.delay_seconds = props.campaign.delay_seconds;
        form.scheduled_at = props.campaign.scheduled_at
            ? props.campaign.scheduled_at.slice(0, 16)
            : '';

        return;
    }

    form.reset();
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;

            if (!isEditing.value) {
                form.reset();
            }
        },
    };

    if (props.campaign) {
        form.put(campaignUpdate.url(props.campaign.id), options);

        return;
    }

    form.post(campaignStore.url(), options);
};

const useWelcomeTemplate = () => {
    form.body_html = welcomeTemplate;
    htmlFileName.value = '';
};

const importHtmlFile = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    form.body_html = await file.text();
    htmlFileName.value = file.name;
    showPreview.value = true;
    input.value = '';
};

const sendTestEmail = () => {
    testMailError.value = '';

    router.post(
        campaignTestSend.url(),
        {
            subject: form.subject,
            preview_text: form.preview_text,
            body_html: form.body_html,
            from_name: form.from_name,
            reply_to: form.reply_to,
        },
        {
            preserveScroll: true,
            onStart: () => {
                testSending.value = true;
            },
            onFinish: () => {
                testSending.value = false;
            },
            onError: (errors) => {
                testMailError.value =
                    errors.mail ??
                    'Không gửi được email thử. Vui lòng kiểm tra nội dung và cấu hình SMTP.';
            },
        },
    );
};

const handleOpenChange = (value: boolean) => {
    if (value) {
        form.clearErrors();
        fillFromCampaign();
    }

    open.value = value;
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogScrollContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Sửa chiến dịch' : 'Tạo chiến dịch email' }}
                </DialogTitle>
                <DialogDescription>
                    Soạn nội dung và chọn danh sách người nhận. Chiến dịch được
                    lưu ở trạng thái nháp cho tới khi bạn bấm gửi.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="campaign-name">Tên chiến dịch</Label>
                    <Input
                        id="campaign-name"
                        v-model="form.name"
                        placeholder="Ví dụ: Remarketing khách chưa mua tháng 10"
                        :class="{ 'border-destructive': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="campaign-list">Danh sách người nhận</Label>
                    <NativeSelect
                        id="campaign-list"
                        v-model="form.email_contact_list_id"
                        :class="{
                            'border-destructive':
                                form.errors.email_contact_list_id,
                        }"
                    >
                        <NativeSelectOption value="">
                            — Chọn danh sách —
                        </NativeSelectOption>
                        <NativeSelectOption
                            v-for="list in contactLists"
                            :key="list.id"
                            :value="list.id"
                        >
                            {{ list.name }}
                            <template v-if="list.contacts_count !== undefined">
                                ({{ list.contacts_count }} email)
                            </template>
                        </NativeSelectOption>
                    </NativeSelect>
                    <p
                        v-if="form.errors.email_contact_list_id"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.email_contact_list_id }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="campaign-subject">Tiêu đề email</Label>
                    <Input
                        id="campaign-subject"
                        v-model="form.subject"
                        :placeholder="subjectPlaceholder"
                        :class="{ 'border-destructive': form.errors.subject }"
                    />
                    <p
                        v-if="form.errors.subject"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.subject }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="campaign-preview">Dòng xem trước</Label>
                    <Input
                        id="campaign-preview"
                        v-model="form.preview_text"
                        placeholder="Đoạn chữ hiển thị cạnh tiêu đề trong hộp thư"
                    />
                </div>

                <div class="grid gap-2">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <Label for="campaign-body">Nội dung (HTML)</Label>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="
                                    testSending ||
                                    form.processing ||
                                    !form.subject ||
                                    !form.body_html
                                "
                                @click="sendTestEmail"
                            >
                                {{ testSending ? 'Đang gửi thử…' : 'Gửi thử' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="htmlFileInput?.click()"
                            >
                                Nạp file HTML
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="useWelcomeTemplate"
                            >
                                Dùng mẫu chào mừng
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="!form.body_html"
                                @click="showPreview = !showPreview"
                            >
                                {{ showPreview ? 'Ẩn xem trước' : 'Xem trước' }}
                            </Button>
                        </div>
                    </div>
                    <input
                        ref="htmlFileInput"
                        type="file"
                        accept=".html,.htm,text/html"
                        class="hidden"
                        @change="importHtmlFile"
                    />
                    <p
                        v-if="htmlFileName"
                        class="text-sm text-muted-foreground"
                    >
                        Đã nạp: {{ htmlFileName }}
                    </p>
                    <Textarea
                        id="campaign-body"
                        v-model="form.body_html"
                        rows="10"
                        class="font-mono text-sm"
                        :placeholder="bodyPlaceholder"
                        :class="{ 'border-destructive': form.errors.body_html }"
                    />
                    <p class="text-sm text-muted-foreground">
                        Biến dùng được: <code>{{ nameVariable }}</code
                        >, <code>{{ emailVariable }}</code> và
                        <code>{{ fieldVariable }}</code> theo các cột khác trong
                        file đã nhập.
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Có thể nhúng ảnh bằng thẻ
                        <code>&lt;img src="https://..."&gt;</code> với URL ảnh
                        công khai.
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Email thử sẽ được gửi đến địa chỉ email tài khoản đang
                        đăng nhập.
                    </p>
                    <p v-if="testMailError" class="text-sm text-destructive">
                        {{ testMailError }}
                    </p>
                    <iframe
                        v-if="showPreview && form.body_html"
                        :srcdoc="form.body_html"
                        sandbox
                        title="Xem trước nội dung email"
                        class="h-80 w-full rounded-md border bg-white"
                    />
                    <p
                        v-if="form.errors.body_html"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.body_html }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="campaign-from">Tên người gửi</Label>
                        <Input
                            id="campaign-from"
                            v-model="form.from_name"
                            placeholder="Tên thương hiệu"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="campaign-reply">Email nhận phản hồi</Label>
                        <Input
                            id="campaign-reply"
                            v-model="form.reply_to"
                            placeholder="sales@congty.com"
                            :class="{
                                'border-destructive': form.errors.reply_to,
                            }"
                        />
                        <p
                            v-if="form.errors.reply_to"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.reply_to }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="campaign-delay">
                            Giãn cách giữa các email (giây)
                        </Label>
                        <Input
                            id="campaign-delay"
                            v-model.number="form.delay_seconds"
                            type="number"
                            min="1"
                            :class="{
                                'border-destructive': form.errors.delay_seconds,
                            }"
                        />
                        <p class="text-sm text-muted-foreground">
                            Giãn cách lớn hơn giúp giảm rủi ro bị đánh dấu spam.
                        </p>
                        <p
                            v-if="form.errors.delay_seconds"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.delay_seconds }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="campaign-scheduled">
                            Hẹn giờ gửi (để trống = gửi thủ công)
                        </Label>
                        <Input
                            id="campaign-scheduled"
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            :class="{
                                'border-destructive': form.errors.scheduled_at,
                            }"
                        />
                        <p
                            v-if="form.errors.scheduled_at"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.scheduled_at }}
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Đang lưu…' : 'Lưu chiến dịch' }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Huỷ
                    </Button>
                </DialogFooter>
            </form>
        </DialogScrollContent>
    </Dialog>
</template>
