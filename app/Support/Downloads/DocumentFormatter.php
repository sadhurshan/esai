<?php

namespace App\Support\Downloads;

use App\Models\CompanyLocaleSetting;
use App\Models\CompanyMoneySetting;
use Carbon\CarbonInterface;
use IntlDateFormatter;
use NumberFormatter;

class DocumentFormatter
{
    private readonly string $locale;
    private readonly string $numberLocale;
    private readonly string $timezone;
    private readonly string $datePattern;
    private readonly string $currency;

    public function __construct(?CompanyLocaleSetting $localeSetting, ?CompanyMoneySetting $moneySetting)
    {
        $this->locale = $localeSetting?->locale ?: config('app.locale', 'en_US');
        $this->timezone = $localeSetting?->timezone ?: config('app.timezone', 'UTC');
        $this->datePattern = $localeSetting?->date_format ?: 'Y-m-d';
        $this->currency = $moneySetting?->pricing_currency ?: ($moneySetting?->base_currency ?: 'USD');
        $numberPattern = $localeSetting?->number_format;
        $this->numberLocale = match ($numberPattern) {
            '1.234,56' => 'de-DE',
            '1 234,56' => 'fr-FR',
            default => $this->locale,
        };
    }

    public function money(?int $amountMinor, ?string $currency = null): string
    {
        $minor = $amountMinor ?? 0;
        $value = $minor / 100;
        $formatter = new NumberFormatter($this->locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($value, $currency ?: $this->currency) ?: sprintf('%s %.2f', $currency ?: $this->currency, $value);
    }

    public function decimal(float|int|null $value, int $precision = 2): string
    {
        if ($value === null) {
            return '—';
        }

        $formatter = new NumberFormatter($this->numberLocale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $precision);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $precision);

        return $formatter->format($value) ?: number_format($value, $precision);
    }

    public function quantity(float|int|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        $formatter = new NumberFormatter($this->numberLocale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 3);

        return $formatter->format($value) ?: number_format($value, 3);
    }

    public function date(?CarbonInterface $date, ?string $fallback = '—'): string
    {
        if ($date === null) {
            return $fallback ?? '—';
        }

        $formatter = new IntlDateFormatter(
            $this->locale,
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE,
            $this->timezone,
        );

        if ($this->datePattern) {
            $formatter->setPattern($this->datePattern);
        }

        return $formatter->format($date) ?: $date->setTimezone($this->timezone)->format('Y-m-d');
    }

    public function formatAddress(array|string|null $address): ?string
    {
        if (is_string($address)) {
            return trim($address) ?: null;
        }

        if (is_array($address)) {
            $parts = array_filter(array_map(static fn ($line) => is_string($line) ? trim($line) : null, $address));

            return $parts ? implode("\n", $parts) : null;
        }

        return null;
    }
}
