<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteChatConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'status' => $this->status,
            'priority' => $this->priority,
            'last_message_preview' => $this->last_message_preview,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_inbound_at' => $this->last_inbound_at?->toIso8601String(),
            'last_outbound_at' => $this->last_outbound_at?->toIso8601String(),
            'unread_count' => (int) data_get($this->meta, 'unread_count', 0),
            'contact' => [
                'id' => $this->contact->id,
                'name' => $this->contact->display_name,
                'email' => $this->contact->email,
                'phone' => $this->contact->phone,
                'avatar_url' => $this->contact->avatar_url,
                'locale' => $this->contact->locale,
            ],
            'tags' => $this->tags->map->only(['id', 'name', 'color'])->values(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
