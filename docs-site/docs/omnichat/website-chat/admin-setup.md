---
id: admin-setup
title: Cấu Hình Kênh Website Chat
sidebar_label: Cấu hình quản trị
---

# Cấu hình kênh Website Chat

Tài liệu này dành cho quản trị viên workspace. Tên màn hình và thao tác dưới đây là đặc tả mục tiêu; chỉ dùng trên production sau khi tính năng tương ứng đã được phát hành.

## 1. Tạo kênh

Vào **Omnichat → Kết nối → Thêm kênh → Website Live Chat**, sau đó khai báo:

- Tên nội bộ của kênh, ví dụ `Website King Coffee`.
- Domain production và các subdomain được phép.
- Domain staging/local dùng cho kiểm thử, nếu có.
- Inbox/team mặc định và người phụ trách dự phòng.
- Múi giờ, ngôn ngữ và giờ làm việc.

Sau khi lưu, hệ thống cấp `channel_id`, `public_key` và đoạn mã nhúng dành riêng cho kênh.

:::danger Không đưa secret lên website
Chỉ `public_key` được xuất hiện trong HTML/JavaScript phía khách. API secret, webhook secret và thông tin đăng nhập phải nằm ở backend hoặc secret manager.
:::

## 2. Cấu hình giao diện

| Nhóm | Giá trị đề xuất |
| --- | --- |
| Thương hiệu | Tên hiển thị, logo, màu chính, lời chào |
| Vị trí | Góc phải dưới; đổi sang trái nếu che nút nghiệp vụ |
| Kích thước | Desktop khoảng 360–400 px; mobile toàn màn hình |
| Trạng thái | Online, đang nhập, mất kết nối, ngoài giờ |
| Nội dung | Tiêu đề, lời chào, thông báo quyền riêng tư, thông báo ngoài giờ |
| Hành vi | Mở bằng click; không tự phát âm thanh khi chưa có tương tác |

Kiểm tra độ tương phản ở light/dark mode và bảo đảm launcher không che thanh cookie, nút mua hàng hoặc vùng safe-area trên mobile.

## 3. Form trước chat

Chỉ yêu cầu dữ liệu thật sự cần cho hỗ trợ:

- Tên: tùy chọn hoặc bắt buộc theo quy trình.
- Email/số điện thoại: chỉ bắt buộc nếu cần trả lời ngoài phiên.
- Chủ đề: dùng để định tuyến, không yêu cầu khách mô tả lại toàn bộ vấn đề.
- Đồng ý chính sách: hiển thị liên kết tới chính sách quyền riêng tư.

Không yêu cầu mật khẩu, OTP, số thẻ, mã bảo mật hoặc giấy tờ nhạy cảm trong form/chat.

## 4. Giờ làm việc và ngoài giờ

- Trong giờ: hiển thị thời gian phản hồi dự kiến và đưa vào hàng chờ.
- Ngoài giờ: cho phép để lại lời nhắn; không hiển thị agent là online.
- Ngày nghỉ: cấu hình ngoại lệ thay vì sửa lịch tuần.
- Tin ngoài giờ: nói rõ khi nào đội ngũ quay lại, không hứa thời gian không thể đáp ứng.

## 5. Định tuyến và SLA

Quy tắc MVP nên theo thứ tự:

1. Domain/page hoặc chủ đề khách chọn.
2. Team có kỹ năng phù hợp.
3. Agent đang online có ít hội thoại mở nhất.
4. Hàng chờ chung nếu không còn agent.

Thiết lập cảnh báo khi hội thoại chưa được nhận hoặc chưa có phản hồi đầu tiên vượt SLA. Không tự đóng hội thoại chỉ vì khách tạm rời trang.

## 6. Chatbot và chuyển người thật

- Chỉ bật bot khi đã có kịch bản, fallback và người chịu trách nhiệm nội dung.
- Bot phải có nút hoặc câu lệnh **Gặp nhân viên**.
- Chuyển người thật ngay với khiếu nại, thanh toán, dữ liệu nhạy cảm hoặc khi bot lặp lại.
- Agent nhìn thấy toàn bộ trao đổi trước khi nhận bàn giao.
- AI không tự thực hiện thao tác nghiệp vụ có tác động nếu chưa có xác nhận và audit log.

## 7. Phân quyền

| Quyền | Admin | Trưởng nhóm | Agent |
| --- | ---: | ---: | ---: |
| Xem và trả lời kênh được giao | ✓ | ✓ | ✓ |
| Phân công/chuyển hội thoại | ✓ | ✓ | Theo cấu hình |
| Sửa branding, domain, giờ làm việc | ✓ | — | — |
| Xoay/thu hồi khóa | ✓ | — | — |
| Xuất dữ liệu, sửa retention | ✓ | Theo quyền | — |

## 8. Checklist trước khi bật production

- [ ] Domain allowlist đúng, không dùng wildcard rộng.
- [ ] Widget hoạt động trên desktop, iOS và Android.
- [ ] Chính sách quyền riêng tư và consent đã được duyệt.
- [ ] Giờ làm việc, tin ngoài giờ và SLA đúng.
- [ ] Agent/team nhận được hội thoại và thông báo.
- [ ] Mất mạng, reload, đổi trang không làm mất lịch sử hoặc tạo trùng hội thoại.
- [ ] Spam/rate limit và giới hạn tệp đã bật.
- [ ] Secret không xuất hiện trong page source, bundle hoặc log trình duyệt.
- [ ] Có quy trình thu hồi khóa và xử lý sự cố.
