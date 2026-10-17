<?php

declare(strict_types=1);

namespace App\Mail;

use App\Jobs\EmailCampaign\SendEmailCampaignMessageJob;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Services\EmailCampaign\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * One marketing email for one recipient.
 *
 * Unlike the transactional mailables this one is NOT ShouldQueue: it is always
 * sent from inside {@see SendEmailCampaignMessageJob},
 * which already runs on the queue and owns the per-recipient retry policy.
 */
class EmailCampaignMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmailCampaign $campaign,
        public EmailCampaignRecipient $recipient,
        public string $unsubscribeUrl,
    ) {
        $this->locale = config('app.locale');
    }

    public function envelope(): Envelope
    {
        $renderer = app(EmailTemplateRenderer::class);

        return new Envelope(
            subject: $renderer->render($this->campaign->subject, $this->recipient, escape: false),
            replyTo: $this->campaign->reply_to ? [$this->campaign->reply_to] : [],
        );
    }

    public function content(): Content
    {
        $renderer = app(EmailTemplateRenderer::class);

        return new Content(
            view: 'mail.campaign',
            with: [
                'title' => $this->campaign->subject,
                'previewText' => $this->campaign->preview_text,
                'bodyHtml' => $renderer->render($this->campaign->body_html, $this->recipient),
                'senderName' => $this->campaign->from_name ?: $this->campaign->workspace->name,
                'recipientEmail' => $this->recipient->email,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'List-Unsubscribe' => "<{$this->unsubscribeUrl}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
