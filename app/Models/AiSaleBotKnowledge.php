<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSaleBotKnowledge extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ai_sale_bot_knowledges';

    protected $fillable = [
        'workspace_id',
        'ai_bot_id',
        'title',
        'file_type',
        'file_path',
        'dify_document_id',
        'dify_batch_id',
        'content',
        'token_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'token_count' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class, 'ai_bot_id');
    }
}
