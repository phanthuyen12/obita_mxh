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

class PostWorkflowSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Post $post
    ) {}

    public function envelope(): Envelope
    {
        $workspaceName = $this->post->workspace?->name ?? 'Workspace';

        return new Envelope(
            subject: "Có bài viết mới cần bạn duyệt trong workspace {$workspaceName}",
        );
    }

    public function content(): Content
    {
        $workspaceName = $this->post->workspace?->name ?? 'Workspace';

        return new Content(
            view: 'mail.post-workflow-submitted',
            with: [
                'title' => 'Bài viết mới cần duyệt',
                'previewText' => 'Có một bài viết mới đang chờ bạn phê duyệt.',
                'body' => "Bài viết mới trong workspace {$workspaceName} vừa được gửi lên và đang chờ bạn phê duyệt.",
                'postContent' => $this->post->content ?? '',
                'authorName' => $this->post->user?->name ?? 'Thành viên',
                'scheduledAt' => $this->post->scheduled_at?->format('d/m/Y H:i') ?? 'Chưa đặt lịch',
                'url' => route('app.posts.edit', $this->post),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
