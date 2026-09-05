---
id: channel-assignments
title: Phân Quyền Kênh & Quản Trị Token
sidebar_label: Phân quyền kênh & Token
---

# Phân Quyền Kênh & Quản Trị Token

Quản lý quyền truy cập kênh mạng xã hội và đảm bảo kết nối API luôn hoạt động liên tục.

---

## 1. Phân Bổ Quyền Truy Cập Kênh
Nhằm tối ưu hóa việc phân chia dự án giữa các nhóm nội dung:
1. Truy cập **Cài đặt Workspace > Phân bổ quyền (Assignments)**.
2. Chọn thành viên cần phân quyền.
3. Tích chọn các tài khoản mạng xã hội mà thành viên này được phép sử dụng.
4. Nhấn **Lưu**. Thành viên đó sẽ chỉ thấy và đăng bài được trên các kênh được chỉ định.

---

## 2. Cơ Chế Tự Động Làm Mới Token (Auto-Refresh Token)
- Hệ thống duy trì tiến trình kiểm tra token chạy ngầm. Khi phát hiện token sắp hết hạn, hệ thống sẽ tự động gửi yêu cầu gia hạn qua Refresh Token.
- Trong trường hợp token bị vô hiệu hóa do đổi mật khẩu mạng xã hội, hệ thống sẽ hiển thị cảnh báo đỏ tại trang quản lý tài khoản để quản trị viên kết nối lại chỉ với 1 cú click.
