<!DOCTYPE html>
<html lang="vi" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  @if(isset($title))
  <title>{{ $title }}</title>
  @endif
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" media="screen">
</head>
<body style="margin: 0; width: 100%; padding: 0; -webkit-font-smoothing: antialiased; word-break: break-word; background-color: #fafafa; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
  @if(isset($previewText))
  <div style="display: none">
    {{ $previewText }}
  </div>
  @endif
  <div role="article" aria-roledescription="email" aria-label="{{ $title }}" lang="vi">
    <div style="background-color: #fafafa; padding: 24px 16px;">
      <table align="center" cellpadding="0" cellspacing="0" role="none" style="width: 552px; max-width: 100%; margin: 0 auto;">
        <tr>
          <td style="text-align: center; padding: 24px 0;">
            <h2 style="margin: 0; font-size: 20px; font-weight: 700; color: #18181b;">King Hub Social</h2>
          </td>
        </tr>
        <tr>
          <td style="border-radius: 12px; background-color: #ffffff; padding: 40px; font-size: 15px; color: #3f3f46; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05); border: 1px solid #e4e4e7;">
            <div style="text-align: center; margin-bottom: 24px;">
              <span style="display: inline-block; width: 48px; height: 48px; line-height: 48px; border-radius: 50%; background-color: #fee2e2; color: #b91c1c; font-size: 22px; font-weight: bold;">✕</span>
            </div>

            <h1 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #991b1b; text-align: center;">
              {{ $title }}
            </h1>

            <p style="margin: 0 0 20px; line-height: 24px; color: #52525b; text-align: center;">
              {{ $body }}
            </p>

            @if(isset($reason) && $reason !== '')
            <div style="margin-top: 20px; border-radius: 8px; background-color: #fef2f2; padding: 16px; border: 1px solid #fecaca;">
              <p style="margin: 0 0 6px; font-size: 13px; font-weight: 700; color: #991b1b; text-transform: uppercase; letter-spacing: 0.5px;">
                Lý do từ chối / Ghi chú chỉnh sửa:
              </p>
              <p style="margin: 0; font-size: 14px; line-height: 22px; color: #7f1d1d; font-weight: 500;">{{ $reason }}</p>
            </div>
            @endif

            <div style="margin-top: 20px; border-radius: 8px; background-color: #f4f4f5; padding: 16px; border: 1px solid #e4e4e7;">
              <p style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 0.5px;">
                Nội dung bài viết:
              </p>
              <p style="margin: 0; font-size: 14px; line-height: 22px; color: #18181b; white-space: pre-wrap;">{{ Str::limit($postContent, 250) }}</p>
            </div>

            <div style="margin-top: 32px; text-align: center;">
              <a href="{{ $url }}" style="display: inline-block; text-decoration: none; padding: 14px 28px; font-size: 15px; font-weight: 600; line-height: 1; border-radius: 8px; background-color: #dc2626; color: #ffffff;">
                Chỉnh sửa bài viết ngay &rarr;
              </a>
            </div>
          </td>
        </tr>
        <tr>
          <td style="text-align: center; padding: 24px; font-size: 12px; color: #a1a1aa;">
            Thông báo tự động từ hệ thống quản lý King Hub Social.
          </td>
        </tr>
      </table>
    </div>
  </div>
</body>
</html>
