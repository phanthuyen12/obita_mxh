<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ __('Hủy nhận email') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background-color:#fafafa;font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
  <div style="max-width:480px;margin:80px auto;padding:0 16px;">
    <div style="background:#ffffff;border:1px solid #e4e4e7;border-radius:12px;padding:40px;text-align:center;">
      @if($notFound)
        <h1 style="margin:0 0 12px;font-size:20px;font-weight:700;color:#09090b;">{{ __('Không tìm thấy liên kết') }}</h1>
        <p style="margin:0;font-size:15px;line-height:24px;color:#52525b;">
          {{ __('Liên kết hủy nhận này không hợp lệ hoặc đã hết hiệu lực.') }}
        </p>
      @else
        <div style="margin-bottom:20px;">
          <span style="display:inline-block;width:48px;height:48px;line-height:48px;border-radius:50%;background-color:#dcfce7;color:#15803d;font-size:24px;font-weight:bold;">&check;</span>
        </div>
        <h1 style="margin:0 0 12px;font-size:20px;font-weight:700;color:#09090b;">
          {{ $alreadyDone ? __('Bạn đã hủy nhận trước đó') : __('Đã hủy nhận email') }}
        </h1>
        <p style="margin:0;font-size:15px;line-height:24px;color:#52525b;">
          {{ __('Địa chỉ :email sẽ không nhận email từ chiến dịch này nữa.', ['email' => $email]) }}
        </p>
      @endif
    </div>
  </div>
</body>
</html>
