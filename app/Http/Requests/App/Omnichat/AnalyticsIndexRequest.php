<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Omnichat;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyticsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace !== null && $this->user()->can('viewAnalytics', $workspace);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'period' => $this->input('period', 'week'),
            'channel_id' => $this->input('channel_id', 'all'),
            'contact_id' => $this->input('contact_id', 'all'),
            'assignee_id' => $this->input('assignee_id', 'all'),
        ]);
    }

    public function rules(): array
    {
        return [
            'period' => ['required', Rule::in(['week', 'month', 'custom'])],
            'channel_id' => ['required', Rule::when($this->input('channel_id') !== 'all', ['uuid'])],
            'contact_id' => ['required', Rule::when($this->input('contact_id') !== 'all', ['uuid'])],
            'assignee_id' => ['required', Rule::when($this->input('assignee_id') !== 'all' && $this->input('assignee_id') !== 'unassigned', ['uuid'])],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** @return array{period: string, channel_id: string, contact_id: string, assignee_id: string, from: string, to: string} */
    public function filters(): array
    {
        $period = (string) $this->validated('period');
        $to = $period === 'custom'
            ? CarbonImmutable::parse((string) $this->validated('to'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $from = match ($period) {
            'month' => $to->subDays(29)->startOfDay(),
            'custom' => CarbonImmutable::parse((string) $this->validated('from'))->startOfDay(),
            default => $to->subDays(6)->startOfDay(),
        };

        return [
            'period' => $period,
            'channel_id' => (string) $this->validated('channel_id'),
            'contact_id' => (string) $this->validated('contact_id'),
            'assignee_id' => (string) $this->validated('assignee_id'),
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
        ];
    }
}
