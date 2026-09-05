<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\OmnichatConversation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OmnichatConversationTagged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /** @param array<int, string> $tagIds */
    public function __construct(public OmnichatConversation $conversation, public array $tagIds) {}
}
