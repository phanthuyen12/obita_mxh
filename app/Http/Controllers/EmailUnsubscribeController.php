<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EmailCampaign\ContactStatus;
use App\Enums\EmailCampaign\SuppressionReason;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailSuppression;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Public one-click unsubscribe. Reached from the campaign footer link and from
 * the `List-Unsubscribe` header, so it must work without a session.
 */
class EmailUnsubscribeController extends Controller
{
    public function show(string $token): View
    {
        $recipient = EmailCampaignRecipient::query()
            ->where('unsubscribe_token', $token)
            ->first();

        if ($recipient === null) {
            return view('email.unsubscribe', ['email' => null, 'alreadyDone' => false, 'notFound' => true]);
        }

        $alreadyDone = $this->suppress($recipient);

        return view('email.unsubscribe', [
            'email' => $recipient->email,
            'alreadyDone' => $alreadyDone,
            'notFound' => false,
        ]);
    }

    /**
     * One-click POST, as required by RFC 8058 for `List-Unsubscribe-Post`.
     */
    public function store(string $token): RedirectResponse
    {
        $recipient = EmailCampaignRecipient::query()
            ->where('unsubscribe_token', $token)
            ->first();

        if ($recipient !== null) {
            $this->suppress($recipient);
        }

        return redirect()->route('email.unsubscribe', $token);
    }

    /**
     * @return bool whether the address was already suppressed before this call
     */
    private function suppress(EmailCampaignRecipient $recipient): bool
    {
        $existing = EmailSuppression::query()
            ->where('workspace_id', $recipient->workspace_id)
            ->where('email', $recipient->email)
            ->exists();

        EmailSuppression::query()->updateOrCreate(
            [
                'workspace_id' => $recipient->workspace_id,
                'email' => $recipient->email,
            ],
            [
                'reason' => SuppressionReason::Unsubscribed,
                'source_campaign_id' => $recipient->email_campaign_id,
            ],
        );

        $recipient->contact?->update(['status' => ContactStatus::Unsubscribed]);

        return $existing;
    }
}
