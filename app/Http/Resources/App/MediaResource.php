<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tags = $this->resource->relationLoaded('tags')
            ? $this->resource->getRelation('tags')
            : $this->resource->tags()->get(['tags.id', 'tags.name']);

        return [
            'id' => $this->id,
            'path' => $this->path,
            'url' => $this->url,
            'type' => $this->type->value,
            'mime_type' => $this->mime_type,
            'original_filename' => $this->original_filename,
            'size' => $this->size,
            'meta' => $this->meta,
            'tags' => $tags->pluck('name')->values()->all(),
            'folder_id' => $this->folder_id,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
