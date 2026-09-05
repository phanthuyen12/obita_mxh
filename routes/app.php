<?php

declare(strict_types=1);

use App\Http\Controllers\App\AnalyticsController;
use App\Http\Controllers\App\ApiKeyController;
use App\Http\Controllers\App\AssetController;
use App\Http\Controllers\App\AutomationController;
use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\ContentCloneCampaignController;
use App\Http\Controllers\App\ContentWorkflowController;
use App\Http\Controllers\App\DiscordController as AppDiscordController;
use App\Http\Controllers\App\FolderController;
use App\Http\Controllers\App\GiphyController;
use App\Http\Controllers\App\LinkPreviewController;
use App\Http\Controllers\App\McpSettingsController;
use App\Http\Controllers\App\NotificationController;
use App\Http\Controllers\App\Omnichat\AnalyticsController as OmnichatAnalyticsController;
use App\Http\Controllers\App\Omnichat\ConversationAssignmentController;
use App\Http\Controllers\App\Omnichat\ConversationReadController;
use App\Http\Controllers\App\Omnichat\ConversationTagController;
use App\Http\Controllers\App\Omnichat\InboxController as OmnichatInboxController;
use App\Http\Controllers\App\Omnichat\LeadController as OmnichatLeadController;
use App\Http\Controllers\App\Omnichat\MessageController as OmnichatMessageController;
use App\Http\Controllers\App\Omnichat\ShopeeSyncController;
use App\Http\Controllers\App\Omnichat\TagController as OmnichatTagController;
use App\Http\Controllers\App\Omnichat\ViewController as OmnichatViewController;
use App\Http\Controllers\App\Omnichat\WebsiteChatController;
use App\Http\Controllers\App\OnboardingController;
use App\Http\Controllers\App\PostAiCreateController;
use App\Http\Controllers\App\PostAiGenerateController;
use App\Http\Controllers\App\PostAiRegenerateMediaController;
use App\Http\Controllers\App\PostAiReviewController;
use App\Http\Controllers\App\PostAnalyticsController;
use App\Http\Controllers\App\PostCommentController;
use App\Http\Controllers\App\PostController;
use App\Http\Controllers\App\PostTagController;
use App\Http\Controllers\App\PostTemplateController;
use App\Http\Controllers\App\PresenceController;
use App\Http\Controllers\App\Settings\AccountController;
use App\Http\Controllers\App\Settings\AiSettingsController;
use App\Http\Controllers\App\Settings\AuthenticationController;
use App\Http\Controllers\App\Settings\NotificationPreferenceController;
use App\Http\Controllers\App\Settings\ProfileController;
use App\Http\Controllers\App\Settings\SettingsController;
use App\Http\Controllers\App\Settings\UsageController;
use App\Http\Controllers\App\SocialAccountGroupController;
use App\Http\Controllers\App\TeamController;
use App\Http\Controllers\App\UnsplashController;
use App\Http\Controllers\App\WelcomeController;
use App\Http\Controllers\App\WordPressSiteController;
use App\Http\Controllers\App\WorkspaceAssignmentController;
use App\Http\Controllers\App\WorkspaceController;
use App\Http\Controllers\App\WorkspaceInviteController;
use App\Http\Controllers\App\WorkspaceLabelController;
use App\Http\Controllers\App\WorkspaceSignatureController;
use App\Http\Controllers\Auth\BlueskyController;
use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\Auth\FacebookController;
use App\Http\Controllers\Auth\InstagramController;
use App\Http\Controllers\Auth\InstagramFacebookController;
use App\Http\Controllers\Auth\LazadaController;
use App\Http\Controllers\Auth\LinkedInController;
use App\Http\Controllers\Auth\MastodonController;
use App\Http\Controllers\Auth\PinterestController;
use App\Http\Controllers\Auth\ShopeeController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Auth\TelegramController;
use App\Http\Controllers\Auth\ThreadsController;
use App\Http\Controllers\Auth\TikTokController;
use App\Http\Controllers\Auth\XController;
use App\Http\Controllers\Auth\YouTubeController;
use App\Http\Controllers\Auth\ZaloOaController;
use App\Http\Controllers\OmnichatChannelAccessController;
use App\Http\Controllers\SocialAccountAccessController;
use App\Http\Middleware\App\EnsureAccountReady;
use App\Http\Middleware\App\EnsureHasWorkspace;
use Illuminate\Support\Facades\Route;

