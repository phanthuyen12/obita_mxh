<?php

namespace App\Jobs;

use App\Enums\SocialAccount\Platform;
use App\Models\SocialAccount;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SubscribeFacebookPageToWebhooks implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public int $uniqueFor = 300;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public function __construct(public string $socialAccountId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $account = SocialAccount::query()->find($this->socialAccountId);

        if ($account === null || $account->platform !== Platform::Facebook) {
            return;
        }

        $this->updateSubscriptionStatus($account, 'subscribing');

        try {
            $response = Http::asForm()
                ->connectTimeout(5)
                ->timeout(10)
                ->post(config('trypost.platforms.facebook.graph_api')."/{$account->platform_user_id}/subscribed_apps", [
                    'subscribed_fields' => 'messages,messaging_postbacks',
                    'access_token' => $account->access_token,
                ]);
        } catch (ConnectionException $exception) {
            throw $exception;
        }

        if ($response->serverError()) {
            throw new RuntimeException('Meta webhook subscription is temporarily unavailable.');
        }

        if ($response->failed() || $response->json('success') !== true) {
            $this->updateSubscriptionStatus(
                $account,
                'failed',
                (string) ($response->json('error.message') ?? 'Meta rejected the webhook subscription.'),
            );

            Log::warning('Facebook Page webhook subscription rejected', [
                'social_account_id' => $account->id,
                'page_id' => $account->platform_user_id,
                'status' => $response->status(),
                'error_code' => $response->json('error.code'),
                'error_type' => $response->json('error.type'),
            ]);

            return;
        }

        $this->updateSubscriptionStatus($account, 'subscribed');
    }

    public function uniqueId(): string
    {
        return $this->socialAccountId;
    }

    public function failed(?Throwable $exception): void
    {
        $account = SocialAccount::query()->find($this->socialAccountId);

        if ($account !== null) {
            $this->updateSubscriptionStatus(
                $account,
                'failed',
                $exception?->getMessage() ?? 'Facebook webhook subscription failed.',
            );
        }

        Log::error('Facebook Page webhook subscription job failed', [
            'social_account_id' => $this->socialAccountId,
            'error' => $exception?->getMessage(),
        ]);
    }

    private function updateSubscriptionStatus(SocialAccount $account, string $status, ?string $error = null): void
    {
        $meta = $account->meta ?? [];
        data_set($meta, 'webhook_subscription', [
            'status' => $status,
            'error' => $error,
            'updated_at' => now()->toIso8601String(),
        ]);

        $account->update(['meta' => $meta]);
    }
}
