<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\AiBot;
use App\Models\AiSaleBotKnowledge;
use App\Models\AiSaleBotProduct;
use App\Models\User;
use App\Models\Workspace;

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
        'persona_tone' => 'friendly',
        'greeting_message' => 'Xin chào!',
        'dify_api_key' => 'app-test',
        'is_active' => true,
        'is_default' => true,
    ]);

    $this->actingAs($this->owner->fresh())
        ->get(route('app.omnichat.ai-train.index'))
        ->assertOk();
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
