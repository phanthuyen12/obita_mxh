---
id: widget-integration
title: Tích Hợp Chat Widget Vào Website
sidebar_label: Tích hợp widget
---

# Tích hợp Chat Widget vào website

:::info Trạng thái MVP
Widget nhúng, Public API tạo phiên, gửi/nhận tin và trang quản trị kênh đã được triển khai. Phần JavaScript API điều khiển nâng cao ở cuối tài liệu vẫn là hướng mở rộng đề xuất.
:::

## Điều kiện trước khi tích hợp

- Một kênh Website Live Chat đã tạo trong đúng workspace.
- `public_key` của kênh và domain đã được allowlist.
- Quyền chỉnh HTML/template hoặc tag manager của website.
- URL chính sách quyền riêng tư của doanh nghiệp.

## Cách nhúng chuẩn

Đặt đoạn mã do màn hình quản trị sinh ra ngay trước thẻ đóng `</body>`:

```html
<script
  src="https://YOUR_KING_HUB_DOMAIN/website-chat/widget.js"
  data-public-key="wc_pk_REPLACE_WITH_PUBLIC_KEY"
  async
></script>
```

Không sao chép `YOUR_KING_HUB_DOMAIN` vào production. Trang **Omnichat → Website Live Chat** sinh sẵn đoạn mã đúng domain và khóa của từng kênh.

## Thử trên website demo