// Subscription selection (requires auth but not subscription)
Route::middleware(['auth'])->group(function () {

    Route::get('/', function () {
        return redirect()->route('app.calendar');
    })->name('app.home');

    Route::get('subscribe', [BillingController::class, 'subscribe'])->name('app.subscribe');
    Route::get('welcome', fn () => redirect()->route('app.welcome.persona'))->name('app.welcome');
    Route::get('welcome/persona', [WelcomeController::class, 'persona'])->name('app.welcome.persona');
    Route::post('welcome/persona', [WelcomeController::class, 'storePersona'])->name('app.welcome.persona.store');
    Route::get('welcome/goals', [WelcomeController::class, 'goals'])->name('app.welcome.goals');
    Route::post('welcome/goals', [WelcomeController::class, 'storeGoals'])->name('app.welcome.goals.store');
    Route::get('welcome/referral-source', [WelcomeController::class, 'referralSource'])->name('app.welcome.referral-source');
    Route::get('welcome/subscription-required', [WelcomeController::class, 'subscriptionRequired'])->name('app.welcome.subscription-required');
    Route::post('welcome/referral-source', [WelcomeController::class, 'storeReferralSource'])
        ->middleware('throttle:6,1')
        ->name('app.welcome.referral-source.store');
    Route::get('billing/processing', [BillingController::class, 'processing'])->name('app.billing.processing');

    Route::get('workspaces/create', [WorkspaceController::class, 'create'])->name('app.workspaces.create');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('app.workspaces.store');
    Route::post('workspaces/autofill', [WorkspaceController::class, 'autofillBrand'])
        ->middleware('throttle:10,1')
        ->name('app.workspaces.autofill');

    Route::get('workspace/members/search', [WorkspaceController::class, 'searchMembers'])
        ->middleware('throttle:60,1')
        ->name('app.workspace.members.search');

    Route::post('presence/heartbeat', [PresenceController::class, 'heartbeat'])
        ->name('app.presence.heartbeat');
});

