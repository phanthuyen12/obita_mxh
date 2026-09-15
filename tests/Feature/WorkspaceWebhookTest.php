<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserWorkspace\Role;
use App\Events\OmnichatMessageCreated;
use App\Jobs\Omnichat\ProcessTelegramOmnichatWebhook;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatWebhookEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceWebhook;
use App\Models\WorkspaceWebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function webhookAdminUser(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $user->account_id, 'user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    return [$user->fresh(), $workspace];
}

it('lets workspace admin create, update and delete outbound webhook', function (): void {
    [$user, $workspace] = webhookAdminUser();

    // 1. Create Webhook
    $response = $this->actingAs($user)->post(route('app.workspace.webhooks.store'), [
        'name' => 'Test Outbound CRM',
        'url' => 'https://example.com/webhook-receiver',
        'events' => ['message.inbound', 'lead.detected'],
        'is_active' => true,
    ]);

    $response->assertRedirect()->assertSessionHas('success');

    $webhook = WorkspaceWebhook::query()->where('workspace_id', $workspace->id)->sole();
    expect($webhook->name)->toBe('Test Outbound CRM')
        ->and($webhook->url)->toBe('https://example.com/webhook-receiver')
        ->and($webhook->events)->toBe(['message.inbound', 'lead.detected'])
        ->and($webhook->secret)->not->toBeEmpty();

    // 2. Update Webhook
    $this->actingAs($user)->put(route('app.workspace.webhooks.update', $webhook), [
        'name' => 'Updated CRM Webhook',
        'url' => 'https://example.com/webhook-receiver-v2',
        'events' => ['message.inbound', 'message.outbound'],
        'is_active' => false,
    ])->assertRedirect()->assertSessionHas('success');

    $webhook->refresh();
    expect($webhook->name)->toBe('Updated CRM Webhook')
        ->and($webhook->is_active)->toBeFalse();

    // 3. Delete Webhook
    $this->actingAs($user)->delete(route('app.workspace.webhooks.destroy', $webhook))
        ->assertRedirect()->assertSessionHas('success');

    expect(WorkspaceWebhook::query()->where('id', $webhook->id)->exists())->toBeFalse();
});

it('can send a test ping to outbound webhook', function (): void {
    [$user, $workspace] = webhookAdminUser();

    $webhook = WorkspaceWebhook::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Test Ping Bot',
        'url' => 'https://httpbin.org/post',
        'secret' => Str::random(64),
        'events' => ['message.inbound'],
        'is_active' => true,
    ]);

    Http::fake([
        'https://httpbin.org/post' => Http::response(['status' => 'received'], 200),
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('app.workspace.webhooks.test', $webhook));

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('status', 200);

    $delivery = WorkspaceWebhookDelivery::query()->where('workspace_webhook_id', $webhook->id)->sole();
    expect($delivery->event)->toBe('test.ping')
        ->and($delivery->status)->toBe('success')
        ->and($delivery->response_status)->toBe(200);
});

it('dispatches outbound webhook when an omnichat message is created', function (): void {
    [$user, $workspace] = webhookAdminUser();

    $webhook = WorkspaceWebhook::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Auto Sync Chat',
        'url' => 'https://crm.partner.io/chat-in',
        'secret' => 'test-secret-12345678901234567890',
        'events' => ['message.inbound'],
        'is_active' => true,
    ]);

    Http::fake([
        'https://crm.partner.io/chat-in' => Http::response(['ok' => true], 200),
    ]);

    $contact = OmnichatContact::query()->create([
        'workspace_id' => $workspace->id,
        'status' => 'active',
        'display_name' => 'Khách hàng VIP',
    ]);

    $conversation = OmnichatConversation::query()->create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'external_id' => 'conv_ext_123',
        'status' => 'open',
    ]);

    $message = OmnichatMessage::query()->create([
        'workspace_id' => $workspace->id,
        'conversation_id' => $conversation->id,
        'external_id' => 'msg_ext_999',
        'direction' => 'inbound',
        'type' => 'text',
        'body' => 'Xin chào, tôi cần tư vấn giá sỉ!',
        'status' => 'received',
    ]);

    // Fire the event
    event(new OmnichatMessageCreated($message));

    $delivery = WorkspaceWebhookDelivery::query()
        ->where('workspace_webhook_id', $webhook->id)
        ->where('event', 'message.inbound')
        ->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->payload['message']['body'])->toBe('Xin chào, tôi cần tư vấn giá sỉ!');
});

it('renders omnichat webhook hub and can retry failed inbound event', function (): void {
    [$user, $workspace] = webhookAdminUser();

    $inboundEvent = OmnichatWebhookEvent::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'telegram',
        'external_event_id' => 'evt_998877',
        'event_type' => 'message',
        'payload' => ['update_id' => 12345, 'message' => ['text' => 'Hello']],
        'status' => 'failed',
        'attempts' => 1,
        'received_at' => now(),
        'error_message' => 'Connection timeout on internal queue',
    ]);

    Queue::fake([ProcessTelegramOmnichatWebhook::class]);

    // 1. Visit Webhook Hub
    $response = $this->actingAs($user)->get(route('app.omnichat.webhooks.index'));
    $response->assertSuccessful();

    // 2. Retry Event
    $retryResponse = $this->actingAs($user)->post(route('app.omnichat.webhooks.retry', $inboundEvent));
    $retryResponse->assertRedirect()->assertSessionHas('success');

    Queue::assertPushed(ProcessTelegramOmnichatWebhook::class);

    $inboundEvent->refresh();
    expect($inboundEvent->status)->toBe('pending')
        ->and($inboundEvent->attempts)->toBe(2);
});
