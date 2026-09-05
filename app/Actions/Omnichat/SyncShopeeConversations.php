<?php

declare(strict_types=1);

namespace App\Actions\Omnichat;

use App\Models\SocialAccount;
use App\Support\Omnichat\ShopeeClient;

class SyncShopeeConversations
{
    public function __construct(private readonly ShopeeClient $client, private readonly ImportShopeeConversation $importer) {}

    public function execute(SocialAccount $account): int
    {
        $cursor = null;
        $synced = 0;
        do {
            $result = $this->client->conversations($account, $cursor);
            $conversations = data_get($result, 'response.conversations', []);
            foreach (is_array($conversations) ? $conversations : [] as $data) {
                if (! is_array($data)) {
                    continue;
                }
                $conversation = $this->importer->conversation($account, $data);
                if ($conversation !== null) {
                    $this->history($account, $conversation->external_id, (int) data_get($data, 'business_type', 0));
                    $synced++;
                }
            }
            $more = (bool) data_get($result, 'response.page_result.more', false);
            $cursor = data_get($result, 'response.page_result.next_message_time_nano');
            $cursor = is_scalar($cursor) ? (string) $cursor : null;
        } while ($more && filled($cursor));

        return $synced;
    }

    public function history(SocialAccount $account, string $conversationId, int $businessType = 0): int
    {
        $offset = null;
        $synced = 0;
        do {
            $result = $this->client->messages($account, $conversationId, $offset, $businessType);
            $messages = data_get($result, 'response.messages', []);
            foreach (array_reverse(is_array($messages) ? $messages : []) as $data) {
                if (is_array($data) && $this->importer->message($account, $data) !== null) {
                    $synced++;
                }
            }
            $nextOffset = data_get($result, 'response.page_result.next_offset');
            $nextOffset = is_scalar($nextOffset) ? (string) $nextOffset : null;
            if (count(is_array($messages) ? $messages : []) < 60 || $nextOffset === $offset) {
                break;
            }
            $offset = $nextOffset;
        } while (filled($offset));

        return $synced;
    }
}
