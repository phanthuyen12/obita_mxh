---
id: overview
title: Mô Hình Phân Quyền & Ma Trận Vai Trò
sidebar_label: Ma trận phân quyền
---

# Mô Hình Phân Quyền & Ma Trận Vai Trò

Hệ thống King Hub Social xây dựng mô hình phân quyền chặt chẽ dựa trên vai trò (Role-Based Access Control - RBAC) nhằm đáp ứng nhu cầu quản trị chuyên nghiệp trong doanh nghiệp và agency.

---

## Ma Trận Phân Quyền Chi Tiết

| Chức Năng / Thao Tác | Quản Trị Viên (Admin) | Người Kiểm Duyệt (Reviewer) | Chuyên Viên Nội Dung (Editor) |
| :--- | :---: | :---: | :---: |
| **Quản trị Workspace & Mời thành viên** | Toàn quyền | Không | Không |
| **Kết nối nền tảng mạng xã hội** | Toàn quyền | Không | Không |
| **Phân bổ quyền truy cập kênh cho nhân sự** | Toàn quyền | Không | Không |
| **Cấu hình Brand Voice & Dify AI** | Toàn quyền | Chỉ xem | Không |
| **Cấu hình API Keys, MCP & Billing** | Toàn quyền | Không | Không |
| **Phê duyệt bài viết (Approve / Reject)** | Toàn quyền | **Toàn quyền** | Không |
| **Đăng bài trực tiếp (Không qua duyệt)** | Có | Có | Không (Bắt buộc duyệt) |
| **Soạn thảo trên các kênh được phân quyền** | Toàn quyền | Các kênh được gán | Các kênh được gán |
| **Tạo chiến dịch Content Clones** | Có | Có | Có (Cần duyệt bài) |
| **Bình luận nội bộ trên bài viết** | Có | Có | Có |
| **Truy cập hộp thư Omnichat** | Toàn quyền | Các kênh được gán | Hội thoại được phân công |
| **Xem báo cáo phân tích tổng thể** | Toàn quyền | Xem báo cáo nội dung | Xem bài viết cá nhân |
