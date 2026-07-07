<?php

declare(strict_types=1);

namespace Headless\Accounting\Currency;

use Headless\Accounting\Support\Config;
use InvalidArgumentException;
use Stringable;

/**
 * Money — a strict, immutable value object representing a sum in a
 * specific currency, stored as integer *minor units* (cents).
 *
 * Floating-point dollars are forbidden by design. All arithmetic goes
 * through integer-safe helpers and a chosen rounding mode. Display
 * formatting is delegated to {@see Money::format()}, which uses the
 * locale-aware {@see Currency} data.
 */
final readonly class Money implements Stringable
{
    public function __construct(
        public int $amount,            // minor units (cents)
        public string $currency,        // ISO-4217, e.g. 'EUR'
    ) {
        if ($amount < -1_000_000_000_000 || $amount > 1_000_000_000_000) {
            throw new InvalidArgumentException('Money amount out of safe range.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid ISO-4217 code: {$currency}.");
        }
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    public static function fromFloat(float $amount, string $currency, int $decimals = 2, string $rounding = 'half_even'): self
    {
        $factor = 10 ** $decimals;
        $rounded = match ($rounding) {
            'half_even' => (int) round(bcmul((string) $amount, (string) $factor, 6), 0, PHP_ROUND_HALF_EVEN),
            'half_up' => (int) round(bcmul((string) $amount, (string) $factor, 6), 0, PHP_ROUND_HALF_UP),
            'down' => (int) floor(bcmul((string) $amount, (string) $factor, 6)),
            'up' => (int) ceil(bcmul((string) $amount, (string) $factor, 6)),
            default => throw new InvalidArgumentException("Unknown rounding mode: {$rounding}"),
        };

        return new self($rounded, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int|string $factor): self
    {
        $factor = is_string($factor)
            ? (int) round(bcmul((string) $this->amount, $factor, 0))
            : $this->amount * $factor;

        return new self((int) $factor, $this->currency);
    }

    public function percentage(float $percent, string $rounding = 'half_even'): self
    {
        $rounded = match ($rounding) {
            'half_even' => (int) round(bcmul((string) $this->amount, (string) ($percent / 100), 6), 0, PHP_ROUND_HALF_EVEN),
            'half_up' => (int) round(bcmul((string) $this->amount, (string) ($percent / 100), 6), 0, PHP_ROUND_HALF_UP),
            'down' => (int) floor(bcmul((string) $this->amount, (string) ($percent / 100), 6)),
            'up' => (int) ceil(bcmul((string) $this->amount, (string) ($percent / 100), 6)),
            default => throw new InvalidArgumentException("Unknown rounding mode: {$rounding}"),
        };

        return new self($rounded, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function compare(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->amount <=> $other->amount;
    }

    /** Returns a new instance with the absolute value. */
    public function abs(): self
    {
        return new self(abs($this->amount), $this->currency);
    }

    /** Returns a new instance negated. */
    public function negate(): self
    {
        return new self(-$this->amount, $this->currency);
    }

    /** Splits the money into N equal pieces, returning the *largest remainder* first. */
    public function allocate(int $n): array
    {
        if ($n <= 0) {
            throw new InvalidArgumentException('Cannot allocate to zero parts.');
        }

        $base = intdiv($this->amount, $n);
        $remainder = $this->amount - ($base * $n);
        $parts = array_fill(0, $n, new self($base, $this->currency));

        for ($i = 0; $i < abs($remainder); $i++) {
            $sign = $remainder > 0 ? 1 : -1;
            $parts[$i] = new self($parts[$i]->amount + $sign, $this->currency);
        }

        return $parts;
    }

    /** Native float representation, useful only for *display*. */
    public function toFloat(?int $decimals = null): float
    {
        $decimals ??= Currency::decimals($this->currency);

        return round($this->amount / (10 ** $decimals), $decimals);
    }

    public function format(?string $locale = null, ?int $decimals = null): string
    {
        $locale ??= Config::string('headless-accounting.locale.default');
        $decimals ??= Currency::decimals($this->currency);
        $symbol = Currency::symbol($this->currency);

        return number_format(
            $this->toFloat($decimals),
            $decimals,
            Currency::decimalSeparator($locale),
            Currency::thousandsSeparator($locale),
        ).' '.$symbol;
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(sprintf(
                'Currency mismatch: %s vs %s. Use a converter first.',
                $this->currency, $other->currency,
            ));
        }
    }
}
