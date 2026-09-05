<?php

declare(strict_types=1);

use App\Actions\Post\SyncPostPlatforms;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status as SocialStatus;
use App\Enums\UserWorkspace\Role;
use App\Enums\WordPress\SiteStatus;
use App\Models\Post;
use App\Models\User;
use App\Models\WordPressSite;
use App\Models\Workspace;
use App\Services\WordPress\WordPressApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('can connect and save wordpress site when credentials are valid', function () {
    Http::fake([
        'https://kingcoffee.com/wp-json/wp/v2/users/me' => Http::response([
            'id' => 42,
            'name' => 'Admin King Coffee',
        ], 200),
        'https://kingcoffee.com/wp-json/wp/v2/categories*' => Http::response([
            ['id' => 1, 'name' => 'Tin tức', 'slug' => 'tin-tuc', 'count' => 10],
        ], 200),
        'https://kingcoffee.com/wp-json/wp/v2/tags*' => Http::response([
            ['id' => 5, 'name' => 'Khuyến mãi', 'slug' => 'khuyen-mai'],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->post(route('app.wordpress.sites.store'), [
        'name' => 'King Coffee Official',
        'url' => 'https://kingcoffee.com',
        'username' => 'admin',
        'application_password' => 'abcd efgh ijkl mnop',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('wordpress_sites', [
        'workspace_id' => $this->workspace->id,
        'name' => 'King Coffee Official',
        'url' => 'https://kingcoffee.com',
        'username' => 'admin',
        'status' => 'connected',
        'wp_user_id' => 42,
        'wp_user_name' => 'Admin King Coffee',
    ]);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => SocialPlatform::WordPress->value,
        'display_name' => 'King Coffee Official',
        'username' => 'admin',
    ]);

    $site = WordPressSite::first();
    expect($site->application_password)->toBe('abcd efgh ijkl mnop');
    expect($site->categories_cache)->toHaveCount(1);
    expect($site->categories_cache[0]['name'])->toBe('Tin tức');

    // Test that SyncPostPlatforms creates a PostPlatform for this WordPress account
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    SyncPostPlatforms::execute($post, $this->user);

    $this->assertDatabaseHas('post_platforms', [
        'post_id' => $post->id,
        'platform' => SocialPlatform::WordPress->value,
        'platform_name' => 'King Coffee Official',
    ]);
});

test('fails to connect wordpress site when invalid credentials', function () {
    Http::fake([
        'https://kingcoffee.com/wp-json/wp/v2/users/me' => Http::response([
            'code' => 'rest_cannot_access',
            'message' => 'Tài khoản không hợp lệ',
        ], 401),
        'https://kingcoffee.com/index.php*' => Http::response([
            'code' => 'rest_cannot_access',
            'message' => 'Tài khoản không hợp lệ',
        ], 401),
    ]);

    $response = $this->actingAs($this->user)->post(route('app.wordpress.sites.store'), [
        'name' => 'King Coffee Official',
        'url' => 'https://kingcoffee.com',
        'username' => 'admin',
        'application_password' => 'wrong_password',
    ]);

    $response->assertSessionHasErrors(['connection']);
    expect(WordPressSite::count())->toBe(0);
});

test('can sync wordpress taxonomies as json', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://kingcoffee.com/wp-json/wp/v2/categories*' => Http::response([
            ['id' => 1, 'name' => 'Tin tức', 'slug' => 'tin-tuc', 'count' => 10],
        ]),
        'https://kingcoffee.com/wp-json/wp/v2/tags*' => Http::response([
            ['id' => 5, 'name' => 'Khuyến mãi', 'slug' => 'khuyen-mai'],
        ]),
    ]);

    $site = WordPressSite::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'King Coffee Official',
        'url' => 'https://kingcoffee.com',
        'username' => 'admin',
        'application_password' => 'abcd efgh ijkl mnop',
        'status' => SiteStatus::Connected,
    ]);

    $response = $this->actingAs($this->user)->postJson(
        route('app.wordpress.sites.sync', $site),
    );

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('categories.0.name', 'Tin tức')
        ->assertJsonPath('tags.0.name', 'Khuyến mãi');
});

