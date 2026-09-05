---
id: rss-webhook
title: Tự Động Hóa Xuất Bản Từ RSS & Webhook
sidebar_label: Tự động hóa RSS & Webhook
---

# Tự Động Hóa Xuất Bản Từ RSS & Webhook

Giải pháp tự động chuyển thể bài viết từ Blog/Website tin tức thành bài đăng mạng xã hội.

---

## Hướng Dẫn Thiết Lập:
1. Vào mục **Automations** (`/automations`) -> Chọn **Tạo quy trình mới**.
2. Thêm **RSS Trigger Node** và nhập URL nguồn tin (ví dụ: `https://yourwebsite.com/feed`).
3. Nối tiếp với **AI Generate Node** với câu lệnh tóm tắt bài viết và tạo hashtag.
4. Nối tiếp với **Publish Node** chọn các Fanpage tiếp nhận bài.
5. Nhấn **Kích hoạt (Activate)**.
