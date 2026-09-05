<?php

declare(strict_types=1);

namespace App\Http\Requests\App\PostAnalytics;

use App\Enums\SocialAccount\Platform;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace !== null && $this->user()->can('viewAnalytics', $workspace);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'period' => $this->input('period', '30d'),
            'platform' => $this->input('platform', 'all'),
            'account_id' => $this->input('account_id', 'all'),
            'content_type' => $this->input('content_type', 'all'),
            'topic_tag' => $this->input('topic_tag', 'all'),
            'sort' => $this->input('sort', 'recent'),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'platform' => ['required', Rule::in(['all', ...array_map(fn (Platform $platform): string => $platform->value, Platform::cases())])],
            'account_id' => ['required', Rule::when($this->input('account_id') !== 'all', ['uuid'])],
            'content_type' => ['required', Rule::in(['all', 'ceo', 'general'])],
            'topic_tag' => ['nullable', 'string', 'max:100'],
            'sort' => ['required', Rule::in(['recent', 'trending', 'engagement'])],
            'period' => ['required', Rule::in(['7d', '30d', '90d', '365d', 'baseline_july_2026', 'custom'])],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** @return array<string, string> */
    public function filters(): array
    {
        $range = $this->dateRange();

        return [
            'search' => (string) $this->validated('search', ''),
            'platform' => (string) $this->validated('platform'),
            'account_id' => (string) $this->validated('account_id'),
            'content_type' => (string) $this->validated('content_type', 'all'),
            'topic_tag' => (string) $this->validated('topic_tag', 'all'),
            'sort' => (string) $this->validated('sort'),
            'period' => (string) $this->validated('period'),
            'page' => (int) $this->input('page', 1),
            'from' => $range['from'],
            'to' => $range['to'],
            'previous_from' => $range['previous_from'],
            'previous_to' => $range['previous_to'],
        ];
    }

    /** @return array{from: string, to: string, previous_from: string, previous_to: string, days: int} */
    public function dateRange(): array
    {
        $period = (string) $this->validated('period');

        if ($period === 'baseline_july_2026') {
            $from = CarbonImmutable::parse('2026-07-01 00:00:00');
            $to = CarbonImmutable::parse('2026-07-31 23:59:59');
            $days = 31;
            $previousFrom = CarbonImmutable::parse('2026-06-01 00:00:00');
            $previousTo = CarbonImmutable::parse('2026-06-30 23:59:59');

            return [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'previous_from' => $previousFrom->toIso8601String(),
                'previous_to' => $previousTo->toIso8601String(),
                'days' => $days,
            ];
        }

        $to = $period === 'custom'
            ? CarbonImmutable::parse((string) $this->validated('to'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $from = match ($period) {
            '7d' => $to->subDays(6)->startOfDay(),
            '90d' => $to->subDays(89)->startOfDay(),
            '365d' => $to->subDays(364)->startOfDay(),
            'custom' => CarbonImmutable::parse((string) $this->validated('from'))->startOfDay(),
            default => $to->subDays(29)->startOfDay(),
        };
        $days = (int) $from->diffInDays($to) + 1;
        $previousTo = $from->subSecond();
        $previousFrom = $previousTo->subDays($days - 1)->startOfDay();

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'previous_from' => $previousFrom->toIso8601String(),
            'previous_to' => $previousTo->toIso8601String(),
            'days' => $days,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('period') !== 'custom' || ! $this->filled(['from', 'to']) || $validator->errors()->hasAny(['from', 'to'])) {
                    return;
                }

                if ($this->date('from')->diffInDays($this->date('to')) > 365) {
                    $validator->errors()->add('to', 'Khoảng thời gian tối đa là 366 ngày.');
                }
            },
        ];
    }
}
