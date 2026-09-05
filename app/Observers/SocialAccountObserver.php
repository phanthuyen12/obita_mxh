<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SocialAccount\Status;
use App\Events\OnboardingStatusUpdated;
use App\Jobs\PostHog\SyncAccountUsage;
use App\Models\SocialAccount;
use App\Services\PostHogService;
use Illuminate\Auth\Access\AuthorizationException;

class SocialAccountObserver
{
    public function creating(SocialAccount $socialAccount): void
    {
        $socialAccount->connected_by_user_id ??= auth()->id();
    }

    public function created(SocialAccount $socialAccount): void
    {
        $this->syncUsageAndOnboarding($socialAccount);
    }

    public function updating(SocialAccount $socialAccount): void
    {
        $user = auth()->user();

        if (! $user || ! $socialAccount->isDirty(['access_token', 'refresh_token'])) {
            return;
        }

        if ($socialAccount->connected_by_user_id !== $user->id
            && $user->cannot('manageAccounts', $socialAccount->workspace)) {
            throw new AuthorizationException('You cannot replace credentials for a shared social account.');
        }
    }

    public function deleted(SocialAccount $socialAccount): void
    {
        $this->syncUsageAndOnboarding($socialAccount);
    }

    public function updated(SocialAccount $socialAccount): void
    {
        if (! $socialAccount->wasChanged('status')) {
            return;
        }

        $wasConnected = $socialAccount->getRawOriginal('status') === Status::Connected->value;
        $isConnected = $socialAccount->status === Status::Connected;

        if ($wasConnected !== $isConnected) {
            $this->notifyOnboarding($socialAccount);
        }
    }

    private function syncUsageAndOnboarding(SocialAccount $socialAccount): void
    {
        $this->syncUsage($socialAccount);

        if ($socialAccount->status === Status::Connected) {
            $this->notifyOnboarding($socialAccount);
        }
    }

    /**
     * First usable connect / last disconnect for the account.
     * Actor-less → syncAndNotify falls back to the account owner.
     */
    private function notifyOnboarding(SocialAccount $socialAccount): void
    {
        $socialAccount->loadMissing('workspace.account');

        $account = $socialAccount->workspace?->account;

        if (! $account?->isOnboardingOpen()) {
            return;
        }

        $hasOtherConnections = SocialAccount::query()
            ->whereIn('workspace_id', $account->workspaces()->select('id'))
            ->whereKeyNot($socialAccount->id)
            ->where('status', Status::Connected)
            ->exists();

        if (! $hasOtherConnections) {
            OnboardingStatusUpdated::dispatchForWorkspace($socialAccount->workspace_id);
        }
    }

    private function syncUsage(SocialAccount $socialAccount): void
    {
        if (PostHogService::isEnabled()) {
            SyncAccountUsage::dispatch(
                (string) $socialAccount->workspace->account_id,
                (string) $socialAccount->workspace_id,
            );
        }
    }
}
