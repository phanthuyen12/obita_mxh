<?php

declare(strict_types=1);

return [
    'title' => 'Không gian làm việc',
    'select_title' => 'Không gian làm việc của bạn',
    'select_description' => 'Chọn một không gian làm việc để tiếp tục',
    'current' => 'Hiện tại',
    'connections' => ':count kết nối',
    'posts' => ':count bài viết',
    'create' => [
        'page_title' => 'Tạo không gian làm việc',
        'title' => 'Thiết lập không gian làm việc',
        'description' => 'Hãy cho chúng tôi biết một chút về bạn hoặc dự án của bạn. Chúng tôi sẽ dùng thông tin này để điều chỉnh nội dung AI theo giọng điệu của bạn.',
        'website' => 'Website',
        'website_placeholder' => 'https://thuonghieu.com',
        'autofill' => 'Tự động điền từ website',
        'autofill_missing_url' => 'Trước tiên hãy nhập URL.',
        'autofill_success' => 'Đã tải thông tin thương hiệu.',
        'autofill_error' => 'Không thể tự động điền. Bạn có thể nhập thủ công.',
        'autofill_errors' => [
            'unreachable' => 'Không thể truy cập website (:reason).',
            'http_status' => 'Website trả về trạng thái không mong đợi (:status).',
            'invalid_scheme' => 'Chỉ hỗ trợ URL http và https.',
            'missing_host' => 'URL chưa có tên miền.',
            'unresolvable_host' => 'Không thể phân giải tên miền (:host).',
            'private_network' => 'Không cho phép URL trỏ đến mạng riêng.',
        ],
        'logo_captured' => 'Đã lấy logo từ website.',
        'name' => 'Tên không gian làm việc',
        'name_placeholder' => 'ví dụ: Công ty ABC',
        'brand_description' => 'Mô tả thương hiệu',
        'brand_description_placeholder' => 'Thương hiệu của bạn làm gì?',
        'content_language' => 'Ngôn ngữ nội dung',
        'content_language_description' => 'Chú thích do AI tạo sẽ được viết bằng ngôn ngữ này.',
        'brand_color' => 'Màu thương hiệu',
        'background_color' => 'Màu nền',
        'text_color' => 'Màu chữ',
        'submit' => 'Tạo không gian làm việc',
        'success' => 'Đã tạo không gian làm việc. Hãy kết nối tài khoản mạng xã hội để bắt đầu đăng bài.',
    ],
    'cannot_delete_last' => 'Bạn không thể xóa không gian làm việc duy nhất. Hãy hủy gói đăng ký trong phần cài đặt thanh toán để đóng tài khoản.',
    'flash' => [
        'deleted' => 'Đã xóa không gian làm việc.',
    ],
];
