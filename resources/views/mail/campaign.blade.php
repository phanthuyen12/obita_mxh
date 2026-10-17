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
  @if(isset($previewText) && $previewText)
  <div style="display: none">
    {{ $previewText }}
  </div>
  @endif
  <div role="article" aria-roledescription="email" aria-label="{{ $title }}" lang="vi">
    <div style="background-color: #fafafa; padding: 24px 16px;">
      <table align="center" cellpadding="0" cellspacing="0" role="none" style="width: 552px; max-width: 100%; margin: 0 auto;">
        <tr>
          <td style="text-align: center; padding: 24px 0;">
            <h2 style="margin: 0; font-size: 20px; font-weight: 700; color: #18181b;">{{ $senderName }}</h2>
          </td>
        </tr>
        <tr>
          <td style="border-radius: 12px; background-color: #ffffff; padding: 40px; font-size: 15px; line-height: 24px; color: #3f3f46; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05); border: 1px solid #e4e4e7;">
            {!! $bodyHtml !!}
          </td>
        </tr>
        <tr>
          <td style="text-align: center; padding: 24px; font-size: 12px; line-height: 20px; color: #a1a1aa;">
            <p style="margin: 0 0 8px;">
              Bạn nhận được email này vì đã để lại địa chỉ {{ $recipientEmail }} với {{ $senderName }}.
            </p>
            <p style="margin: 0;">
              <a href="{{ $unsubscribeUrl }}" style="color: #71717a; text-decoration: underline;">Hủy nhận email</a>
            </p>
          </td>
        </tr>
      </table>
    </div>
  </div>
</body>
</html>
