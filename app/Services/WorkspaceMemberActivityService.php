<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OmnichatMessage;
use App\Models\Post;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class WorkspaceMemberActivityService
{
    /**
     * @param  Collection<int, string>  $userIds
     * @return array<string, array{posts_created: int, posts_published: int, customers_cared: int, messages_sent: int, first_activity_at: ?string, last_activity_at: ?string}>
     */
    public function forUsers(string $workspaceId, Collection $userIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $postStats = Post::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('user_id, COUNT(*) as posts_created, COUNT(published_at) as posts_published, MIN(created_at) as first_activity_at, MAX(created_at) as last_activity_at')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $messageStats = OmnichatMessage::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('sender_user_id', $userIds)
            ->whereIn('direction', ['outbound', 'internal'])
            ->whereBetween('sent_at', [$from, $to])
            ->selectRaw('sender_user_id, COUNT(*) as messages_sent, COUNT(DISTINCT conversation_id) as customers_cared, MIN(sent_at) as first_activity_at, MAX(sent_at) as last_activity_at')
            ->groupBy('sender_user_id')
            ->get()
            ->keyBy('sender_user_id');

        return $userIds->mapWithKeys(function (string $userId) use ($postStats, $messageStats): array {
            $posts = $postStats->get($userId);
            $messages = $messageStats->get($userId);
            $firstActivity = collect([$posts?->first_activity_at, $messages?->first_activity_at])->filter()->min();
            $lastActivity = collect([$posts?->last_activity_at, $messages?->last_activity_at])->filter()->max();

            return [$userId => [
                'posts_created' => (int) ($posts?->posts_created ?? 0),
                'posts_published' => (int) ($posts?->posts_published ?? 0),
                'customers_cared' => (int) ($messages?->customers_cared ?? 0),
                'messages_sent' => (int) ($messages?->messages_sent ?? 0),
                'first_activity_at' => $firstActivity,
                'last_activity_at' => $lastActivity,
            ]];
        })->all();
    }
}
