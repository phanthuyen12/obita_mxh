<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\AiBot;
use App\Models\AiSaleBotKnowledge;
use App\Models\AiSaleBotProduct;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config(['trypost.self_hosted' => true]);

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->owner->account_id,
        'user_id' => $this->owner->id,
    ]);
    $this->workspace->members()->attach($this->owner->id, ['role' => Role::Admin->value]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
});

it('renders the ai train page with knowledges, products, and bots', function (): void {
    AiSaleBotKnowledge::query()->create([
        'workspace_id' => $this->workspace->id,
        'title' => 'Chính sách bảo hành',
        'content' => 'Bảo hành 12 tháng chính hãng',
        'token_count' => 100,
        'status' => 'ready',
    ]);

    AiSaleBotProduct::query()->create([
        'workspace_id' => $this->workspace->id,
        'sku' => 'CF-001',
        'name' => 'Cà phê rang xay',
        'unit' => 'gói',
        'price' => 120000,
        'stock_quantity' => 50,
        'is_in_stock' => true,
    ]);

    AiBot::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Bot Sale Master',
        'response_language' => 'en',
        'persona_tone' => 'friendly',
        'greeting_message' => 'Xin chào!',
        'dify_api_key' => 'app-test',
        'is_active' => true,
        'is_default' => true,
    ]);

    $this->actingAs($this->owner->fresh())
        ->get(route('app.omnichat.ai-train.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('bots.0.name', 'Bot Sale Master')
            ->where('bots.0.response_language', 'en')
        );
});

it('allows creating product in ai sale bot pricing catalog', function (): void {
    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.omnichat.ai-train.product.store'), [
            'sku' => 'CF-KING-01',
            'name' => 'King Coffee Espresso',
            'unit' => 'lon',
            'price' => 25000,
            'sale_price' => 22000,
            'stock_quantity' => 200,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('product.sku', 'CF-KING-01');

    expect(AiSaleBotProduct::query()->where('workspace_id', $this->workspace->id)->where('sku', 'CF-KING-01')->exists())->toBeTrue();
});

it('handles chat sandbox querying pricing catalog', function (): void {
    AiSaleBotProduct::query()->create([
        'workspace_id' => $this->workspace->id,
        'sku' => 'CF-LATTE',
        'name' => 'King Latte Macchiato',
        'unit' => 'hộp',
        'price' => 75000,
        'stock_quantity' => 80,
        'is_in_stock' => true,
    ]);

    $res = $this->actingAs($this->owner->fresh())
        ->postJson(route('app.omnichat.ai-train.sandbox'), [
            'message' => 'Cho mình xin giá King Latte',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($res->json('reply'))->toContain('King Latte Macchiato');
});

it('passes the configured bot name and response language to Dify in the sandbox', function (): void {
    $bot = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Trading Coach',
        'response_language' => 'ja',
    ]);

    Http::fake([
        '*/chat-messages' => Http::response(['answer' => 'こんにちは'], 200),
    ]);

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.omnichat.ai-train.sandbox'), [
            'message' => 'Hello',
            'bot_id' => $bot->id,
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'こんにちは');

    Http::assertSent(fn (Request $request): bool => $request['inputs'] === [
        'bot_name' => 'Trading Coach',
        'response_language' => 'ja',
    ]);
});

it('retries syncing an uploaded knowledge file to its configured Dify dataset', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('ai-knowledges/thuyendevm-mmo.txt', 'Tri thuc Trading da nap.');

    $bot = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'dify_dataset_id' => 'dataset-123',
        'dify_dataset_api_key' => 'dataset-secret',
    ]);
    $knowledge = AiSaleBotKnowledge::query()->create([
        'workspace_id' => $this->workspace->id,
        'ai_bot_id' => $bot->id,
        'title' => 'THUYENDEVM MMO',
        'file_type' => 'txt',
        'file_path' => 'ai-knowledges/thuyendevm-mmo.txt',
        'content' => 'Tri thuc Trading da nap.',
        'token_count' => 24,
        'status' => 'ready',
    ]);

    Http::fake([
        '*/datasets/dataset-123/document/create-by-file' => Http::response([
            'document' => ['id' => 'dify-doc-123'],
            'batch' => 'dify-batch-123',
        ]),
    ]);

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.omnichat.ai-train.knowledge.sync', $knowledge))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('knowledge.dify_document_id', 'dify-doc-123')
        ->assertJsonPath('knowledge.dify_batch_id', 'dify-batch-123');

    expect($knowledge->fresh()->ai_bot_id)->toBe($bot->id);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/datasets/dataset-123/document/create-by-file'));
});

it('re-indexes an already synced knowledge file instead of creating a duplicate', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('ai-knowledges/thuyendevm-mmo.txt', 'Noi dung da cap nhat.');

    $bot = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'dify_dataset_id' => 'dataset-123',
        'dify_dataset_api_key' => 'dataset-secret',
    ]);
    $knowledge = AiSaleBotKnowledge::query()->create([
        'workspace_id' => $this->workspace->id,
        'ai_bot_id' => $bot->id,
        'title' => 'THUYENDEVM MMO',
        'file_type' => 'txt',
        'file_path' => 'ai-knowledges/thuyendevm-mmo.txt',
        'dify_document_id' => 'existing-dify-doc',
        'content' => 'Noi dung da cap nhat.',
        'token_count' => 21,
        'status' => 'ready',
    ]);

    Http::fake([
        '*/datasets/dataset-123/documents/existing-dify-doc' => Http::response([
            'document' => ['id' => 'existing-dify-doc'],
            'batch' => 'reindex-batch-123',
        ]),
    ]);

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.omnichat.ai-train.knowledge.sync', $knowledge))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('knowledge.dify_document_id', 'existing-dify-doc')
        ->assertJsonPath('knowledge.dify_batch_id', 'reindex-batch-123');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && str_contains($request->url(), '/datasets/dataset-123/documents/existing-dify-doc'));
});

it('returns a useful error when retrying Dify sync without dataset setup', function (): void {
    $knowledge = AiSaleBotKnowledge::query()->create([
        'workspace_id' => $this->workspace->id,
        'title' => 'Chính sách tư vấn',
        'file_type' => 'manual_text',
        'content' => 'Noi dung tai lieu.',
        'token_count' => 18,
        'status' => 'ready',
    ]);

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.omnichat.ai-train.knowledge.sync', $knowledge))
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Bot chưa được cấu hình Dataset ID trên Dify.');
});
