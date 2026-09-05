<?php

namespace App\Events;

use App\Models\SocialAccount;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccountSyncFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $accountId;

    public string $name;

    public string $platform;

    public string $errorMessage;

    public function __construct(
        public SocialAccount $account,
        string $errorMessage
    ) {
        $this->accountId = $account->id;
        $this->name = $account->accountDisplayName();
        $this->platform = $account->platform->value;
        $this->errorMessage = $errorMessage;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspace.'.$this->account->workspace_id),
        ];
    }

    public function broadcastAs(): string
    {
        return '.account.sync.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'account_id' => $this->accountId,
            'name' => $this->name,
            'platform' => $this->platform,
            'error_message' => $this->errorMessage,
        ];
    }
}
