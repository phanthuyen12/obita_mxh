<?php

declare(strict_types=1);

namespace App\Enums\Folder;

enum Permission: string
{
    case View = 'view';
    case CreateFolder = 'create_folder';
    case UploadMedia = 'upload_media';
    case EditMedia = 'edit_media';
    case DeleteMedia = 'delete_media';
    case ManageFolder = 'manage_folder';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Xem thư viện',
            self::CreateFolder => 'Tạo thư mục con',
            self::UploadMedia => 'Upload Media',
            self::EditMedia => 'Sửa / di chuyển Media',
            self::DeleteMedia => 'Xóa Media',
            self::ManageFolder => 'Quản lý Folder',
        };
    }
}
