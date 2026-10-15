---
paths:
  - '*.yml'
---

# General

## Tên thương hiệu chatbot là "Nhất Thiên" (có dấu)
Trong các file workflow Dify (`CHATBOT VERSION 2 - MASTER.yml`, `Nhat Thien Trading Assistant v1.yml`), tên thương hiệu luôn viết là **Nhất Thiên** — không phải "Nhật Thiên" (sai dấu), cũng không phải "Nhat Thien" (không dấu).

Phần còn lại của prompt cố tình viết tiếng Việt không dấu, nên mỗi prompt có nhắc thương hiệu đều kèm dòng chốt: `TEN THUONG HIEU: luon viet day du dau tieng Viet la "Nhất Thiên", khong viet khong dau.` để model không bắt chước style không dấu.

Lưu ý: tên file `Nhat Thien Trading Assistant v1.yml` vẫn để không dấu (đổi tên sẽ làm lệch đường dẫn), chỉ nội dung dùng tên có dấu.
