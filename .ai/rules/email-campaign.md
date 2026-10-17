---
paths:
  - 'app/{Services/Mail,Services/EmailCampaign,Jobs/EmailCampaign,Mail,Http/Controllers/App/EmailCampaign}/**'
---

# Email Campaign

## Email marketing gửi qua SMTP riêng của workspace, không fallback mailer hệ thống
Module chiến dịch email (`app/Services/EmailCampaign/**`, `app/Jobs/EmailCampaign/**`) BẮT BUỘC gửi qua `App\Services\Mail\WorkspaceMailerResolver`, đọc bảng `workspace_mail_settings` của chính workspace đó.

Workspace chưa cấu hình SMTP thì resolver ném `WorkspaceMailNotConfiguredException` — **không bao giờ fallback về mailer mặc định** (`config('mail.default')`, hiện là SendKit). Lý do: mail marketing bị báo spam sẽ làm hỏng uy tín domain gửi mail giao dịch (đăng ký, quên mật khẩu) của toàn bộ hệ thống.

Hệ quả cần nhớ: vì không đi qua SendKit, webhook `POST /webhook/sendkit` sẽ KHÔNG bắn event cho các workspace dùng SMTP riêng, nên recipient dừng ở trạng thái `sent`, không có `delivered`/`opened`/`bounced`. Listener `App\Listeners\SendKit\RecordEmailCampaignEvent` chỉ hữu dụng khi workspace dùng mailer SendKit.
