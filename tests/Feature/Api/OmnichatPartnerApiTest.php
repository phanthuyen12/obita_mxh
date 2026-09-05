<?php

declare(strict_types=1);

use App\Jobs\Omnichat\DeliverWebsiteChatWebhook;
use App\Models\OmnichatChannel;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\OmnichatTag;
use App\Services\Brand\SafeHttpFetcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $result = createApiTestToken();
    $this->workspace = $result['workspace'];
    $this->plainToken = $result['plain_token'];
    $this->channel = OmnichatChannel::factory()->website()->create(['workspace_id' => $this->workspace->id]);
});

it('serves the API docs UI', function (): void {
    $this->get(route('api.docs'))->assertOk()->assertSee('api-reference');
});

it('registers an outbound webhook for a website chat channel', function (): void {
    $this->withToken($this->plainToken)
        ->putJson(route('api.website-chat.webhook.update', $this->channel), [
            'url' => 'https://8.8.8.8/kinghub-webhook',
            'events' => ['message.created', 'conversation.tagged'],
        ])
        ->assertOk()
        ->assertJsonPath('webhook.enabled', true)
        ->assertJsonPath('webhook.url', 'https://8.8.8.8/kinghub-webhook')
        ->assertJsonStructure(['secret']);

    expect(data_get($this->channel->refresh()->settings, 'outbound_webhook.events'))
        ->toBe(['message.created', 'conversation.tagged']);
});

it('lists only conversations from the requested website chat channel', function (): void {
    $contact = OmnichatContact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'display_name' => 'Nguyễn Văn An',
        'email' => 'an@example.com',
    ]);
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => null,
        'channel_id' => $this->channel->id,
        'contact_id' => $contact->id,
        'status' => 'open',
        'last_message_preview' => 'Tôi cần hỗ trợ',
    ]);

    $otherChannel = OmnichatChannel::factory()->website()->create(['workspace_id' => $this->workspace->id]);
    OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => null,
        'channel_id' => $otherChannel->id,
        'contact_id' => $contact->id,
    ]);

    $this->withToken($this->plainToken)
        ->getJson(route('api.website-chat.conversations.index', [
            'channel' => $this->channel,
            'status' => 'open',
            'search' => 'Nguyễn',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $conversation->id)
        ->assertJsonPath('data.0.contact.name', 'Nguyễn Văn An')
        ->assertJsonPath('meta.total', 1);
});

it('creates and assigns Omnichat conversation tags', function (): void {
    $tagId = $this->withToken($this->plainToken)
        ->postJson(route('api.omnichat.tags.store'), ['name' => 'VIP', 'color' => '#16A34A'])
        ->assertCreated()
        ->json('tag.id');

    $contact = OmnichatContact::factory()->create(['workspace_id' => $this->workspace->id]);
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => null,
        'channel_id' => $this->channel->id,
        'contact_id' => $contact->id,
    ]);

    $this->withToken($this->plainToken)
        ->putJson(route('api.omnichat.conversations.tags.update', $conversation), ['tag_ids' => [$tagId]])
        ->assertOk()
        ->assertJsonPath('tags.0.name', 'VIP');

    expect($conversation->tags()->sole())->toBeInstanceOf(OmnichatTag::class);
});

it('signs and delivers an outbound Omnichat reply to the partner webhook', function (): void {
    $secret = 'whsec_test_secret';
    $settings = $this->channel->settings;
    $settings['outbound_webhook'] = [
        'url' => 'https://8.8.8.8/kinghub-webhook',
        'events' => ['message.created'],
        'enabled' => true,
    ];
    $this->channel->update(['settings' => $settings, 'webhook_secret' => $secret]);

    $contact = OmnichatContact::factory()->create(['workspace_id' => $this->workspace->id]);
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => null,
        'channel_id' => $this->channel->id,
        'contact_id' => $contact->id,
    ]);
    $message = OmnichatMessage::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => null,
        'channel_id' => $this->channel->id,
        'conversation_id' => $conversation->id,
        'direction' => 'outbound',
        'body' => 'Xin chào từ Omnichat',
    ]);

    Http::fake(['https://8.8.8.8/*' => Http::response(['received' => true])]);

    (new DeliverWebsiteChatWebhook('message.created', $this->channel->id, $message->id))
        ->handle(app(SafeHttpFetcher::class));

    Http::assertSent(function (Request $request) use ($secret): bool {
        $signature = $request->header('X-KingHub-Signature')[0] ?? '';
        preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/', $signature, $matches);

        return $request->url() === 'https://8.8.8.8/kinghub-webhook'
            && $request->header('X-KingHub-Event')[0] === 'message.created'
            && data_get($request->data(), 'data.message.body') === 'Xin chào từ Omnichat'
            && hash_equals($matches[2] ?? '', hash_hmac('sha256', ($matches[1] ?? '').'.'.$request->body(), $secret));
    });
});
