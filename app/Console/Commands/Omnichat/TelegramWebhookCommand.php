<?php

declare(strict_types=1);

namespace App\Console\Commands\Omnichat;

use App\Enums\Omnichat\ChannelProvider;
use App\Models\OmnichatChannel;
use App\Support\Omnichat\TelegramOmnichatClient;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('omnichat:telegram-webhook {channel_id? : ID kênh Telegram} {--sync : Đăng ký / đồng bộ lại Webhook với Telegram} {--delete : Xóa Webhook khỏi Telegram} {--info : Xem chi tiết thông tin Webhook từ Telegram}')]
#[Description('Quản lý, đồng bộ và kiểm tra trạng thái Webhook của các bot Telegram trong OmniChat')]
class TelegramWebhookCommand extends Command
{
    public function handle(TelegramOmnichatClient $client): int
    {
        $channelId = $this->argument('channel_id');
        $sync = (bool) $this->option('sync');
        $delete = (bool) $this->option('delete');

        $query = OmnichatChannel::query()
            ->where('provider', ChannelProvider::Telegram);

        if ($channelId) {
            $query->where('id', $channelId);
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->warn('Không tìm thấy kênh Telegram nào trong hệ thống.');

            return self::SUCCESS;
        }

        $this->info("Tìm thấy {$channels->count()} kênh Telegram.");

        foreach ($channels as $channel) {
            $botName = $channel->name;
            $botId = $channel->id;
            $targetUrl = $client->buildWebhookUrl($channel);

            $this->newLine();
            $this->line('==================================================');
            $this->info("Kênh: {$botName} [ID: {$botId}]");
            $this->line("Webhook URL dự kiến: {$targetUrl}");

            if ($delete) {
                $this->warn('Đang xóa Webhook...');
                try {
                    $ok = $client->deleteWebhook($channel);
                    $ok ? $this->info('✓ Đã xóa Webhook thành công.') : $this->error('✗ Xóa Webhook thất bại.');
                } catch (Throwable $e) {
                    $this->error("Lỗi: {$e->getMessage()}");
                }
            } elseif ($sync) {
                $this->info('Đang đăng ký / đồng bộ Webhook...');
                try {
                    $ok = $client->setWebhook($channel, $targetUrl);
                    $ok ? $this->info("✓ Đã đăng ký Webhook thành công với URL: {$targetUrl}") : $this->error('✗ Đăng ký Webhook thất bại.');
                } catch (Throwable $e) {
                    $this->error("Lỗi: {$e->getMessage()}");
                }
            }

            // Always display Webhook Info after action or when viewing
            try {
                $response = $client->getWebhookInfo($channel);
                $info = (array) data_get($response, 'result', []);

                $currentUrl = (string) data_get($info, 'url', 'Chưa đăng ký');
                $hasCustomCert = data_get($info, 'has_custom_certificate') ? 'Có' : 'Không';
                $pendingUpdates = (int) data_get($info, 'pending_update_count', 0);
                $lastErrorDate = data_get($info, 'last_error_date');
                $lastErrorMessage = data_get($info, 'last_error_message');

                $this->table(
                    ['Thuộc tính', 'Giá trị'],
                    [
                        ['URL hiện tại trên Telegram', $currentUrl ?: '(Trống)'],
                        ['Trạng thái khớp URL', $currentUrl === $targetUrl ? '✓ KHỚP' : '✗ KHÁC NHAU (Cần --sync)'],
                        ['Tin nhắn tồn đọng (pending)', $pendingUpdates],
                        ['Chứng chỉ tùy chỉnh', $hasCustomCert],
                        ['Lần lỗi gần nhất', $lastErrorDate ? Carbon::createFromTimestamp($lastErrorDate)->toDateTimeString() : 'Không có'],
                        ['Nội dung lỗi gần nhất', $lastErrorMessage ?: 'Không có'],
                    ]
                );
            } catch (Throwable $e) {
                $this->error("Không thể lấy WebhookInfo từ Telegram: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
