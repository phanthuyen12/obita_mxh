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

class PostWorkflowRejected extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Post $post,
        public string $reason = 'Vui lòng kiểm tra và chỉnh sửa lại nội dung bài viết.'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Bài viết cần chỉnh sửa / bị từ chối trong workspace {$this->post->workspace->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.post-workflow-rejected',
            with: [
                'title' => 'Bài viết bị từ chối / cần chỉnh sửa',
                'previewText' => 'Bài viết của bạn đã bị từ chối phê duyệt.',
                'body' => "Bài viết của bạn trong workspace {$this->post->workspace->name} đã bị từ chối duyệt và cần được chỉnh sửa lại.",
                'reason' => $this->reason,
                'postContent' => $this->post->content,
                'url' => route('app.posts.edit', $this->post),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
