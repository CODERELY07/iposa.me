<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The period of a report: last 7 days, this month (default), or a custom range of at most one year.
 */
class ReportRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'in:week,month,custom'],
            'from' => ['nullable', 'required_if:period,custom', 'date', 'before_or_equal:today'],
            'to' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:from', 'before_or_equal:today'],
        ];
    }

    public function period(): string
    {
        return $this->validated('period') ?? 'month';
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        $today = CarbonImmutable::today();

        return match ($this->period()) {
            'week' => [$today->subDays(6), $today],
            'custom' => $this->customRange(CarbonImmutable::parse($this->validated('from')), CarbonImmutable::parse($this->validated('to'))),
            default => [$today->startOfMonth(), $today],
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function customRange(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [$from->max($to->subYear())->startOfDay(), $to->startOfDay()];
    }
}
