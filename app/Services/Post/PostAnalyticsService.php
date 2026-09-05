<?php

declare(strict_types=1);

namespace App\Services\Post;

use App\Enums\SocialAccount\Status as SocialAccountStatus;
use App\Models\PageFollowerSnapshot;
use App\Models\Post;
use App\Models\PostMetricSnapshot;
use App\Models\PostPlatform;
use App\Models\WorkspaceKpiTarget;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PostAnalyticsService
{
    public function __construct(private readonly PostMetricsFetcher $metricsFetcher) {}

    /** @return array<string, mixed> */
    public function index(string $workspaceId, array $filters): array
    {
        $platform = $filters['platform'];
        $accountId = $filters['account_id'];
        $contentType = $filters['content_type'] ?? 'all';
        $topicTag = $filters['topic_tag'] ?? 'all';

        $query = Post::query()
            ->where('workspace_id', $workspaceId)
            ->published()
            ->whereBetween('published_at', [$filters['from'], $filters['to']])
            ->with(['tags', 'postPlatforms' => function ($query) use ($platform, $accountId): void {
                $query->where('status', 'published')
                    ->whereHas('socialAccount', fn ($q) => $q->where('is_active', true)->where('status', SocialAccountStatus::Connected))
                    ->with(['socialAccount', 'snapshots']);
                $query->when($platform !== 'all', fn ($platforms) => $platforms->where('platform', $platform));
                $query->when($accountId !== 'all', fn ($platforms) => $platforms->where('social_account_id', $accountId));
            }])
            ->latest('published_at');

        if ($contentType === 'ceo') {
            $query->where('is_ceo_content', true);
        } elseif ($contentType === 'general') {
            $query->where(fn ($q) => $q->where('is_ceo_content', false)->orWhereNull('is_ceo_content'));
        }

        if ($topicTag !== 'all' && filled($topicTag)) {
            $query->whereHas('tags', fn ($tags) => $tags->where('tags.name', $topicTag));
        }

        if ($filters['search'] !== '') {
            $query->where('content', 'like', "%{$filters['search']}%");
        }

        $this->constrainPostQuery($query, $filters);

        $posts = $query->paginate(12)->withQueryString();
        $rows = collect($posts->items())->map(fn (Post $post) => $this->summarize($post, false));

        if ($filters['sort'] === 'trending') {
            $rows = $rows->sortByDesc('trend_score');
        } elseif ($filters['sort'] === 'engagement') {
            $rows = $rows->sortByDesc('engagement_rate');
        }

        $posts->setCollection($rows->values());

        // Fetch top content rankings using stored metrics
        $allPostsQuery = (clone $query)->limit(30)->get();
        $allSummarized = $allPostsQuery->map(fn (Post $post) => $this->summarize($post, false));
        $topContent = $allSummarized->sortByDesc('engagement_rate')->take(3)->values();

        $summary = $this->summaryAt($workspaceId, $filters, $filters['to']);
        $summary['posts'] = $this->postsCount($workspaceId, $filters, $filters['from'], $filters['to']);
        $previousSummary = $this->summaryAt($workspaceId, $filters, $filters['previous_to']);
        $previousSummary['posts'] = $this->postsCount($workspaceId, $filters, $filters['previous_from'], $filters['previous_to']);

        $kpiTarget = $this->kpiTargetSummary($workspaceId, $filters, (int) $summary['posts']);
        $followerGrowth = $this->calculateFollowerGrowth($workspaceId, $filters);

        return [
            'posts' => $posts,
            'summary' => $summary,
            'comparison' => $this->comparison($summary, $previousSummary),
            'growth' => $this->growth($workspaceId, $filters)->values()->all(),
            'top_content' => $topContent->values()->all(),
            'kpi_summary' => $kpiTarget,
            'follower_growth' => $followerGrowth,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Post $post): array
    {
        $post->load(['postPlatforms' => fn ($query) => $query->enabled()->with(['socialAccount', 'snapshots'])]);

        return [
            'post' => $this->summarize($post, false),
            'platforms' => $post->postPlatforms->map(fn (PostPlatform $platform) => [
                'id' => $platform->id,
                'platform' => $platform->platform->value,
                'account' => $platform->display_name,
                'url' => $platform->platform_url,
                'metrics' => $this->latestMetrics($platform, false),
                'growth' => $this->platformGrowth($platform),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    public function summarize(Post $post, bool $fetchLiveMetrics = true): array
    {
        $current = $post->postPlatforms->map(fn (PostPlatform $platform) => $this->latestMetrics($platform, $fetchLiveMetrics));
        $previous = $post->postPlatforms->map(fn (PostPlatform $platform) => $this->previousMetrics($platform));
        $totals = $this->totals($current);
        $previousTotals = $this->totals($previous);
        $platformUrls = $post->postPlatforms
            ->mapWithKeys(fn (PostPlatform $platform) => [$platform->platform->value => $platform->platform_url])
            ->filter()
            ->all();

        $reach = $totals['reach'] > 0 ? $totals['reach'] : $totals['views'];
        $sumEngagements = $totals['reactions'] + $totals['comments'] + $totals['shares'];
        $engRate = $reach > 0 ? round(($sumEngagements / $reach) * 100, 2) : 0.0;

        $firstMedia = collect($post->media ?? [])->first();
        $mediaPreview = is_array($firstMedia) ? ($firstMedia['url'] ?? $firstMedia['path'] ?? null) : null;

        return [
            'id' => $post->id,
            'content' => trim((string) $post->content),
            'excerpt' => Str::limit(trim((string) $post->content), 140),
            'media_preview' => $mediaPreview,
            'published_at' => $post->published_at?->toIso8601String(),
            'platforms' => $post->postPlatforms->pluck('platform')->map(fn ($platform) => $platform->value)->values(),
            'external_url' => collect($platformUrls)->first(),
            'platform_urls' => $platformUrls,
            'is_ceo_content' => (bool) $post->is_ceo_content,
            'topic_tags' => $post->topic_tags ?? [],
            'reach' => $totals['reach'],
            'impressions' => $totals['impressions'],
            'reactions' => $totals['reactions'],
            'comments' => $totals['comments'],
            'shares' => $totals['shares'],
            'views' => $totals['views'],
            'interactions' => $totals['interactions'],
            'likes' => $totals['likes'],
            'engagement_rate' => $engRate,
            'growth' => $this->growthPercent($totals['interactions'], $previousTotals['interactions']) ?? 0,
            'trend_score' => $totals['interactions'] + $totals['views'],
        ];
    }

    /** @return array<string, int|float> */
    private function summaryAt(string $workspaceId, array $filters, string $at): array
    {
        $contentType = $filters['content_type'] ?? 'all';
        $topicTag = $filters['topic_tag'] ?? 'all';
        $search = $filters['search'] ?? '';

        $platforms = PostPlatform::query()
            ->where('status', 'published')
            ->whereDoesntHave('socialAccount', fn ($sa) => $sa->where(fn ($q) => $q->where('is_active', false)->orWhere('status', '!=', SocialAccountStatus::Connected)))
            ->whereHas('post', function ($posts) use ($workspaceId, $at, $contentType, $topicTag, $search): void {
                $posts->where('workspace_id', $workspaceId)
                    ->published()
                    ->where('published_at', '<=', $at);

                if ($contentType === 'ceo') {
                    $posts->where('is_ceo_content', true);
                } elseif ($contentType === 'general') {
                    $posts->where(fn ($q) => $q->where('is_ceo_content', false)->orWhereNull('is_ceo_content'));
                }

                if ($topicTag !== 'all' && filled($topicTag)) {
                    $posts->whereHas('tags', fn ($tags) => $tags->where('tags.name', $topicTag));
                }

                if ($search !== '') {
                    $posts->where('content', 'like', "%{$search}%");
                }
            })
            ->with(['snapshots' => fn ($snapshots) => $snapshots->where('captured_at', '<=', $at)]);

        $platforms->when($filters['platform'] !== 'all', fn ($query) => $query->where('platform', $filters['platform']));
        $platforms->when($filters['account_id'] !== 'all', fn ($query) => $query->where('social_account_id', $filters['account_id']));
        $totals = $this->totals(collect());

        $platforms->chunkById(500, function (Collection $platformBatch) use (&$totals): void {
            foreach ($platformBatch as $platform) {
                $metrics = $this->latestMetrics($platform, false);

                foreach ($totals as $key => $value) {
                    if (is_int($value) || is_float($value)) {
                        $totals[$key] += ($metrics[$key] ?? 0);
                    }
                }
            }
        });

        $reach = $totals['reach'] > 0 ? $totals['reach'] : ($totals['impressions'] > 0 ? $totals['impressions'] : $totals['views']);
        $engagements = $totals['reactions'] + $totals['comments'] + $totals['shares'];
        $totals['engagement_rate'] = $reach > 0 ? round(($engagements / $reach) * 100, 2) : 0.0;

        return $totals;
    }

    private function postsCount(string $workspaceId, array $filters, string $from, string $to): int
    {
        $query = Post::query()
            ->where('workspace_id', $workspaceId)
            ->published()
            ->whereBetween('published_at', [$from, $to]);

        $this->constrainPostQuery($query, $filters);

        return $query->count();
    }

    private function constrainPostQuery(Builder $query, array $filters): void
    {
        $contentType = $filters['content_type'] ?? 'all';
        $topicTag = $filters['topic_tag'] ?? 'all';
        $search = $filters['search'] ?? '';

        if ($contentType === 'ceo') {
            $query->where('is_ceo_content', true);
        } elseif ($contentType === 'general') {
            $query->where(fn ($q) => $q->where('is_ceo_content', false)->orWhereNull('is_ceo_content'));
        }

        if ($topicTag !== 'all' && filled($topicTag)) {
            $query->whereHas('tags', fn ($tags) => $tags->where('tags.name', $topicTag));
        }

        if ($search !== '') {
            $query->where('content', 'like', "%{$search}%");
        }

        if ($filters['platform'] !== 'all' || $filters['account_id'] !== 'all') {
            $query->whereHas('postPlatforms', fn ($platforms) => $platforms
                ->where('status', 'published')
                ->whereHas('socialAccount', fn ($sa) => $sa->where('is_active', true)->where('status', SocialAccountStatus::Connected))
                ->when($filters['platform'] !== 'all', fn ($p) => $p->where('platform', $filters['platform']))
                ->when($filters['account_id'] !== 'all', fn ($p) => $p->where('social_account_id', $filters['account_id']))
            );
        } else {
            $query->whereDoesntHave('postPlatforms', fn ($platforms) => $platforms
                ->whereHas('socialAccount', fn ($sa) => $sa->where(fn ($q) => $q->where('is_active', false)->orWhere('status', '!=', SocialAccountStatus::Connected)))
            );
        }
    }

    /** @return array{target_posts: int, actual_posts: int, completion_rate: float, target_ceo_posts: int, actual_ceo_posts: int, ceo_completion_rate: float} */
    private function kpiTargetSummary(string $workspaceId, array $filters, int $actualPosts): array
    {
        $target = WorkspaceKpiTarget::where('workspace_id', $workspaceId)
            ->latest()
            ->first();

        $targetPosts = $target?->target_posts_count ?? 10;
        $targetCeoPosts = $target?->target_ceo_posts_count ?? 3;

        $ceoQuery = Post::query()
            ->where('workspace_id', $workspaceId)
            ->published()
            ->where('is_ceo_content', true)
            ->whereBetween('published_at', [$filters['from'], $filters['to']]);

        $this->constrainPostQuery($ceoQuery, $filters);

        $actualCeoPosts = $ceoQuery->count();

        return [
            'target_posts' => $targetPosts,
            'actual_posts' => $actualPosts,
            'completion_rate' => $targetPosts > 0 ? round(($actualPosts / $targetPosts) * 100, 1) : 100.0,
            'target_ceo_posts' => $targetCeoPosts,
            'actual_ceo_posts' => $actualCeoPosts,
            'ceo_completion_rate' => $targetCeoPosts > 0 ? round(($actualCeoPosts / $targetCeoPosts) * 100, 1) : 100.0,
        ];
    }

    /** @return array{start_followers: int, end_followers: int, net_growth: int, growth_percent: float|null} */
    private function calculateFollowerGrowth(string $workspaceId, array $filters): array
    {
        $from = CarbonImmutable::parse($filters['from'])->toDateString();
        $to = CarbonImmutable::parse($filters['to'])->toDateString();

        $startFollowers = (int) PageFollowerSnapshot::query()
            ->whereHas('socialAccount', fn ($q) => $q->where('workspace_id', $workspaceId)->where('is_active', true)->where('status', SocialAccountStatus::Connected))
            ->where('date', '<=', $from)
            ->latest('date')
            ->sum('follower_count');

        $endFollowers = (int) PageFollowerSnapshot::query()
            ->whereHas('socialAccount', fn ($q) => $q->where('workspace_id', $workspaceId)->where('is_active', true)->where('status', SocialAccountStatus::Connected))
            ->where('date', '<=', $to)
            ->latest('date')
            ->sum('follower_count');

        $netGrowth = $endFollowers - $startFollowers;
        $growthPercent = $startFollowers > 0 ? round(($netGrowth / $startFollowers) * 100, 2) : 0.0;

        return [
            'start_followers' => $startFollowers,
            'end_followers' => $endFollowers,
            'net_growth' => $netGrowth,
            'growth_percent' => $growthPercent,
        ];
    }

    /** @return Collection<int, array<string, int|string>> */
    private function growth(string $workspaceId, array $filters): Collection
    {
        $from = CarbonImmutable::parse($filters['from']);
        $to = CarbonImmutable::parse($filters['to']);
        $days = (int) $from->diffInDays($to) + 1;
        $snapshots = PostMetricSnapshot::query()
            ->select(['id', 'post_platform_id', 'metrics', 'captured_at'])
            ->whereBetween('captured_at', [$from, $to])
            ->whereHas('postPlatform', function ($query) use ($workspaceId, $filters): void {
                $query->whereHas('socialAccount', fn ($q) => $q->where('is_active', true)->where('status', SocialAccountStatus::Connected));
                $query->whereHas('post', fn ($posts) => $posts->where('workspace_id', $workspaceId));
                $query->when($filters['platform'] !== 'all', fn ($platforms) => $platforms->where('platform', $filters['platform']));
                $query->when($filters['account_id'] !== 'all', fn ($platforms) => $platforms->where('social_account_id', $filters['account_id']));
            })
            ->oldest('captured_at')
            ->get();

        return $snapshots
            ->groupBy(fn (PostMetricSnapshot $snapshot): string => $this->bucketDate($snapshot->captured_at->toImmutable(), $days))
            ->map(function (Collection $bucketSnapshots, string $date): array {
                $latestByPlatform = $bucketSnapshots
                    ->sortByDesc('captured_at')
                    ->unique('post_platform_id')
                    ->map(fn (PostMetricSnapshot $snapshot): array => $this->normalize($snapshot->metrics));
                $totals = $this->totals($latestByPlatform);

                return ['date' => $date, ...$totals];
            })
            ->sortBy('date')
            ->values();
    }

    private function bucketDate(CarbonImmutable $date, int $days): string
    {
        if ($days <= 31) {
            return $date->startOfDay()->toIso8601String();
        }

        if ($days <= 180) {
            return $date->startOfWeek()->startOfDay()->toIso8601String();
        }

        return $date->startOfMonth()->startOfDay()->toIso8601String();
    }

    /** @return array<string, array{current: int|float, previous: int|float, change: int|null}> */
    private function comparison(array $current, array $previous): array
    {
        return collect(['posts', 'views', 'reach', 'impressions', 'interactions', 'reactions', 'comments', 'shares', 'engagement_rate'])
            ->mapWithKeys(fn (string $metric): array => [$metric => [
                'current' => $current[$metric] ?? 0,
                'previous' => $previous[$metric] ?? 0,
                'change' => $this->growthPercent((int) ($current[$metric] ?? 0), (int) ($previous[$metric] ?? 0)),
            ]])
            ->all();
    }

    /** @return array<string, int|float> */
    private function latestMetrics(PostPlatform $platform, bool $fetchLiveMetrics = true): array
    {
        if ($platform->snapshots->isNotEmpty()) {
            return $this->normalize($platform->snapshots->first()->metrics);
        }

        if (! $fetchLiveMetrics) {
            return $this->normalize([]);
        }

        return $this->normalize($this->metricsFetcher->forPlatform($platform));
    }

    /** @return array<string, int|float> */
    private function previousMetrics(PostPlatform $platform): array
    {
        return $this->normalize($platform->snapshots->get(1)?->metrics ?? []);
    }

    /** @param array<int, mixed>|array{unsupported?: bool} $metrics @return array<string, int|float> */
    private function normalize(array $metrics): array
    {
        $normalized = [
            'views' => 0,
            'reach' => 0,
            'impressions' => 0,
            'interactions' => 0,
            'likes' => 0,
            'reactions' => 0,
            'comments' => 0,
            'shares' => 0,
            'engagement_rate' => 0.0,
        ];

        if (isset($metrics['unsupported'])) {
            return $normalized;
        }

        foreach ($metrics as $metric) {
            $key = Str::lower((string) ($metric['key'] ?? ''));
            $label = Str::lower((string) ($metric['label'] ?? ''));
            $value = (int) ($metric['value'] ?? 0);

            if ($key === 'reach' || Str::contains($label, 'reach')) {
                $normalized['reach'] += $value;
            } elseif ($key === 'impressions' || Str::contains($label, 'impression')) {
                $normalized['impressions'] += $value;
                $normalized['views'] += $value;
            } elseif (Str::contains($label, 'view')) {
                $normalized['views'] += $value;
                if ($normalized['impressions'] === 0) {
                    $normalized['impressions'] += $value;
                }
            }

            if ($key === 'reactions' || Str::contains($label, ['reaction', 'like', 'thích'])) {
                $normalized['reactions'] += $value;
                $normalized['likes'] += $value;
                $normalized['interactions'] += $value;
            }
            if ($key === 'comments' || Str::contains($label, ['comment', 'bình luận', 'reply'])) {
                $normalized['comments'] += $value;
                $normalized['interactions'] += $value;
            }
            if ($key === 'shares' || Str::contains($label, ['share', 'chia sẻ', 'repost', 'retweet'])) {
                $normalized['shares'] += $value;
                $normalized['interactions'] += $value;
            }
        }

        if ($normalized['views'] === 0 && $normalized['impressions'] > 0) {
            $normalized['views'] = $normalized['impressions'];
        } elseif ($normalized['impressions'] === 0 && $normalized['views'] > 0) {
            $normalized['impressions'] = $normalized['views'];
        }

        $baseViewers = $normalized['reach'] > 0 ? $normalized['reach'] : ($normalized['impressions'] > 0 ? $normalized['impressions'] : $normalized['views']);
        $sumInteractions = $normalized['reactions'] + $normalized['comments'] + $normalized['shares'];
        $normalized['engagement_rate'] = $baseViewers > 0 ? round(($sumInteractions / $baseViewers) * 100, 2) : 0.0;

        return $normalized;
    }

    /** @param Collection<int, array<string, int|float>> $metrics @return array<string, int|float> */
    private function totals(Collection $metrics): array
    {
        $keys = ['views', 'reach', 'impressions', 'interactions', 'likes', 'reactions', 'comments', 'shares'];
        $res = [];
        foreach ($keys as $key) {
            $res[$key] = (int) $metrics->sum($key);
        }

        $reach = $res['reach'] > 0 ? $res['reach'] : ($res['impressions'] > 0 ? $res['impressions'] : $res['views']);
        $engagements = $res['reactions'] + $res['comments'] + $res['shares'];
        $res['engagement_rate'] = $reach > 0 ? round(($engagements / $reach) * 100, 2) : 0.0;

        return $res;
    }

    private function growthPercent(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return $current === 0 ? 0 : null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    /** @return array<int, array{date: string, value: int}> */
    private function platformGrowth(PostPlatform $platform): array
    {
        return $platform->snapshots->reverse()->map(fn (PostMetricSnapshot $snapshot) => [
            'date' => $snapshot->captured_at->toDateString(),
            'value' => $this->normalize($snapshot->metrics)['interactions'],
        ])->values()->all();
    }

    private function deduplicatePosts(string $workspaceId): void
    {
        $duplicateGroups = Post::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('content')
            ->published()
            ->with(['postPlatforms.snapshots', 'tags'])
            ->get()
            ->groupBy(fn (Post $p): string => trim((string) $p->content).'|'.$p->published_at?->format('Y-m-d'));

        foreach ($duplicateGroups as $group) {
            if ($group->count() <= 1) {
                continue;
            }

            $primary = $group->sortByDesc(function (Post $p): int {
                $score = 0;
                if ($p->is_ceo_content) {
                    $score += 100;
                }
                if (! empty($p->topic_tags)) {
                    $score += 50;
                }
                foreach ($p->postPlatforms as $platform) {
                    if ($platform->snapshots->isNotEmpty()) {
                        $score += 20;
                    }
                }

                return $score;
            })->first();

            if (! $primary) {
                continue;
            }

            $duplicates = $group->where('id', '!=', $primary->id);

            foreach ($duplicates as $dup) {
                if ($dup->is_ceo_content && ! $primary->is_ceo_content) {
                    $primary->is_ceo_content = true;
                }
                if (! empty($dup->topic_tags)) {
                    $mergedTags = array_values(array_unique(array_merge($primary->topic_tags ?? [], $dup->topic_tags)));
                    $tagIds = $primary->workspace->tags()->whereIn('name', $mergedTags)->pluck('id');
                    $primary->tags()->syncWithoutDetaching($tagIds);
                }
                $primary->save();

                foreach ($dup->postPlatforms as $dupPlatform) {
                    $existingMatchingPlatform = $primary->postPlatforms
                        ->where('social_account_id', $dupPlatform->social_account_id)
                        ->first();

                    if ($existingMatchingPlatform) {
                        foreach ($dupPlatform->snapshots as $dupSnapshot) {
                            $exists = PostMetricSnapshot::query()
                                ->where('post_platform_id', $existingMatchingPlatform->id)
                                ->where('captured_at', $dupSnapshot->captured_at)
                                ->exists();

                            if ($exists) {
                                $dupSnapshot->delete();
                            } else {
                                $dupSnapshot->update(['post_platform_id' => $existingMatchingPlatform->id]);
                            }
                        }
                        $dupPlatform->delete();
                    } else {
                        $dupPlatform->update(['post_id' => $primary->id]);
                    }
                }

                $dup->delete();
            }
        }
    }
}
