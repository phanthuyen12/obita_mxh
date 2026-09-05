<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Events\OmnichatMessageCreated;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Omnichat\PhoneNumberDetector;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('detects and normalizes Vietnamese mobile numbers', function (string $body, string $expected): void {
    expect(app(PhoneNumberDetector::class)->detect($body))->toBe($expected);
})->with([
    ['SĐT của tôi 0912 345 678', '0912345678'],
    ['Liên hệ +84 912-345-678 nhé', '0912345678'],
]);

it('stores a frontend message idempotently and broadcasts it', function (): void {
    config(['trypost.self_hosted' => true]);
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Admin->value]);
    $user->update(['current_workspace_id' => $workspace->id]);
    $account = SocialAccount::factory()->facebook()->create(['workspace_id' => $workspace->id]);
    $contact = OmnichatContact::factory()->create(['workspace_id' => $workspace->id]);
    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $workspace->id,
        'social_account_id' => $account->id,
        'contact_id' => $contact->id,
    ]);
    $clientId = (string) Str::uuid();
    Event::fake([OmnichatMessageCreated::class]);
    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['message_id' => 'mid.12345'], 200),
    ]);

    $payload = ['body' => 'Xin chào khách hàng', 'client_id' => $clientId, 'mode' => 'reply'];
    $this->actingAs($user->fresh())->postJson(route('app.omnichat.messages.store', $conversation), $payload)->assertCreated();
    $this->actingAs($user->fresh())->postJson(route('app.omnichat.messages.store', $conversation), $payload)->assertCreated();

    expect($conversation->messages()->count())->toBe(1)
        ->and($conversation->messages()->sole()->sender_user_id)->toBe($user->id)
        ->and($conversation->messages()->sole()->status)->toBe('sent');
    Event::assertDispatched(OmnichatMessageCreated::class, 1);

    $event = new OmnichatMessageCreated($conversation->messages()->sole());
    expect($event->broadcastOn())
        ->toBeInstanceOf(PrivateChannel::class)
        ->name->toBe("private-omnichat.channel.{$account->id}");
});
