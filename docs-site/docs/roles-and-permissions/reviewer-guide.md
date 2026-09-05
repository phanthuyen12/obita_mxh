---
id: reviewer-guide
title: Hướng Dẫn Dành Cho Người Kiểm Duyệt (Reviewer)
sidebar_label: Người kiểm duyệt (Reviewer)
---

# Hướng Dành Cho Người Kiểm Duyệt (Reviewer)

Người kiểm duyệt (Reviewer / Content Leader / Đại diện Khách hàng) đóng vai trò thẩm định chất lượng nội dung, đảm bảo bài viết chuẩn xác về mặt thông điệp, hình ảnh và thời gian xuất bản trước khi ra công chúng.

```mermaid
sequenceDiagram
    actor Editor as Chuyên viên Nội dung
    participant System as Hệ thống King Hub
    actor Reviewer as Người Kiểm Duyệt (Leader)
    
    Editor->>System: Soạn bài & Gửi duyệt (Submit for Review)
    System-->>Reviewer: Thông báo In-app & Email có bài mới
    Reviewer->>System: Mở bài viết & Đối soát khung Live Preview
    alt Nội dung đạt yêu cầu
        Reviewer->>System: Nhấn "Phê duyệt" (Approve)
        System-->>Editor: Thông báo bài đã được lên lịch tự động
    else Cần chỉnh sửa
        Reviewer->>System: Nhấn "Từ chối" (Reject) + Nhập lý do phản hồi
        System-->>Editor: Trả về trạng thái Nháp kèm góp ý chi tiết
    end
```

---

## Hướng Dẫn Thao Tác Kiểm Duyệt

### Bước 1: Tiếp Nhận Bài Viết Cần Duyệt
- Truy cập danh sách **Bài viết > Chờ duyệt (Pending)** hoặc nhấp vào thông báo nhận được trên thanh tiêu đề.

### Bước 2: Đánh Giá Nội Dung & Kiểm Tra Định Dạng
1. Đọc kỹ văn bản, kiểm tra tính chuẩn xác của các liên kết và hashtag.
2. Kiểm tra khung **Live Preview** của từng nền tảng để đảm bảo hình ảnh không bị cắt sai tỷ lệ hoặc nội dung vượt quá giới hạn ký tự.
3. Sử dụng tab **Bình luận (Comments)** để tag `@tên_nhân_viên` trao đổi nội bộ nếu có điểm cần làm rõ.

### Bước 3: Đưa Ra Quyết Định
- **Phê duyệt (Approve):** Nhấp nút **Phê duyệt**. Bài viết sẽ chuyển thẳng sang trạng thái **Scheduled** (Đã lên lịch) hoặc xuất bản ngay nếu là bài đăng tức thì.
- **Từ chối (Reject):** Nhấp nút **Từ chối** -> Điền rõ lý do trong hộp thoại (ví dụ: *"Sửa lại giá bán, thay hình ảnh theo mẫu số 2"*) -> Bấm **Xác nhận**.
