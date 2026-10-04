<?php

declare(strict_types=1);

use App\Actions\Omnichat\StoreMessage;
use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Jobs\Omnichat\SendBroadcastMessageJob;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastMessage;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('immediate broadcast queues messages instead of marking them as already sent', function (): void {
    Queue::fake();

    $contact = OmnichatContact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Mai',
    ]);
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
    ]);
    OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $account->id,
        'contact_id' => $contact->id,
        'external_id' => 'facebook-customer-1',
    ]);

    $response = $this->actingAs($this->user)->postJson('/omnichat/broadcast', [
        'name' => 'Quick send',
        'trigger_type' => 'all',
        'message_template' => 'Xin chào {name}',
        'ai_spin_enabled' => false,
        'delay_seconds' => 1,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('queued_count', 1)
        ->assertJsonPath('sent_count', 0);

    $broadcastMessage = BroadcastMessage::query()->firstOrFail();

    expect($broadcastMessage->status)->toBe('queued')
        ->and($broadcastMessage->sent_body)->toBe('Xin chào Mai')
        ->and(BroadcastCampaign::query()->firstOrFail()->status)->toBe('sending');

    Queue::assertPushed(SendBroadcastMessageJob::class, fn (SendBroadcastMessageJob $job): bool => $job->broadcastMessageId === $broadcastMessage->id);
});

test('broadcast message is marked sent only after the omnichat sender confirms delivery', function (): void {
    $contact = OmnichatContact::factory()->create(['workspace_id' => $this->workspace->id]);
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
    ]);
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $account->id,
        'contact_id' => $contact->id,
        'external_id' => 'facebook-customer-2',
    ]);
    $campaign = BroadcastCampaign::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Delivery test',
        'trigger_type' => 'all',
        'message_template' => 'Hello',
        'status' => 'sending',
    ]);
    $broadcastMessage = BroadcastMessage::query()->create([
        'workspace_id' => $this->workspace->id,
        'broadcast_campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'conversation_id' => $conversation->id,
        'sent_body' => 'Hello',
        'status' => 'queued',
    ]);

    $storeMessage = mock(StoreMessage::class);
    $storeMessage->shouldReceive('execute')
        ->once()
        ->andReturn(new OmnichatMessage(['status' => 'sent']));

    (new SendBroadcastMessageJob($broadcastMessage->id))->handle($storeMessage);

    expect($broadcastMessage->fresh()->status)->toBe('sent')
        ->and($campaign->fresh()->status)->toBe('completed')
        ->and($campaign->fresh()->stats['total_sent'])->toBe(1);
});
