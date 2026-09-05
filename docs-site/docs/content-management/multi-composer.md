---
id: multi-composer
title: Trình Soạn Thảo Đa Nền Tảng & Live Preview
sidebar_label: Soạn thảo đa nền tảng
---

# Trình Soạn Thảo Đa Nền Tảng (Multi-Composer)

Trình soạn thảo Multi-Composer cho phép sáng tạo một nội dung gốc và tùy biến giao diện riêng biệt cho từng mạng xã hội trên cùng một màn hình.

---

## Cấu Trúc Trình Soạn Thảo

```text
┌───────────────────────────────────────────────────────────┬───────────────────────────────┐
│ THANH CHỌN KÊNH: [Facebook] [Instagram] [TikTok] [X]...   │                               │
├───────────────────────────────────────────────────────────┤    KHUNG XEM TRƯỚC THỰC TẾ    │
│ VÙNG SOẠN THẢO VĂN BẢN:                                   │        (LIVE PREVIEW)         │
│                                                           │                               │
│ [ Nhập nội dung bài viết gốc...                         ] │  ┌─────────────────────────┐  │
│                                                           │  │ [Avatar] Tên Kênh       │  │
│                                                           │  │ Nội dung hiển thị đúng  │  │
│ [📷 Tải Media]  [✂️ Cắt Ảnh]  [✒️ Chèn Chữ Ký]  [🏷️ Nhãn]    │  │ format của từng mạng XH │  │
│ [🤖 Trợ Lý AI]  [😀 Emoji]    [@ Gắn Thẻ]      [🔗 Preview]│  │                         │  │
├───────────────────────────────────────────────────────────┤  │ [Hình ảnh / Video]      │  │
│ [Lưu Nháp]        [Gửi Duyệt Bài]       [Xuất Bản / Hẹn Giờ]│  └─────────────────────────┘  │
└───────────────────────────────────────────────────────────┴───────────────────────────────┘
```

---

## Các Tiện Ích Hỗ Trợ Soạn Thảo

1. **Tùy biến theo Tab kênh:** Nhấp vào từng tab mạng xã hội để chỉnh sửa nội dung riêng (ví dụ: bổ sung hashtag riêng cho Instagram, rút ngắn ký tự cho Twitter).
2. **Cắt ảnh chuẩn tỷ lệ (Image Cropper):**
   - `1:1`: Chuẩn vuông cho Facebook/Instagram Feed.
   - `4:5`: Chuẩn dọc cho Instagram Feed giúp tối ưu diện tích hiển thị trên điện thoại.
   - `16:9`: Chuẩn ngang cho bài viết kèm link, LinkedIn và Facebook.
   - `9:16`: Chuẩn dọc cho TikTok, Reels, Shorts và Stories.
3. **Đếm ký tự thời gian thực:** Cảnh báo giới hạn ký tự tự động theo tiêu chuẩn của từng nền tảng.
