<?php

namespace App\Support\Money;

use App\Enums\MoneyRoundRule;
use InvalidArgumentException;

class Money
{
    public function __construct(
        private readonly int $amountMinor,
        private readonly string $currency
    ) {
        if (strlen($this->currency) !== 3) {
            throw new InvalidArgumentException('Currency codes must be ISO 4217 length.');
        }
    }

    public static function fromMinor(int $amountMinor, string $currency): self
    {
        return new self($amountMinor, strtoupper($currency));
    }

    public static function fromDecimal(float $amount, string $currency, int $minorUnit, MoneyRoundRule $rule = MoneyRoundRule::HalfUp): self
    {
        $factor = 10 ** $minorUnit;
        $value = $amount * $factor;
        $minor = self::roundMinor($value, $rule);

        return new self($minor, strtoupper($currency));
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function toDecimal(int $minorUnit): string
    {
        $factor = 10 ** $minorUnit;
        $value = $this->amountMinor / $factor;

        return number_format($value, $minorUnit, '.', '');
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor - $other->amountMinor, $this->currency);
    }

    public function multiply(float $multiplier, MoneyRoundRule $rule = MoneyRoundRule::HalfUp): self
    {
        $value = $this->amountMinor * $multiplier;

        return new self(self::roundMinor($value, $rule), $this->currency);
    }

    public function divide(float $divisor, MoneyRoundRule $rule = MoneyRoundRule::HalfUp): self
    {
        if ($divisor === 0.0) {
            throw new InvalidArgumentException('Cannot divide by zero.');
        }

        $value = $this->amountMinor / $divisor;

        return new self(self::roundMinor($value, $rule), $this->currency);
    }

    public function round(MoneyRoundRule $rule): self
    {
        return new self(self::roundMinor($this->amountMinor, $rule), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && $this->amountMinor === $other->amountMinor;
    }

    public function format(int $minorUnit): string
    {
        $factor = 10 ** $minorUnit;
        $value = $this->amountMinor / $factor;

        return number_format($value, $minorUnit, '.', '');
    }

    private static function roundMinor(float $value, MoneyRoundRule $rule): int
    {
        return match ($rule) {
            MoneyRoundRule::Bankers => self::roundBankers($value),
            MoneyRoundRule::HalfUp => (int) round($value, 0, PHP_ROUND_HALF_UP),
        };
    }

    private static function roundBankers(float $value): int
    {
        $floor = floor($value);
        $fraction = $value - $floor;

        if (abs($fraction - 0.5) > 1e-9) {
            return (int) round($value, 0, PHP_ROUND_HALF_UP);
        }

        return ((int) $floor % 2 === 0) ? (int) $floor : (int) ($floor + 1);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
    }
}
