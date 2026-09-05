<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PostWorkflowApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Post $post
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Bài viết của bạn đã được duyệt trong workspace {$this->post->workspace->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.post-workflow-approved',
            with: [
                'title' => 'Bài viết đã được phê duyệt',
                'previewText' => 'Bài viết của bạn đã được duyệt thành công.',
                'body' => "Bài viết của bạn trong workspace {$this->post->workspace->name} đã được phê duyệt và lên lịch đăng thành công.",
                'postContent' => $this->post->content,
                'scheduledAt' => $this->post->scheduled_at?->format('d/m/Y H:i') ?? 'Ngay lập tức',
                'url' => route('app.posts.edit', $this->post),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