// Social Connect routes
Route::middleware(['auth'])->group(function () {
    // Starting a connection reads the user's current workspace, so these require
    // one — during onboarding they redirect to workspace creation. Disconnecting
    // lives here too (and not behind EnsureAccountReady) so it works before a
    // subscription exists; the controller still authorizes workspace ownership.
    Route::middleware(EnsureHasWorkspace::class)->group(function () {
        Route::get('connect/lazada', [LazadaController::class, 'connect'])->name('app.social.lazada.connect');
        Route::get('connect/shopee', [ShopeeController::class, 'connect'])->name('app.social.shopee.connect');
        Route::get('connect/linkedin', [LinkedInController::class, 'connect'])->name('app.social.linkedin.connect');
        Route::get('connect/x', [XController::class, 'connect'])->name('app.social.x.connect');
        Route::get('connect/tiktok', [TikTokController::class, 'connect'])->name('app.social.tiktok.connect');
        Route::get('connect/youtube', [YouTubeController::class, 'connect'])->name('app.social.youtube.connect');
        Route::get('connect/facebook', [FacebookController::class, 'connect'])->name('app.social.facebook.connect');
        Route::get('connect/zalo-oa', [ZaloOaController::class, 'connect'])->name('app.social.zalo-oa.connect');
        Route::get('connect/instagram', [InstagramController::class, 'connect'])->name('app.social.instagram.connect');
        Route::get('connect/instagram-facebook', [InstagramFacebookController::class, 'connect'])->name('app.social.instagram-facebook.connect');
        Route::get('connect/threads', [ThreadsController::class, 'connect'])->name('app.social.threads.connect');
        Route::get('connect/pinterest', [PinterestController::class, 'connect'])->name('app.social.pinterest.connect');
        Route::get('connect/bluesky', [BlueskyController::class, 'connect'])->name('app.social.bluesky.connect');
        Route::post('connect/bluesky', [BlueskyController::class, 'store'])->name('app.social.bluesky.store');
        Route::get('connect/mastodon', [MastodonController::class, 'connect'])->name('app.social.mastodon.connect');
        Route::post('connect/mastodon', [MastodonController::class, 'authorizeInstance'])->name('app.social.mastodon.authorize');
        Route::post('connect/telegram', [TelegramController::class, 'connect'])->name('app.social.telegram.connect');
        Route::get('connect/discord', [DiscordController::class, 'connect'])->name('app.social.discord.connect');

        Route::delete('accounts', [SocialController::class, 'batchDisconnect'])->name('app.accounts.batch-disconnect');
        Route::delete('accounts/{account}', [SocialController::class, 'disconnect'])->name('app.accounts.disconnect');
    });

    // OAuth callbacks and identity selection resolve their workspace from the
    // session set when the flow started, then self-close the popup. They run
    // without the current-workspace gate so a momentarily missing current
    // workspace can't HTML-redirect the popup instead of closing it cleanly.
    Route::get('lazada/callback', [LazadaController::class, 'callback'])->name('app.social.lazada.callback');
    Route::get('shopee/callback/{state?}', [ShopeeController::class, 'callback'])->name('app.social.shopee.callback');
    Route::get('accounts/linkedin/callback', [LinkedInController::class, 'callback'])->name('app.social.linkedin.callback');
    Route::get('accounts/linkedin/select', [LinkedInController::class, 'selectIdentity'])->name('app.social.linkedin.select-identity');
    Route::post('accounts/linkedin/select', [LinkedInController::class, 'select'])->name('app.social.linkedin.select');

    Route::get('accounts/x/callback', [XController::class, 'callback'])->name('app.social.x.callback');

    Route::get('accounts/tiktok/callback', [TikTokController::class, 'callback'])->name('app.social.tiktok.callback');

    Route::get('accounts/youtube/callback', [YouTubeController::class, 'callback'])->name('app.social.youtube.callback');
    Route::get('accounts/youtube/select', [YouTubeController::class, 'selectChannel'])->name('app.social.youtube.select-channel');
    Route::post('accounts/youtube/select', [YouTubeController::class, 'select'])->name('app.social.youtube.select');

    Route::get('accounts/facebook/callback', [FacebookController::class, 'callback'])->name('app.social.facebook.callback');
    Route::get('zalo-oa/callback', [ZaloOaController::class, 'callback'])->name('app.social.zalo-oa.callback');
    Route::get('accounts/facebook/select', [FacebookController::class, 'selectPage'])->name('app.social.facebook.select-page');
    Route::post('accounts/facebook/select', [FacebookController::class, 'select'])->name('app.social.facebook.select');

    Route::get('accounts/instagram/callback', [InstagramController::class, 'callback'])->name('app.social.instagram.callback');
    Route::get('accounts/instagram/select', [InstagramController::class, 'selectAccount'])->name('app.social.instagram.select-account');
    Route::post('accounts/instagram/select', [InstagramController::class, 'select'])->name('app.social.instagram.select');

    Route::get('accounts/instagram-facebook/callback', [InstagramFacebookController::class, 'callback'])->name('app.social.instagram-facebook.callback');
    Route::get('accounts/instagram-facebook/select-page', [InstagramFacebookController::class, 'selectPage'])->name('app.social.instagram-facebook.select-page');
    Route::post('accounts/instagram-facebook/select', [InstagramFacebookController::class, 'select'])->name('app.social.instagram-facebook.select');

    Route::get('accounts/threads/callback', [ThreadsController::class, 'callback'])->name('app.social.threads.callback');

    Route::get('accounts/pinterest/callback', [PinterestController::class, 'callback'])->name('app.social.pinterest.callback');

    Route::get('accounts/mastodon/callback', [MastodonController::class, 'callback'])->name('app.social.mastodon.callback');

    Route::get('accounts/discord/callback', [DiscordController::class, 'callback'])->name('app.social.discord.callback');
});

