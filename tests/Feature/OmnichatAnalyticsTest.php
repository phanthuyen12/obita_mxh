<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\OmnichatContact;
use App\Models\OmnichatConversation;
use App\Models\OmnichatMessage;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->user->account_id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('user can view omnichat analytics page with real database records', function () {
    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
        'display_name' => 'Real Fashion Page',
        'is_active' => true,
    ]);

    $contact = OmnichatContact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'display_name' => 'Nguyễn Văn Thật',
        'phone' => '0912345678',
        'is_lead' => true,
        'lead_stage' => 'hot',
    ]);

    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $account->id,
        'contact_id' => $contact->id,
        'assigned_user_id' => $this->user->id,
        'status' => 'open',
        'last_message_preview' => 'Em muốn đặt mua combo này ạ',
    ]);

    OmnichatMessage::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $account->id,
        'conversation_id' => $conversation->id,
        'sender_contact_id' => $contact->id,
        'direction' => 'inbound',
        'body' => 'Em muốn đặt mua combo này ạ',
    ]);

    OmnichatMessage::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $account->id,
        'conversation_id' => $conversation->id,
        'sender_user_id' => $this->user->id,
        'direction' => 'outbound',
        'body' => 'Dạ shop chào bạn, shop giao liền nha!',
    ]);

    $response = $this->actingAs($this->user)
        ->get('/omnichat/analytics');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('omnichat/Analytics')
        ->where('summary.messages', 2)
        ->where('summary.conversations', 1)
        ->where('summary.contacts', 1)
        ->where('summary.inbound', 1)
        ->where('summary.outbound', 1)
        ->where('summary.hot_leads_count', 1)
        ->has('channels', 1)
        ->where('contacts.total', 1)
        ->where('contacts.data.0.name', 'Nguyễn Văn Thật')
        ->where('contacts.data.0.phone', '0912345678')
    );
});

test('user can view dedicated user analytics page with real user stats', function () {
    $otherMember = User::factory()->create(['name' => 'Nhân viên A']);
    $this->workspace->members()->attach($otherMember->id, ['role' => Role::Member->value]);

    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Facebook,
        'is_active' => true,
    ]);

    $contact = OmnichatContact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'display_name' => 'Khách Hàng Của A',
    ]);

    $conversation = OmnichatConversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $account->id,
        'contact_id' => $contact->id,
        'assigned_user_id' => $otherMember->id,
        'status' => 'resolved',
    ]);

    OmnichatMessage::factory()->create([
        'workspace_id' => $this->workspace->id,
        'social_account_id' => $account->id,
        'conversation_id' => $conversation->id,
        'sender_user_id' => $otherMember->id,
        'direction' => 'outbound',
        'body' => 'Dạ em đã hỗ trợ xong ạ',
    ]);

    $response = $this->actingAs($this->user)
        ->get("/omnichat/analytics/users/{$otherMember->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('omnichat/UserAnalytics')
        ->where('user.id', $otherMember->id)
        ->where('user_summary.total_assigned', 1)
        ->where('user_summary.total_messages_sent', 1)
        ->where('user_summary.resolved_count', 1)
        ->where('user_summary.resolution_rate', 100)
        ->where('assigned_customers.total', 1)
        ->where('assigned_customers.data.0.name', 'Khách Hàng Của A')
    );
});

test('user can export omnichat analytics with real customer records', function () {
    $contact = OmnichatContact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'display_name' => 'Khách Hàng Xuất CSV',
        'phone' => '0987654321',
    ]);

    $response = $this->actingAs($this->user)
        ->get('/omnichat/analytics/export');

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

test('user can filter omnichat analytics with search parameters matching real contacts', function () {
    $contact1 = OmnichatContact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'display_name' => 'Nguyễn Văn Minh',
        'is_lead' => true,
        'lead_stage' => 'hot',
    ]);

    $contact2 = OmnichatContact::factory()->create([
        'workspace_id' => $this->workspace->id,
        'display_name' => 'Trần Thị Thu',
        'is_lead' => false,
        'lead_stage' => 'cold',
    ]);

    $response = $this->actingAs($this->user)
        ->get('/omnichat/analytics?search=Minh&lead_status=hot');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('omnichat/Analytics')
        ->where('contacts.total', 1)
        ->where('contacts.data.0.name', 'Nguyễn Văn Minh')
    );
});
