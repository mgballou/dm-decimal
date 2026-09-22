<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal;

use Stringable;

final class Decimal implements Stringable
{
    private const float EXP_LIMIT = 9e15;

    private const float LAYER_DOWN = 15.954589770191003; // log10(9e15)

    private const float FIRST_NEG_LAYER = 1.1111111111111112e-16; // 1 / 9e15

    public const int MAX_SIGNIFICANT_DIGITS = 17;

    private const int NUMBER_EXP_MAX = 308;

    private const int NUMBER_EXP_MIN = -324;

    public readonly float $sign;

    public readonly int $layer;

    public readonly float $mag;

    private function __construct(float $sign, int $layer, float $mag)
    {
        $this->sign = $sign;
        $this->layer = $layer;
        $this->mag = $mag;
    }

    public static function fromComponents(float $sign, int $layer, float $mag): self
    {
        return self::normalize($sign, $layer, $mag);
    }

    public static function fromComponentsNoNormalize(float $sign, int $layer, float $mag): self
    {
        return new self($sign, $layer, $mag);
    }

    public static function fromNumber(float|int $value): self
    {
        $value = (float) $value;

        if (is_nan($value)) {
            return self::nan();
        }

        if (! is_finite($value)) {
            return $value > 0 ? self::inf() : self::negInf();
        }

        if ($value === 0.0 || $value === -0.0) {
            return self::zero();
        }

        return self::normalize((float) ($value > 0 ? 1 : -1), 0, abs($value));
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === 'NaN') {
            return self::nan();
        }

        if ($value === 'Infinity' || $value === '+Infinity') {
            return self::inf();
        }

        if ($value === '-Infinity') {
            return self::negInf();
        }

        if ($value === '' || $value === '0') {
            return self::zero();
        }

        $sign = 1.0;
        if ($value[0] === '-') {
            $sign = -1.0;
            $value = substr($value, 1);
        } elseif ($value[0] === '+') {
            $value = substr($value, 1);
        }

        // Count e's for multi-e notation (e.g. "1e1e10" = "ee10")
        $parts = explode('e', strtolower($value));
        $eCount = count($parts) - 1;

        if ($eCount === 0) {
            $num = (float) $value;
            if (is_nan($num) || ! is_finite($num)) {
                return $num === 0.0 ? self::zero() : self::nan();
            }

            return $num === 0.0
                ? self::zero()
                : self::normalize($sign, 0, $num);
        }

        if ($eCount === 1) {
            $num = (float) $value;
            if (is_finite($num) && $num !== 0.0) {
                return self::fromNumber($sign * $num);
            }
            // Large exponent: parse mantissa and exponent separately
            $mantissa = (float) $parts[0];
            if ($mantissa === 0.0) {
                return self::zero();
            }

            $exponent = (float) $parts[1];
            if (abs($exponent) < self::EXP_LIMIT) {
                $result = self::normalize($sign * ($mantissa > 0 ? 1 : -1), 1, $exponent + DecimalLog::log10(abs($mantissa)));

                return $result;
            }

            return self::normalize($sign * ($mantissa > 0 ? 1 : -1), 1, $exponent);
        }

        // Multi-e notation: ee100 means 10^10^100
        if ($eCount === 2) {
            $mantissa = (float) $parts[0];
            if ($mantissa === 0.0) {
                return self::zero();
            }

            $exponent = (float) ($parts[1] . 'e' . $parts[2]);

            return self::normalize($sign * ($mantissa > 0 ? 1 : -1), 2, $exponent);
        }

        // 3+ e's: each adds a layer
        $mag = (float) $parts[$eCount];
        for ($i = $eCount - 1; $i >= 1; $i--) {
            $part = (float) $parts[$i];
            if ($part !== 0.0) {
                $mag = DecimalLog::log10(abs($part)) + $mag;
            }
        }

        $mantissa = (float) $parts[0];
        if ($mantissa === 0.0) {
            return self::zero();
        }

