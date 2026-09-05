---
id: approval-workflow
title: Quy Trình Gửi Duyệt & Phê Duyệt Bài Viết
sidebar_label: Quy trình duyệt bài
---

# Quy Trình Gửi Duyệt & Phê Duyệt Bài Viết

Đảm bảo kiểm soát chất lượng nội dung tuyệt đối trước khi phát hành.

```mermaid
stateDiagram-v2
    direction LR
    [*] --> Draft: Soạn thảo bài viết
    Draft --> Pending: Nhấn "Submit for Review"
    
    state Pending {
        [*] --> KiemTra: Leader / Reviewer xem xét
        KiemTra --> ThaoLuan: Bình luận & Tag @tên nội bộ
    }
    
    Pending --> Draft: Từ chối (Reject kèm lý do)
    Pending --> Scheduled: Phê duyệt (Approve)
    
    Scheduled --> Published: Tự động đăng đúng giờ
```

---

## Chi Tiết Thao Tác

1. **Gửi duyệt bài:** Sau khi hoàn thành soạn thảo, Editor nhấp **Submit for Review**.
2. **Kiểm tra bài viết:** Reviewer mở bài viết từ danh sách chờ duyệt, kiểm tra nội dung và Live Preview.
3. **Phê duyệt hoặc Từ chối:**
   - Nếu duyệt: Bấm **Phê Duyệt (Approve)**. Bài viết được khóa lịch và tự động đăng đúng giờ.
   - Nếu cần chỉnh sửa: Bấm **Từ Chối (Reject)** và nhập lý do phản hồi để Editor cập nhật lại.
