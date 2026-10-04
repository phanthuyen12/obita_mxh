<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSaleBotProduct extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ai_sale_bot_products';

    protected $fillable = [
        'workspace_id',
        'sku',
        'name',
        'unit',
        'price',
        'sale_price',
        'stock_quantity',
        'description',
        'image_url',
        'attributes',
        'is_in_stock',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'attributes' => 'array',
            'is_in_stock' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
