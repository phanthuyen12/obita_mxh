---
id: visual-builder
title: Trình Dựng Quy Trình Tự Động Kéo Thả
sidebar_label: Visual Automations
---

# Trình Dựng Quy Trình Tự Động Kéo Thả (Visual Automations)

Xây dựng các luồng tự động hóa marketing không cần lập trình thông qua giao diện dạng Node trực quan.

```mermaid
graph LR
    T[Trigger Node<br>RSS Feed / Webhook] --> C[Condition Node<br>Lọc điều kiện]
    C --> G[Generate Node<br>AI sinh bài]
    G --> P[Publish Node<br>Xuất bản lên MXH]
```

- **Trigger Node:** Kích hoạt theo lịch biểu, Webhook hoặc RSS Feed bài viết mới.
- **Condition Node:** Rẽ nhánh luồng xử lý theo từ khóa hoặc chuyên mục.
- **Generate Node:** Sử dụng AI để tóm tắt và tạo bài đăng mạng xã hội.
- **Publish Node:** Đăng bài tự động lên các kênh được chọn.
