<?php

declare(strict_types=1);

namespace App\Jobs\Omnichat;

use App\Actions\Omnichat\SyncShopeeConversations as SyncShopeeConversationsAction;
use App\Models\SocialAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncShopeeConversations implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public string $socialAccountId) {}

    public function handle(SyncShopeeConversationsAction $sync): void
    {
        $account = SocialAccount::query()->find($this->socialAccountId);
        if ($account !== null) {
            $sync->execute($account);
        }
    }
}
