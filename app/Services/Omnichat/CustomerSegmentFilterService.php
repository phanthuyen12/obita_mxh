<?php

declare(strict_types=1);

namespace App\Services\Omnichat;

use App\Models\CustomerSegment;
use App\Models\OmnichatContact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerSegmentFilterService
{
    /**
     * Build base query for a segment within a workspace.
     */
    public function queryForSegment(CustomerSegment $segment): Builder
    {
        $query = OmnichatContact::query()
            ->where('workspace_id', $segment->workspace_id);

        return $segment->filterContacts($query);
    }

    /**
     * Get paginated contacts belonging to a dynamic segment.
     */
    public function getPaginatedContacts(CustomerSegment $segment, int $perPage = 20): LengthAwarePaginator
    {
        return $this->queryForSegment($segment)
            ->latest('last_seen_at')
            ->paginate($perPage);
    }

    /**
     * Get total count of contacts in a segment.
     */
    public function countContactsInSegment(CustomerSegment $segment): int
    {
        return $this->queryForSegment($segment)->count();
    }

    /**
     * Quick query for inactive contacts (30 days, 40 days, etc.)
     */
    public function queryInactiveContacts(string $workspaceId, int $inactiveDays, bool $hasPhoneOnly = false): Builder
    {
        $threshold = now()->subDays($inactiveDays);

        $query = OmnichatContact::query()
            ->where('workspace_id', $workspaceId)
            ->where(function (Builder $q) use ($threshold): void {
                $q->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<=', $threshold);
            });

        if ($hasPhoneOnly) {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        }

        return $query;
    }
}
