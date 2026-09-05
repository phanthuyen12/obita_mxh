---
id: content-cloning
title: Chiến Dịch Nhân Bản Nội Dung (Content Clones)
sidebar_label: Chiến dịch Content Clones
---

# Chiến Dịch Nhân Bản Nội Dung (Content Clones)

Giải pháp nhân bản 1 bài viết chất lượng cao thành hàng chục bài viết biến thể bằng AI để phủ sóng toàn bộ hệ thống kênh vệ tinh.

```mermaid
flowchart TD
    Origin[1 Bài Viết Gốc Chất Lượng Cao] --> Config[Cấu Hình Chiến Dịch Clone]
    
    subgraph PARAMS["Tham Số Cấu Hình"]
        Config --> P1[Số lượng biến thể: 5, 10, 20...]
        Config --> P2[Phong cách: Chuyên gia / Kể chuyện / Ngắn gọn]
        Config --> P3[Chọn danh sách kênh vệ tinh mục tiêu]
    end
    
    PARAMS --> AI_Gen[AI Dify Engine Xử Lý Viết Lại Nội Dung]
    AI_Gen --> Preview[Xem Trước Toàn Bộ Biến Thể Batch Preview]
    Preview --> Schedule[Phân Bổ Lịch Đăng Tự Động Batch Scheduling]
```

---

## Các Bước Thực Hiện Chiến Dịch:

1. **Chọn bài viết gốc:** Truy cập **Content Clones** (`/content-clones`) -> Chọn bài viết có tương tác tốt nhất.
2. **Thiết lập số lượng & phong cách:** Chọn số lượng bài biến thể cần tạo và phong cách diễn đạt.
3. **Xem trước (Batch Preview):** Kiểm tra danh sách các bài viết do AI tạo ra và chỉnh sửa nếu cần.
4. **Lên lịch phân bổ (Batch Scheduling):** Chọn khung thời gian (ví dụ: *Đăng rải đều trong 7 ngày vào 09:00 và 15:00*). Hệ thống sẽ tự động xếp lịch vào các kênh vệ tinh tương ứng.
