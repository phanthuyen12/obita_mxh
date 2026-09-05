<?php

namespace App\Models;

use App\Enums\WordPress\SiteStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordPressSite extends Model
{
    use HasUuids;

    //
    protected $table = 'wordpress_sites';

    protected $fillable = [
        'workspace_id',
        'name',
        'url',
        'username',
        'application_password',
        'status',
        'error_message',
        'wp_user_id',
        'wp_user_name',
        'categories_cache',
        'tags_cache',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'application_password' => 'encrypted', // Tự động mã hóa khi lưu vào DB
            'status' => SiteStatus::class,
            'categories_cache' => 'array',
            'tags_cache' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
