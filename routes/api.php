<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\DocsController;
use App\Http\Controllers\Api\LabelController;
use App\Http\Controllers\Api\Omnichat\ConversationTagController as OmnichatConversationTagController;
use App\Http\Controllers\Api\Omnichat\TagController as OmnichatTagController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\SignatureController;
use App\Http\Controllers\Api\SocialAccountController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\WebsiteChat\ConfigController as WebsiteChatConfigController;
use App\Http\Controllers\Api\WebsiteChat\ConversationController as WebsiteChatConversationController;
use App\Http\Controllers\Api\WebsiteChat\MessageController as WebsiteChatMessageController;
use App\Http\Controllers\Api\WebsiteChat\SessionController as WebsiteChatSessionController;
use App\Http\Controllers\Api\WebsiteChat\WebhookController as WebsiteChatWebhookController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::post('/uploads/{token}', [UploadController::class, 'store'])
    ->middleware(['signed', 'throttle:signed-uploads'])
    ->whereUuid('token')
    ->name('api.uploads.store');

Route::get('/docs', DocsController::class)->name('api.docs');

Route::prefix('website-chat/v1')->middleware('throttle:120,1')->group(function (): void {
    Route::get('config', WebsiteChatConfigController::class)->name('api.website-chat.config');
    Route::post('sessions', [WebsiteChatSessionController::class, 'store'])->name('api.website-chat.sessions.store');
    Route::delete('sessions/current', [WebsiteChatSessionController::class, 'destroy'])->name('api.website-chat.sessions.destroy');
    Route::get('messages', [WebsiteChatMessageController::class, 'index'])->name('api.website-chat.messages.index');
    Route::post('messages', [WebsiteChatMessageController::class, 'store'])->name('api.website-chat.messages.store');
});

Route::middleware(['auth:api', 'workspace.token', 'throttle:api'])->group(function () {
    // Posts
    Route::get('/posts', [PostController::class, 'index'])->name('api.posts.index');
    Route::post('/posts', [PostController::class, 'store'])->name('api.posts.store');
    Route::get('/posts/{post}', [PostController::class, 'show'])->name('api.posts.show');
    Route::put('/posts/{post}', [PostController::class, 'update'])->name('api.posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('api.posts.destroy');
    Route::post('/posts/{post}/media', [PostController::class, 'storeMedia'])->name('api.posts.store-media');
    Route::post('/posts/{post}/media/from-url', [PostController::class, 'attachMediaFromUrl'])->name('api.posts.attach-media-from-url');
    Route::get('/posts/{post}/metrics', [PostController::class, 'metrics'])->name('api.posts.metrics');
    Route::get('/posts/{post}/preview', [PostController::class, 'preview'])->name('api.posts.preview');

    // Platforms (read-only metadata)
    Route::get('/content-types', [PlatformController::class, 'contentTypes'])->name('api.content-types');

    // Workspace
    Route::get('/workspace', [WorkspaceController::class, 'show'])->name('api.workspace.show');

    // Signatures
    Route::get('/signatures', [SignatureController::class, 'index'])->name('api.signatures.index');
    Route::post('/signatures', [SignatureController::class, 'store'])->name('api.signatures.store');
    Route::put('/signatures/{signature}', [SignatureController::class, 'update'])->name('api.signatures.update');
    Route::delete('/signatures/{signature}', [SignatureController::class, 'destroy'])->name('api.signatures.destroy');

    // Labels
    Route::get('/labels', [LabelController::class, 'index'])->name('api.labels.index');
    Route::post('/labels', [LabelController::class, 'store'])->name('api.labels.store');
    Route::put('/labels/{label}', [LabelController::class, 'update'])->name('api.labels.update');
    Route::delete('/labels/{label}', [LabelController::class, 'destroy'])->name('api.labels.destroy');

    // Social Accounts
    Route::get('/social-accounts', [SocialAccountController::class, 'index'])->name('api.social-accounts.index');
    Route::put('/social-accounts/{account}/toggle', [SocialAccountController::class, 'toggle'])->name('api.social-accounts.toggle');
    Route::get('/social-accounts/{account}/boards', [SocialAccountController::class, 'boards'])
        ->middleware('throttle:60,1')
        ->name('api.social-accounts.boards');
    Route::get('/social-accounts/{account}/channels', [SocialAccountController::class, 'channels'])
        ->middleware('throttle:60,1')
        ->name('api.social-accounts.channels');

    // API Keys
    Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api.api-keys.index');
    Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api.api-keys.store');
    Route::delete('/api-keys/{apiToken}', [ApiKeyController::class, 'destroy'])->name('api.api-keys.destroy');

    // Omnichat partner integration
    Route::get('/omnichat/tags', [OmnichatTagController::class, 'index'])->name('api.omnichat.tags.index');
    Route::post('/omnichat/tags', [OmnichatTagController::class, 'store'])->name('api.omnichat.tags.store');
    Route::put('/omnichat/conversations/{conversation}/tags', [OmnichatConversationTagController::class, 'update'])
        ->name('api.omnichat.conversations.tags.update');
    Route::get('/website-chat/v1/channels/{channel}/webhook', [WebsiteChatWebhookController::class, 'show'])
        ->name('api.website-chat.webhook.show');
    Route::get('/website-chat/v1/channels/{channel}/conversations', [WebsiteChatConversationController::class, 'index'])
        ->name('api.website-chat.conversations.index');
    Route::put('/website-chat/v1/channels/{channel}/webhook', [WebsiteChatWebhookController::class, 'update'])
        ->name('api.website-chat.webhook.update');
    Route::delete('/website-chat/v1/channels/{channel}/webhook', [WebsiteChatWebhookController::class, 'destroy'])
        ->name('api.website-chat.webhook.destroy');
});