Mở [Website Chat Demo](https://autopost.tnicorporation.com/website-chat/demo.html), nhập `public_key` và bấm **Mở Website Chat**. Trước đó, thêm origin này vào danh sách domain được phép:

```text
https://autopost.tnicorporation.com
```

Trang demo dùng widget và API production nên hội thoại sẽ xuất hiện trong Omnichat Inbox của workspace sở hữu public key. Nếu cần tự xây giao diện thay cho widget, xem [API Website Live Chat](./api-reference.md).

## Truyền thông tin khách đã đăng nhập — chưa có trong MVP

Không tin dữ liệu danh tính chỉ do browser tự khai báo. Website backend phải cấp một token danh tính ngắn hạn đã ký, widget chỉ nhận token đó:

```html
<script>
  window.KingHubChatSettings = {
    publicKey: 'wc_pk_REPLACE_WITH_PUBLIC_KEY',
    identityToken: 'SIGNED_SHORT_LIVED_TOKEN_FROM_YOUR_BACKEND',
    locale: 'vi',
  };
</script>
```

Token nên chứa định danh nội bộ ổn định, tên hiển thị và các claim tối thiểu; có `exp`, `iat`, `aud` và `jti`. Không đưa mật khẩu, access token phiên đăng nhập, thông tin thanh toán hoặc dữ liệu không cần cho CSKH vào token.

Nếu không có token đã ký, widget tạo khách ẩn danh với visitor ID ngẫu nhiên lưu first-party. Agent phải thấy nhãn **Chưa xác minh**.

## Ngữ cảnh trang và chiến dịch

MVP tự gửi `page_url` và `page_title`. API điều khiển ngữ cảnh mở rộng dưới đây là đề xuất cho giai đoạn tiếp theo:

```js
window.KingHubChat?.setContext({
  pageType: 'product',
  productId: 'coffee-001',
  campaign: 'summer-2026',
});
```

Chỉ dùng danh sách khóa được backend cho phép. Server phải bỏ qua trường lạ, giới hạn độ dài và không dùng metadata client cho quyết định bảo mật, giá hoặc khuyến mãi.

## JavaScript API điều khiển đề xuất — chưa có trong MVP

```js
window.KingHubChat?.open();
window.KingHubChat?.close();
window.KingHubChat?.show();
window.KingHubChat?.hide();
window.KingHubChat?.setLocale('vi');
window.KingHubChat?.on('unread-count-changed', ({ count }) => {});
```

SDK cần xử lý trường hợp lệnh được gọi trước khi script tải xong bằng command queue hoặc sự kiện `ready`.

## Content Security Policy

Chỉ thêm đúng origin được cung cấp, ví dụ:

```http
Content-Security-Policy:
  script-src 'self' https://cdn.example.com;
  connect-src 'self' https://chat-api.example.com wss://chat-realtime.example.com;
  img-src 'self' data: https://chat-cdn.example.com;
  frame-src https://chat-widget.example.com;
```

Không thêm `*`, không bật `unsafe-eval` chỉ để widget chạy. Danh sách origin thực tế phải lấy từ cấu hình phát hành.

## Tích hợp trên SPA / Frameworks (React, Vue, Next.js)

import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';

Với các website dạng Single Page Application (SPA), bạn chỉ cần khởi tạo SDK **một lần duy nhất** ở Layout gốc (Root Layout hoặc App Component).

<Tabs>
  <TabItem value="react" label="React / Next.js" default>
    Thêm đoạn script sau vào `pages/_document.js` (hoặc `app/layout.tsx` nếu dùng App Router):

    ```jsx
    import Script from 'next/script';

    export default function RootLayout({ children }) {
      return (
        <html lang="vi">
          <body>
            {children}
            {/* Nhúng Widget Script bằng next/script */}
            <Script 
              src="https://autopost.tnicorporation.com/website-chat/widget.js" 
              strategy="lazyOnload"
              data-public-key="wc_pk_REPLACE_WITH_PUBLIC_KEY"
            />
          </body>
        </html>
      );
    }
    ```
  </TabItem>
  <TabItem value="vue" label="Vue / Nuxt">
    Trong ứng dụng Vue, bạn có thể nhúng script ở file `index.html` gốc hoặc thông qua `useHead` trong Nuxt.js:

    ```vue
    <script setup>
    import { useHead } from '@vueuse/head' // hoặc Nuxt 3 useHead

    useHead({
      script: [
        {
          src: 'https://autopost.tnicorporation.com/website-chat/widget.js',
          'data-public-key': 'wc_pk_REPLACE_WITH_PUBLIC_KEY',
          async: true,
          tagPosition: 'bodyClose'
        }
      ]
    })
    </script>
    ```
  </TabItem>
</Tabs>

**Lưu ý khi làm việc với SPA:**
- **Chuyển trang (Navigation):** Khi chuyển route, gọi hàm `window.KingHubChat?.setContext({...})` thay vì tải lại toàn bộ thẻ `<script>`.
- **Bảo mật phiên đăng nhập:** Khi người dùng (user) thực hiện đăng xuất khỏi hệ thống của bạn, cần gọi API reset phiên của SDK chat để tránh lộ tin nhắn cá nhân cho người dùng kế tiếp trên cùng một thiết bị.
- **Tránh rò rỉ bộ nhớ (Memory leaks):** Đảm bảo bạn không gắn lặp lại các Event Listener của chat SDK mỗi lần chuyển trang.

## Consent và cookie

Nếu chính sách của doanh nghiệp yêu cầu consent trước khi tải chat:

1. Chưa consent: không tải SDK và không tạo visitor ID.
2. Sau consent: tải script, sau đó mở widget theo thao tác của khách.
3. Thu hồi consent: đóng widget, ngừng tracking không thiết yếu và áp dụng quy trình xóa dữ liệu nếu được yêu cầu.

## Kiểm thử tích hợp

1. Gửi tin khi online và xác nhận agent nhận realtime.
2. Agent trả lời và xác nhận widget nhận đúng một lần.
3. Reload/đổi trang và xác nhận lịch sử còn nguyên.
4. Mở tab thứ hai và kiểm tra đồng bộ unread.
5. Mất mạng, gửi lại và kiểm tra không tạo tin trùng.
6. Domain không allowlist phải bị từ chối.
7. Public key bị thu hồi phải ngừng tạo phiên mới.
8. Đăng xuất trên thiết bị dùng chung không được để người sau xem lịch sử cũ.
9. Kiểm tra bàn phím, screen reader, mobile viewport và reduced motion.

## Xử lý lỗi thường gặp

| Hiện tượng | Kiểm tra |
| --- | --- |
| Không thấy nút chat | URL script, CSP, ad blocker, trạng thái kênh và console |
| `domain_not_allowed` | Domain, subdomain và port có nằm trong allowlist hay không |
| Kết nối realtime thất bại | `connect-src`, proxy WebSocket, mạng doanh nghiệp |
| Tạo nhiều hội thoại | SDK bị khởi tạo lặp hoặc visitor/session ID không ổn định |
| Agent không thấy ngữ cảnh | Tên khóa không được allowlist hoặc payload vượt giới hạn |
