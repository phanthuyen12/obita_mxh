---
id: tags-and-routing
title: Phân Loại Thẻ & Điều Phối Hội Thoại
sidebar_label: Gắn thẻ & Phân công CSKH
---

# Gắn Thẻ Phân Loại & Phân Công Nhân Viên CSKH

Quy trình điều phối hội thoại chuyên nghiệp trong môi trường nhóm CSKH.

```mermaid
sequenceDiagram
    actor Cust as Khách Hàng
    participant Inbox as Omnichat Inbox
    actor Lead as Trưởng Nhóm CSKH
    actor Agent as Nhân Viên CSKH
    
    Cust->>Inbox: Gửi tin nhắn mới
    Inbox-->>Lead: Hội thoại hiển thị ở mục "Chưa phân công"
    Lead->>Inbox: Phân công hội thoại cho Nhân Viên
    Inbox-->>Agent: Thông báo hội thoại mới trong mục "Của tôi"
    Agent->>Cust: Tiếp nhận tư vấn & Gắn thẻ phân loại
```

---

## 1. Gắn Thẻ Hội Thoại (Tags)
- Tạo các thẻ phân loại màu sắc: *Khách mới, Đang tư vấn, Đã chốt đơn, Khiếu nại*.
- Hỗ trợ gắn nhiều thẻ trên cùng một cuộc trò chuyện để dễ dàng lọc và quản lý.

## 2. Phân Công Nhân Sự (Agent Assignment)
- Trưởng nhóm chỉ định nhân viên phụ trách cuộc trò chuyện để tránh trùng lặp.
- Nhân viên có thể lọc xem riêng mục **Hội thoại của tôi**.
