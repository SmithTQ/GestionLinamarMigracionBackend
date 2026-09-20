<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Support\Carbon;

trait FormatsResourceDates
{
    protected function formatResourceDate(mixed $value, string $format): ?string
    {
        return $value === null || $value === ''
            ? null
            : Carbon::parse((string) $value)->format($format);
    }

    protected function formatResourceDateIso(mixed $value): ?string
    {
        return $value === null || $value === ''
            ? null
            : Carbon::parse((string) $value)->toISOString();
    }

    protected function formatResourceDateOnly(mixed $value): ?string
    {
        return $value === null || $value === ''
            ? null
            : Carbon::parse((string) $value)->toDateString();
    }
}