// Routes that require account access and a current workspace
Route::middleware(['auth', EnsureAccountReady::class, EnsureHasWorkspace::class])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'index'])->name('app.onboarding');
    Route::post('onboarding/mcp/skip', [OnboardingController::class, 'skipMcp'])->name('app.onboarding.mcp.skip');
    Route::post('onboarding/complete', [OnboardingController::class, 'complete'])->name('app.onboarding.complete');

    // Discord — live lookups for the composer (channel picker + mention autocomplete).
    // Throttled because they proxy the shared bot's (rate-limited) Discord API.
    Route::get('discord/accounts/{account}/channels', [AppDiscordController::class, 'channels'])
        ->middleware('throttle:60,1')
        ->name('app.discord.channels');
    Route::get('discord/accounts/{account}/mentions', [AppDiscordController::class, 'mentions'])
        ->middleware('throttle:60,1')
        ->name('app.discord.mentions');

    // Workspaces
    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('app.workspaces.index');
    Route::post('workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('app.workspaces.switch');
    Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('app.workspaces.destroy');

    // Workspace settings
    Route::get('settings/workspace', [WorkspaceController::class, 'settings'])->name('app.workspace.settings');
    Route::put('settings/workspace', [WorkspaceController::class, 'updateSettings'])->name('app.workspace.settings.update');
    Route::post('settings/workspace/logo', [WorkspaceController::class, 'uploadLogo'])->name('app.workspace.upload-logo');
    Route::delete('settings/workspace/logo', [WorkspaceController::class, 'deleteLogo'])->name('app.workspace.delete-logo');

    // Brand settings
    Route::get('settings/workspace/brand', [WorkspaceController::class, 'brandSettings'])->name('app.workspace.brand');
    Route::get('settings/workspace/assignments', [WorkspaceAssignmentController::class, 'index'])->name('app.workspace.assignments');

    // Social Accounts
    Route::get('accounts', [SocialController::class, 'index'])->name('app.accounts');
    Route::get('accounts/browser', [SocialController::class, 'browser'])->name('app.accounts.browser');
    Route::put('accounts/{account}/toggle', [SocialController::class, 'toggleActive'])->name('app.accounts.toggle');
    Route::put('accounts/{account}/access', [SocialAccountAccessController::class, 'update'])->name('app.accounts.access.update');
    Route::put('omnichat-channels/{channel}/access', [OmnichatChannelAccessController::class, 'update'])
        ->name('app.omnichat-channels.access.update');
    Route::post('account-groups', [SocialAccountGroupController::class, 'store'])->name('app.account-groups.store');
    Route::get('account-groups/{group}', [SocialAccountGroupController::class, 'show'])->name('app.account-groups.show');
    Route::put('account-groups/{group}', [SocialAccountGroupController::class, 'update'])->name('app.account-groups.update');
    Route::delete('account-groups/{group}', [SocialAccountGroupController::class, 'destroy'])->name('app.account-groups.destroy');

    // Analytics
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('app.analytics');
    Route::get('analytics/{account}', [AnalyticsController::class, 'show'])->name('app.analytics.show');
    Route::get('post-analytics', [PostAnalyticsController::class, 'index'])->name('app.post-analytics.index');
    Route::get('post-analytics/export', [PostAnalyticsController::class, 'export'])->name('app.post-analytics.export');
    Route::get('post-analytics/{post}', [PostAnalyticsController::class, 'show'])->name('app.post-analytics.show');
    Route::post('post-analytics/facebook/{account}/sync', [PostAnalyticsController::class, 'syncFacebook'])->name('app.post-analytics.facebook.sync');
    Route::post('post-analytics/facebook/sync-all', [PostAnalyticsController::class, 'syncAllFacebook'])->name('app.post-analytics.facebook.sync-all');
    Route::post('post-analytics/youtube/{account}/sync', [PostAnalyticsController::class, 'syncYouTube'])->name('app.post-analytics.youtube.sync');
    Route::post('post-analytics/youtube/sync-all', [PostAnalyticsController::class, 'syncAllYouTube'])->name('app.post-analytics.youtube.sync-all');
    Route::post('post-analytics/tiktok/{account}/sync', [PostAnalyticsController::class, 'syncTikTok'])->name('app.post-analytics.tiktok.sync');
    Route::post('post-analytics/tiktok/sync-all', [PostAnalyticsController::class, 'syncAllTikTok'])->name('app.post-analytics.tiktok.sync-all');

    // Omnichat
    Route::get('omnichat', [OmnichatInboxController::class, 'index'])->name('app.omnichat.index');
    Route::get('omnichat/website-chat', [WebsiteChatController::class, 'index'])->name('app.omnichat.website-chat.index');

    // WordPress Sites
    Route::get('wordpress/sites', [WordPressSiteController::class, 'index'])->name('app.wordpress.sites.index');
    Route::post('wordpress/sites', [WordPressSiteController::class, 'store'])->name('app.wordpress.sites.store');
    Route::put('wordpress/sites/{site}', [WordPressSiteController::class, 'update'])->name('app.wordpress.sites.update');
    Route::delete('wordpress/sites/{site}', [WordPressSiteController::class, 'destroy'])->name('app.wordpress.sites.destroy');
    Route::post('wordpress/sites/{site}/test', [WordPressSiteController::class, 'testConnection'])->name('app.wordpress.sites.test');
    Route::post('wordpress/sites/{site}/sync', [WordPressSiteController::class, 'sync'])->name('app.wordpress.sites.sync');
    Route::post('omnichat/website-chat', [WebsiteChatController::class, 'store'])->name('app.omnichat.website-chat.store');
    Route::put('omnichat/website-chat/{channel}', [WebsiteChatController::class, 'update'])->name('app.omnichat.website-chat.update');
    Route::post('omnichat/website-chat/{channel}/rotate', [WebsiteChatController::class, 'rotate'])->name('app.omnichat.website-chat.rotate');
    Route::delete('omnichat/website-chat/{channel}', [WebsiteChatController::class, 'destroy'])->name('app.omnichat.website-chat.destroy');
    Route::get('omnichat/analytics', [OmnichatAnalyticsController::class, 'index'])->name('app.omnichat.analytics');
    Route::get('omnichat/analytics/users/{user}', [OmnichatAnalyticsController::class, 'userShow'])->name('app.omnichat.analytics.user');
    Route::get('omnichat/analytics/export', [OmnichatAnalyticsController::class, 'export'])->name('app.omnichat.analytics.export');
    Route::put('omnichat/view', OmnichatViewController::class)->name('app.omnichat.view.update');
    Route::get('omnichat/leads', [OmnichatLeadController::class, 'index'])->name('app.omnichat.leads.index');
    Route::patch('omnichat/leads/{contact}', [OmnichatLeadController::class, 'update'])->name('app.omnichat.leads.update');
    Route::post('omnichat/tags', [OmnichatTagController::class, 'store'])->name('app.omnichat.tags.store');
    Route::delete('omnichat/tags/{tag}', [OmnichatTagController::class, 'destroy'])->name('app.omnichat.tags.destroy');
    Route::put('omnichat/conversations/{conversation}/tags', [ConversationTagController::class, 'update'])->name('app.omnichat.conversations.tags.update');
    Route::post('omnichat/conversations/{conversation}/messages', [OmnichatMessageController::class, 'store'])
        ->middleware('throttle:120,1')
        ->name('app.omnichat.messages.store');
    Route::post('omnichat/shopee/{account}/sync', ShopeeSyncController::class)->middleware('throttle:10,1')->name('app.omnichat.shopee.sync');
    Route::post('omnichat/conversations/{conversation}/read', ConversationReadController::class)->middleware('throttle:120,1')->name('app.omnichat.conversations.read');
    Route::put('omnichat/conversations/{conversation}/assignment', [ConversationAssignmentController::class, 'update'])->name('app.omnichat.conversations.assignment.update');

    Route::get('content-workflows', [ContentWorkflowController::class, 'index'])->name('app.content-workflows.index');
    Route::post('content-workflows', [ContentWorkflowController::class, 'store'])->name('app.content-workflows.store');
    Route::patch('content-workflows/{workflow}', [ContentWorkflowController::class, 'update'])->name('app.content-workflows.update');
    Route::delete('content-workflows/{workflow}', [ContentWorkflowController::class, 'destroy'])->name('app.content-workflows.destroy');

    Route::get('content-clones', [ContentCloneCampaignController::class, 'index'])->name('app.content-clones.index');
    Route::post('content-clones/preview', [ContentCloneCampaignController::class, 'preview'])
        ->middleware('throttle:10,1')
        ->name('app.content-clones.preview');
    Route::get('content-clones/preview-status/{taskId}', [ContentCloneCampaignController::class, 'previewStatus'])
        ->name('app.content-clones.preview-status');
    Route::post('content-clones/generate-scene-image', [ContentCloneCampaignController::class, 'generateSceneImage'])
        ->name('app.content-clones.generate-scene-image');
    Route::post('content-clones/generate-scene-video', [ContentCloneCampaignController::class, 'generateSceneVideo'])
        ->name('app.content-clones.generate-scene-video');
    Route::post('content-clones/generate-scene-voiceover', [ContentCloneCampaignController::class, 'generateSceneVoiceover'])
        ->name('app.content-clones.generate-scene-voiceover');
    Route::post('content-clones/generate-video-scenes', [ContentCloneCampaignController::class, 'generateVideoScenes'])
        ->name('app.content-clones.generate-video-scenes');
    Route::post('content-test-videos', [ContentCloneCampaignController::class, 'testVideosDify'])
        ->withoutMiddleware(['auth', EnsureAccountReady::class, EnsureHasWorkspace::class])
        ->name('app.content-clones.generate-scene-video-test');
    Route::post('content-clones/stitch-video', [ContentCloneCampaignController::class, 'stitchVideo'])
        ->name('app.content-clones.stitch-video');
    Route::post('content-clones', [ContentCloneCampaignController::class, 'store'])->name('app.content-clones.store');
    Route::delete('content-clones/{campaign}', [ContentCloneCampaignController::class, 'destroy'])->name('app.content-clones.destroy');

    // Calendar
    Route::get('calendar', [PostController::class, 'calendar'])->name('app.calendar');

    // Posts
    Route::get('posts/{status?}', [PostController::class, 'index'])->name('app.posts.index')->where('status', 'draft|scheduled|published');
    Route::get('posts/create', [PostController::class, 'create'])->name('app.posts.create');
    Route::post('posts', [PostController::class, 'store'])->name('app.posts.store');
    Route::get('posts/{post}/channels', [PostController::class, 'channels'])->name('app.posts.channels');
    Route::put('posts/{post}/folder', [PostController::class, 'move'])->name('app.posts.folder.update');
    Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('app.posts.edit');
    Route::get('posts/{post}', [PostController::class, 'show'])->name('app.posts.show');
    Route::get('posts/{post}/platforms/{postPlatform}/metrics', [PostController::class, 'platformMetrics'])->name('app.posts.platforms.metrics');
    Route::put('posts/{post}', [PostController::class, 'update'])->name('app.posts.update');
    Route::post('posts/{post}/workflow/submit', [PostController::class, 'submitForReview'])->name('app.posts.workflow.submit');
    Route::post('posts/{post}/workflow/approve', [PostController::class, 'approveWorkflow'])->name('app.posts.workflow.approve');
    Route::post('posts/{post}/workflow/reject', [PostController::class, 'rejectWorkflow'])->name('app.posts.workflow.reject');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('app.posts.destroy');
    Route::post('posts/{post}/duplicate', [PostController::class, 'duplicate'])->name('app.posts.duplicate');
    Route::post('posts/link-preview', LinkPreviewController::class)
        ->middleware('throttle:30,1')
        ->name('app.posts.link-preview');

    // Post Templates
    Route::get('post-templates', [PostTemplateController::class, 'index'])->name('app.post-templates.index');
    Route::post('post-templates/{slug}/apply', [PostTemplateController::class, 'apply'])->name('app.post-templates.apply');

    // Post AI
    Route::post('posts/{post}/ai/generate', [PostAiGenerateController::class, 'generate'])->name('app.posts.ai.generate');
    Route::post('posts/{post}/media/{mediaId}/ai/regenerate', [PostAiRegenerateMediaController::class, 'regenerate'])->name('app.posts.ai.regenerate-media');
    Route::post('posts/{post}/ai/review', [PostAiReviewController::class, 'review'])->name('app.posts.ai.review');
    Route::post('posts/ai/create', [PostAiCreateController::class, 'start'])->name('app.posts.ai.create');
    Route::get('posts/ai/{creationId}/loading', [PostAiCreateController::class, 'loading'])->name('app.posts.ai.loading')->whereUuid('creationId');

    // Post Comments
    Route::get('posts/{post}/comments', [PostCommentController::class, 'index'])->name('app.posts.comments.index');
    Route::post('posts/{post}/comments', [PostCommentController::class, 'store'])->name('app.posts.comments.store');
    Route::put('posts/{post}/comments/{comment}', [PostCommentController::class, 'update'])->name('app.posts.comments.update');
    Route::delete('posts/{post}/comments/{comment}', [PostCommentController::class, 'destroy'])->name('app.posts.comments.destroy');
    Route::post('posts/{post}/comments/{comment}/react', [PostCommentController::class, 'react'])->name('app.posts.comments.react');

    // Members
    Route::get('settings/workspace/members', [WorkspaceInviteController::class, 'index'])->name('app.members');
    Route::post('settings/workspace/members/invites', [WorkspaceInviteController::class, 'store'])->name('app.invites.store');
    Route::delete('settings/workspace/members/invites/{invite}', [WorkspaceInviteController::class, 'destroy'])->name('app.invites.destroy');
    Route::delete('settings/workspace/members/{user}', [WorkspaceInviteController::class, 'removeMember'])->name('app.members.remove');
    Route::put('settings/workspace/members/{user}/role', [WorkspaceInviteController::class, 'updateRole'])->name('app.members.update-role');

    // Teams
    Route::get('settings/workspace/teams', [TeamController::class, 'index'])->name('app.teams.index');
    Route::get('settings/workspace/teams/members', [TeamController::class, 'members'])->name('app.teams.members');
    Route::get('settings/workspace/teams/{team}/member-ids', [TeamController::class, 'memberIds'])->name('app.teams.member-ids');
    Route::post('settings/workspace/teams', [TeamController::class, 'store'])->name('app.teams.store');
    Route::put('settings/workspace/teams/{team}', [TeamController::class, 'update'])->name('app.teams.update');
    Route::delete('settings/workspace/teams/{team}', [TeamController::class, 'destroy'])->name('app.teams.destroy');

    // Signatures
    Route::get('signatures', [WorkspaceSignatureController::class, 'index'])->name('app.signatures.index');
    Route::post('signatures', [WorkspaceSignatureController::class, 'store'])->name('app.signatures.store');
    Route::put('signatures/{signature}', [WorkspaceSignatureController::class, 'update'])->name('app.signatures.update');
    Route::delete('signatures/{signature}', [WorkspaceSignatureController::class, 'destroy'])->name('app.signatures.destroy');

    // Assets
    Route::get('folders', [FolderController::class, 'index'])->name('app.folders.index');
    Route::get('folders/manage', [FolderController::class, 'manage'])->name('app.folders.manage');
    Route::get('folders/subjects', [FolderController::class, 'subjects'])->name('app.folders.subjects');
    Route::get('folders/{folder}/permissions', [FolderController::class, 'permissions'])->name('app.folders.permissions.index');
    Route::post('folders', [FolderController::class, 'store'])->name('app.folders.store');
    Route::put('folders/{folder}', [FolderController::class, 'update'])->name('app.folders.update');
    Route::delete('folders/{folder}', [FolderController::class, 'destroy'])->name('app.folders.destroy');
    Route::put('folders/{folder}/permissions', [FolderController::class, 'assignPermissions'])->name('app.folders.permissions.update');

    Route::get('assets', [AssetController::class, 'index'])->name('app.assets.index');
    Route::get('assets/search', [AssetController::class, 'search'])->name('app.assets.search');
    Route::post('assets', [AssetController::class, 'store'])->name('app.assets.store');
    Route::post('assets/chunked', [AssetController::class, 'storeChunked'])->name('app.assets.store-chunked');
    Route::post('assets/from-url', [AssetController::class, 'storeFromUrl'])->name('app.assets.store-from-url');
    Route::delete('assets/{media}', [AssetController::class, 'destroy'])->name('app.assets.destroy');
    Route::put('assets/{media}/folder', [AssetController::class, 'move'])->name('app.assets.folder.update');
    Route::put('assets/{media}/tags', [AssetController::class, 'updateTags'])->name('app.assets.tags.update');
    Route::get('assets/unsplash/search', [UnsplashController::class, 'search'])->name('app.assets.unsplash.search');
    Route::get('assets/unsplash/trending', [UnsplashController::class, 'trending'])->name('app.assets.unsplash.trending');
    Route::get('assets/giphy/search', [GiphyController::class, 'search'])->name('app.assets.giphy.search');
    Route::get('assets/giphy/trending', [GiphyController::class, 'trending'])->name('app.assets.giphy.trending');

    // Labels
    Route::get('labels', [WorkspaceLabelController::class, 'index'])->name('app.labels.index');
    Route::post('labels', [WorkspaceLabelController::class, 'store'])->name('app.labels.store');
    Route::put('labels/{label}', [WorkspaceLabelController::class, 'update'])->name('app.labels.update');
    Route::delete('labels/{label}', [WorkspaceLabelController::class, 'destroy'])->name('app.labels.destroy');

    // Post tags
    Route::redirect('topics', 'post-tags')->name('app.topics.legacy');
    Route::get('post-tags', [PostTagController::class, 'index'])->name('app.post-tags.index');
    Route::post('post-tags', [PostTagController::class, 'store'])->name('app.post-tags.store');
    Route::put('post-tags/{tag}', [PostTagController::class, 'update'])->name('app.post-tags.update');
    Route::delete('post-tags/{tag}', [PostTagController::class, 'destroy'])->name('app.post-tags.destroy');

    // Automations
    Route::get('automations', [AutomationController::class, 'index'])->name('app.automations.index');
    Route::post('automations', [AutomationController::class, 'store'])->name('app.automations.store');
    Route::get('automations/{automation}', [AutomationController::class, 'show'])->name('app.automations.show');
    Route::get('automations/{automation}/workflow', [AutomationController::class, 'workflow'])->name('app.automations.workflow');
    Route::get('automations/{automation}/invocations', [AutomationController::class, 'invocations'])->name('app.automations.invocations');
    Route::get('automations/{automation}/metrics', [AutomationController::class, 'metrics'])->name('app.automations.metrics');
    Route::get('automations/{automation}/settings', [AutomationController::class, 'settings'])->name('app.automations.settings');
    Route::put('automations/{automation}', [AutomationController::class, 'update'])->name('app.automations.update');
    Route::delete('automations/{automation}', [AutomationController::class, 'destroy'])->name('app.automations.destroy');
    Route::post('automations/{automation}/activate', [AutomationController::class, 'activate'])->name('app.automations.activate');
    Route::post('automations/{automation}/pause', [AutomationController::class, 'pause'])->name('app.automations.pause');
    Route::post('automations/{automation}/runs/{run}/retry', [AutomationController::class, 'retryRun'])->name('app.automations.runs.retry');
    Route::post('automations/{automation}/test', [AutomationController::class, 'test'])->name('app.automations.test');
    Route::post('automations/{automation}/feed/inspect', [AutomationController::class, 'inspectFeed'])->name('app.automations.feed.inspect');
    Route::get('automations/{automation}/runs/{run}', [AutomationController::class, 'showRun'])->name('app.automations.runs.show');

    // API Keys
    Route::get('settings/workspace/api-keys', [ApiKeyController::class, 'index'])->name('app.api-keys.index');
    Route::post('settings/workspace/api-keys', [ApiKeyController::class, 'store'])->name('app.api-keys.store');
    Route::delete('settings/workspace/api-keys/{tokenId}', [ApiKeyController::class, 'destroy'])->name('app.api-keys.destroy');

    // MCP
    Route::get('settings/workspace/mcp', [McpSettingsController::class, 'index'])->name('app.mcp.index');
    Route::delete('settings/workspace/mcp/{client}', [McpSettingsController::class, 'disconnect'])->name('app.mcp.disconnect');

    // Account Settings
    Route::get('settings/account', [AccountController::class, 'edit'])->name('app.account.edit');
    Route::put('settings/account', [AccountController::class, 'update'])->name('app.account.update');
    Route::get('settings/account/ai', [AiSettingsController::class, 'edit'])->name('app.ai-settings.edit');
    Route::put('settings/account/ai', [AiSettingsController::class, 'update'])->name('app.ai-settings.update');
    Route::post('settings/account/ai/test-dify', [AiSettingsController::class, 'testDify'])->name('app.ai-settings.test-dify');
    Route::put('settings/account/ai/pages/{account}', [AiSettingsController::class, 'updatePageAi'])->name('app.ai-settings.page.update');
    Route::put('settings/account/ai/pages', [AiSettingsController::class, 'updateBatchPageAi'])->name('app.ai-settings.pages.batch-update');
    Route::get('settings/account/usage', [UsageController::class, 'index'])->name('app.usage.index');

    // Billing
    Route::get('settings/account/billing', [BillingController::class, 'index'])->name('app.billing.index');
    Route::get('settings/account/billing/portal', [BillingController::class, 'portal'])->name('app.billing.portal');
    Route::post('settings/account/billing/swap-to-yearly', [BillingController::class, 'swapToYearly'])->name('app.billing.swap-to-yearly');

});