        return self::normalize(
            $sign * ($mantissa > 0 ? 1 : -1),
            $eCount,
            $mag,
        );
    }

    public static function from(self|string|float|int $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value)) {
            return self::fromString($value);
        }

        return self::fromNumber($value);
    }

    public static function zero(): self
    {
        return new self(0.0, 0, 0.0);
    }

    public static function one(): self
    {
        return new self(1.0, 0, 1.0);
    }

    public static function nan(): self
    {
        return new self(NAN, 0, NAN);
    }

    public static function inf(): self
    {
        return new self(1.0, PHP_INT_MAX, INF);
    }

    public static function negInf(): self
    {
        return new self(-1.0, PHP_INT_MAX, INF);
    }

    public function isNan(): bool
    {
        return is_nan($this->sign) || is_nan($this->mag);
    }

    public function isInfinite(): bool
    {
        return ! is_finite($this->mag);
    }

    public function isZero(): bool
    {
        return $this->sign === 0.0 && $this->layer === 0 && $this->mag === 0.0;
    }

    public function toNumber(): float
    {
        if ($this->isNan()) {
            return NAN;
        }

        if ($this->layer === 0) {
            return $this->sign * $this->mag;
        }

        if ($this->layer === 1) {
            return $this->sign * DecimalLog::pow10($this->mag);
        }

        return $this->mag > 0
            ? ($this->sign > 0 ? INF : -INF)
            : 0.0;
    }

    public function __toString(): string
    {
        if ($this->isNan()) {
            return 'NaN';
        }

        if (! is_finite($this->mag)) {
            return $this->sign === 1.0 ? 'Infinity' : '-Infinity';
        }

        if ($this->sign === 0.0 || ($this->layer === 0 && $this->mag === 0.0)) {
            return '0';
        }

        if ($this->layer === 0) {
            $val = $this->sign * $this->mag;
            if (abs($val) < 1e21 && abs($val) > 1e-7) {
                return self::formatFloat($val);
            }

            return $this->mantissaExponentString();
        }

        if ($this->layer === 1) {
            return $this->mantissaExponentString();
        }

        // layer >= 2
        $prefix = $this->sign === -1.0 ? '-' : '';
        if ($this->layer <= 5) {
            return $prefix . str_repeat('e', $this->layer) . self::formatFloat($this->mag);
        }

        return $prefix . '(e^' . $this->layer . ')' . self::formatFloat($this->mag);
    }

    private function mantissaExponentString(): string
    {
        if ($this->layer === 0) {
            $e = (int) floor(DecimalLog::log10($this->mag));
            $m = $this->sign * $this->mag / self::powerOf10($e);

            return self::formatFloat($m) . 'e' . $e;
        }

        if ($this->layer === 1) {
            $e = (int) floor($this->mag);
            $m = $this->sign * DecimalLog::pow10($this->mag - $e);

            return self::formatFloat($m) . 'e' . $e;
        }

        return ($this->sign === -1.0 ? '-' : '') . str_repeat('e', $this->layer) . self::formatFloat($this->mag);
    }

    /**
     * Get the mantissa (significand) of this number in scientific notation.
     */
    public function mantissa(): float
    {
        if ($this->layer === 0) {
            if ($this->mag === 0.0) {
                return 0.0;
            }

            $e = (int) floor(DecimalLog::log10($this->mag));

            return $this->sign * $this->mag / self::powerOf10($e);
        }

        if ($this->layer === 1) {
            $residue = $this->mag - floor($this->mag);

            return $this->sign * DecimalLog::pow10($residue);
        }

        return $this->sign;
    }

    /**
     * Get the exponent of this number in scientific notation.
     */
    public function exponent(): float
    {
        if ($this->layer === 0) {
            if ($this->mag === 0.0) {
                return 0;
            }

            return floor(DecimalLog::log10($this->mag));
        }

        if ($this->layer === 1) {
            return floor($this->mag);
        }

        if ($this->layer === 2) {
            return floor(DecimalLog::pow10($this->mag));
        }

        return $this->mag * INF;
    }

    public function neg(): self
    {
        return new self(-$this->sign, $this->layer, $this->mag);
    }

    public function abs(): self
    {
        return new self($this->sign === 0.0 ? 0.0 : 1.0, $this->layer, $this->mag);
    }

    /**
     * Reciprocal: 1/x.
     */
    public function recip(): self
    {
        if ($this->isNan() || $this->mag === 0.0) {
            return self::nan();
        }

        if (! is_finite($this->mag)) {
            return self::zero();
        }

        if ($this->layer === 0) {
            return new self($this->sign, 0, 1.0 / $this->mag);
        }

        return self::fromComponents($this->sign, $this->layer, -$this->mag);
    }

    private static function normalize(float $sign, int $layer, float $mag): self
    {
        if (is_nan($sign) || is_nan($layer) || is_nan($mag)) {
            return self::nan();
        }

        if ($sign === 0.0 || ($mag === 0.0 && $layer === 0)) {
            return self::zero();
        }

        if ($layer > 0 && $mag === -INF) {
            return self::zero();
        }

        if (! is_finite($mag) || ! is_finite($layer)) {
            return $sign > 0 ? self::inf() : self::negInf();
        }

        // At layer 0, negative mag flips sign
        if ($layer === 0 && $mag < 0) {
            $mag = -$mag;
            $sign = -$sign;
        }

        // Very small layer 0 value -> push to layer 1
        if ($layer === 0 && $mag < self::FIRST_NEG_LAYER) {
            $layer = 1;
            $mag = DecimalLog::log10($mag);

            return new self($sign, $layer, $mag);
        }

        // If |mag| >= EXP_LIMIT, push up a layer
        if (abs($mag) >= self::EXP_LIMIT) {
            $layer += 1;
            $mag = ($mag > 0 ? 1 : -1) * DecimalLog::log10(abs($mag));

            return new self($sign, $layer, $mag);
        }

        // If |mag| < LAYER_DOWN and layer > 0, pull down
        while (abs($mag) < self::LAYER_DOWN && $layer > 0) {
            $layer -= 1;
            if ($layer === 0) {
                $mag = DecimalLog::pow10($mag);
            } else {
                $mag = ($mag > 0 ? 1 : -1) * DecimalLog::pow10(abs($mag));
            }
        }

        // Final layer 0 sign adjustment
        if ($layer === 0) {
            if ($mag < 0) {
                $mag = -$mag;
                $sign = -$sign;
            } elseif ($mag === 0.0) {
                $sign = 0.0;
            }
        }

        if (is_nan($sign) || is_nan($mag)) {
            return self::nan();
        }

        return new self($sign, $layer, $mag);
    }

    private static function powerOf10(int $power): float
    {
        if ($power >= self::NUMBER_EXP_MIN + 1 && $power <= self::NUMBER_EXP_MAX) {
            return (float) ('1e' . $power);
        }

        return DecimalLog::pow10((float) $power);
    }

    /**
     * Format a float to match JavaScript's Number.prototype.toString().
     *
     * JS rules: no scientific notation for values in [1e-7, 1e21),
     * scientific notation (lowercase 'e') outside that range, and
     * exactly the digits needed to round-trip the double.
     */
    private static function formatFloat(float $value): string
    {
        if ($value === 0.0) {
            return '0';
        }

        if (is_nan($value)) {
            return 'NaN';
        }

        if (! is_finite($value)) {
            return $value > 0 ? 'Infinity' : '-Infinity';
        }

        $abs = abs($value);

        // Integers that fit in a double should format without decimal point
        if ($abs < 1e21 && $abs >= 1.0 && $abs === floor($abs)) {
            return (string) (int) $value;
        }

        // PHP's default (string) uses E+ notation for large/small floats.
        // JS uses no scientific notation for [1e-7, 1e21).
        if ($abs < 1e21 && $abs > 1e-7) {
            // Use sprintf with enough precision to round-trip
            $str = sprintf('%.17G', $value);

            // Trim trailing zeros after decimal point
            if (str_contains($str, '.')) {
                $str = rtrim(rtrim($str, '0'), '.');
            }

            // If it looks like scientific notation, PHP went there anyway
            if (str_contains($str, 'E') || str_contains($str, 'e')) {
                return self::formatScientific($value);
            }

            return $str;
        }

        return self::formatScientific($value);
    }

    private static function formatScientific(float $value): string
    {
        $str = sprintf('%.17E', $value);
        // Parse mantissa and exponent
        if (preg_match('/^(-?\d+\.\d+)E([+-]\d+)$/', $str, $m) === 1) {
            $mantissa = rtrim(rtrim($m[1], '0'), '.');
            $exp = (int) $m[2];

            return $mantissa . 'e' . ($exp >= 0 ? '+' : '') . $exp;
        }

        return str_replace('E', 'e', $str);
    }
}
