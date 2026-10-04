<?php

declare(strict_types=1);

namespace App\Jobs\Omnichat;

use App\Actions\Omnichat\StoreMessage;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastMessage;
use App\Models\OmnichatConversation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SendBroadcastMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $broadcastMessageId)
    {
        $this->onQueue('broadcasts');
    }

    public function handle(StoreMessage $storeMessage): void
    {
        $broadcastMessage = BroadcastMessage::query()
            ->with(['campaign', 'workspace.owner', 'conversation'])
            ->find($this->broadcastMessageId);

        if ($broadcastMessage === null || $broadcastMessage->status !== 'queued') {
            return;
        }

        $conversation = $broadcastMessage->conversation;
        $sender = $broadcastMessage->workspace?->owner;

        if (! $conversation instanceof OmnichatConversation || ! $sender instanceof User) {
            $this->markFailed($broadcastMessage, 'Không tìm thấy cuộc trò chuyện hoặc người gửi hợp lệ cho khách hàng này.');

            return;
        }

        $broadcastMessage->update(['status' => 'sending']);

        try {
            $sentMessage = $storeMessage->execute(
                conversation: $conversation,
                sender: $sender,
                body: (string) $broadcastMessage->sent_body,
                mode: 'reply',
                clientId: 'broadcast-'.$broadcastMessage->id,
                image: $this->imageFor($broadcastMessage),
            );

            if ($sentMessage->status !== 'sent') {
                throw new \RuntimeException('Nền tảng chưa xác nhận tin nhắn broadcast đã được gửi.');
            }

            $broadcastMessage->update([
                'status' => 'sent',
                'error_message' => null,
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->markFailed($broadcastMessage, $exception->getMessage());

            Log::warning('Omnichat broadcast message delivery failed.', [
                'broadcast_message_id' => $broadcastMessage->id,
                'conversation_id' => $conversation->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->refreshCampaignStatus($broadcastMessage->campaign);
    }

    public function failed(?Throwable $exception): void
    {
        $broadcastMessage = BroadcastMessage::query()
            ->with('campaign')
            ->find($this->broadcastMessageId);

        if ($broadcastMessage === null || $broadcastMessage->status === 'sent') {
            return;
        }

        $this->markFailed(
            $broadcastMessage,
            $exception?->getMessage() ?? 'Không thể gửi tin broadcast.',
        );
    }

    private function imageFor(BroadcastMessage $broadcastMessage): ?UploadedFile
    {
        if (blank($broadcastMessage->image_url)) {
            return null;
        }

        $imagePath = Str::after((string) parse_url($broadcastMessage->image_url, PHP_URL_PATH), '/storage/');
        $disk = Storage::disk('public');

        if ($imagePath === '' || ! $disk->exists($imagePath)) {
            throw new \RuntimeException('Không tìm thấy tệp ảnh broadcast đã tải lên.');
        }

        $absolutePath = $disk->path($imagePath);

        return new UploadedFile(
            $absolutePath,
            basename($imagePath),
            $disk->mimeType($imagePath) ?: null,
            null,
            true,
        );
    }

    private function markFailed(BroadcastMessage $broadcastMessage, string $errorMessage): void
    {
        $broadcastMessage->update([
            'status' => 'failed',
            'error_message' => Str::limit($errorMessage, 60000),
        ]);

        $this->refreshCampaignStatus($broadcastMessage->campaign);
    }

    private function refreshCampaignStatus(?BroadcastCampaign $campaign): void
    {
        if ($campaign === null) {
            return;
        }

        $sentCount = $campaign->messages()->where('status', 'sent')->count();
        $failedCount = $campaign->messages()->where('status', 'failed')->count();
        $pendingCount = $campaign->messages()->whereIn('status', ['queued', 'sending'])->count();
        $targetedCount = $campaign->messages()->count();

        $updates = [
            'stats' => [
                'total_targeted' => $targetedCount,
                'total_sent' => $sentCount,
                'failed' => $failedCount,
                'pending' => $pendingCount,
            ],
        ];

        if ($pendingCount === 0) {
            $updates['status'] = $campaign->repeat_daily ? 'scheduled' : 'completed';
            $updates['completed_at'] = $campaign->repeat_daily ? null : now();
        }

        $campaign->update($updates);
    }
}