test('sends post payload to the plain permalink rest route', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://kingcoffee.com/wp-json/wp/v2/posts' => Http::response([], 404),
        'https://kingcoffee.com/index.php*' => Http::response([
            'id' => 123,
            'link' => 'https://kingcoffee.com/?p=123',
        ], 201),
    ]);

    $response = app(WordPressApiClient::class)->sendRequest(
        'https://kingcoffee.com',
        'admin',
        'abcd efgh ijkl mnop',
        'posts',
        'post',
        ['title' => 'Bài viết thử nghiệm', 'status' => 'publish'],
    );

    expect($response->json('id'))->toBe(123);

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return data_get($query, 'rest_route') === '/wp/v2/posts'
            && $request['title'] === 'Bài viết thử nghiệm'
            && $request['status'] === 'publish'
            && ! array_key_exists('rest_route', $request->data());
    });
});

test('preserves the plain permalink rest route with get parameters', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://kingcoffee.com/wp-json/wp/v2/posts*' => Http::response([], 404),
        'https://kingcoffee.com/index.php*' => Http::response([
            ['id' => 123, 'status' => 'publish'],
        ]),
    ]);

    $response = app(WordPressApiClient::class)->sendRequest(
        'https://kingcoffee.com',
        'admin',
        'abcd efgh ijkl mnop',
        'posts',
        params: ['include' => [123], 'context' => 'edit'],
    );

    expect($response->json('0.id'))->toBe(123);

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return data_get($query, 'rest_route') === '/wp/v2/posts'
            && data_get($query, 'include') === ['123']
            && data_get($query, 'context') === 'edit';
    });
});

test('can update wordpress site configuration and keep existing app password when blank', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://newcoffee.com/wp-json/wp/v2/users/me' => Http::response([
            'id' => 77,
            'name' => 'Updated Admin',
        ], 200),
        'https://newcoffee.com/wp-json/wp/v2/categories*' => Http::response([
            ['id' => 2, 'name' => 'Blog', 'slug' => 'blog', 'count' => 3],
        ], 200),
        'https://newcoffee.com/wp-json/wp/v2/tags*' => Http::response([
            ['id' => 8, 'name' => 'Deal', 'slug' => 'deal'],
        ], 200),
    ]);

    $site = WordPressSite::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Old Coffee',
        'url' => 'https://oldcoffee.com',
        'username' => 'old-admin',
        'application_password' => 'old app password',
        'status' => SiteStatus::Connected,
    ]);

    $this->workspace->socialAccounts()->create([
        'platform' => SocialPlatform::WordPress,
        'platform_user_id' => 'https://oldcoffee.com',
        'username' => 'old-admin',
        'display_name' => 'Old Coffee',
        'access_token' => 'old app password',
        'status' => SocialStatus::Connected,
        'connected_by_user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('app.wordpress.sites.update', $site), [
        'name' => 'New Coffee',
        'url' => 'https://newcoffee.com',
        'username' => 'new-admin',
        'application_password' => '',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $site->refresh();
    expect($site->name)->toBe('New Coffee')
        ->and($site->url)->toBe('https://newcoffee.com')
        ->and($site->username)->toBe('new-admin')
        ->and($site->application_password)->toBe('old app password')
        ->and($site->wp_user_id)->toBe(77)
        ->and($site->categories_cache)->toHaveCount(1);

    $this->assertDatabaseHas('social_accounts', [
        'workspace_id' => $this->workspace->id,
        'platform' => SocialPlatform::WordPress->value,
        'platform_user_id' => 'https://newcoffee.com',
        'display_name' => 'New Coffee',
        'username' => 'new-admin',
    ]);
});

test('can delete connected wordpress site and sync social account deletion', function () {
    $site = WordPressSite::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'King Coffee Official',
        'url' => 'https://kingcoffee.com',
        'username' => 'admin',
        'application_password' => 'abcd efgh ijkl mnop',
        'status' => SiteStatus::Connected,
    ]);

    $this->workspace->socialAccounts()->create([
        'platform' => SocialPlatform::WordPress,
        'platform_user_id' => 'https://kingcoffee.com',
        'username' => 'admin',
        'display_name' => 'King Coffee Official',
        'access_token' => 'abcd efgh ijkl mnop',
        'status' => SocialStatus::Connected,
        'connected_by_user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->delete(route('app.wordpress.sites.destroy', $site));

    $response->assertRedirect();
    expect(WordPressSite::count())->toBe(0);
    $this->assertDatabaseMissing('social_accounts', [
        'platform' => SocialPlatform::WordPress->value,
        'platform_user_id' => 'https://kingcoffee.com',
    ]);
});