// Notifications (auth only, no subscription required)
Route::middleware(['auth'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('app.notifications.index');
    Route::put('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('app.notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('app.notifications.read-all');
    Route::post('notifications/archive-all', [NotificationController::class, 'archiveAll'])->name('app.notifications.archive-all');
});

// Settings (auth required)
Route::middleware(['auth'])->group(function () {
    Route::get('settings', [SettingsController::class, 'index'])->name('app.settings');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('app.profile.edit');
    Route::put('settings/profile', [ProfileController::class, 'update'])->name('app.profile.update');
    Route::post('settings/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('app.profile.upload-photo');
    Route::delete('settings/profile/photo', [ProfileController::class, 'deletePhoto'])->name('app.profile.delete-photo');
    Route::put('settings/language', [ProfileController::class, 'updateLanguage'])->name('app.profile.language');
});

Route::middleware(['auth'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('app.profile.destroy');

    Route::get('settings/authentication', [AuthenticationController::class, 'edit'])->name('app.authentication.edit');
    Route::put('settings/authentication/password', [AuthenticationController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('app.authentication.update-password');
    Route::delete('settings/authentication/sessions', [AuthenticationController::class, 'destroyOtherSessions'])
        ->name('app.authentication.destroy-other-sessions');
    Route::get('settings/authentication/providers/{provider}/connect', [AuthenticationController::class, 'connectProvider'])
        ->name('app.authentication.connect-provider');
    Route::delete('settings/authentication/providers/{provider}', [AuthenticationController::class, 'disconnectProvider'])
        ->name('app.authentication.disconnect-provider');

    Route::get('settings/profile/notifications', [NotificationPreferenceController::class, 'edit'])->name('app.notifications.preferences');
    Route::put('settings/profile/notifications', [NotificationPreferenceController::class, 'update'])->name('app.notifications.preferences.update');
});
