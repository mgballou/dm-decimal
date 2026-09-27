<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal;

use Stringable;

final class Decimal implements Stringable
{
    private const float EXP_LIMIT = 9e15;

    private const float LAYER_DOWN = 15.954242509439325; // log10(9e15)

    private const float FIRST_NEG_LAYER = 1.1111111111111112e-16; // 1 / 9e15

    public const int MAX_SIGNIFICANT_DIGITS = 17;

    private const int NUMBER_EXP_MAX = 308;

    private const int NUMBER_EXP_MIN = -324;

    private const int MAX_ES_IN_A_ROW = 5;

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
                return self::nan();
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

    /**
     * Print this number as break_eternity.js's toString() does: layer 0 as a
     * plain JavaScript number between 1e-7 and 1e21, layer 1 (and layer 0
     * outside that range) as mantissa, "e", exponent, layers 2 to 5 as that
     * many e's before the mag, and higher layers as "(e^N)" before it.
     */
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

        if ($this->layer === 0 && $this->mag < 1e21 && $this->mag > 1e-7) {
            return self::jsNumber($this->sign * $this->mag);
        }

        if ($this->layer <= 1) {
            return self::jsNumber($this->mantissa()) . 'e' . self::jsNumber($this->exponent());
        }

        $prefix = $this->sign === -1.0 ? '-' : '';
        if ($this->layer <= self::MAX_ES_IN_A_ROW) {
            return $prefix . str_repeat('e', $this->layer) . self::jsNumber($this->mag);
        }

        return $prefix . '(e^' . $this->layer . ')' . self::jsNumber($this->mag);
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

            // powerOf10() stops at 1e-323, so break_eternity.js reads the
            // smallest denormal's mantissa as 5 by hand.
            if ($this->mag === 5e-324) {
                return $this->sign * 5;
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
        if (is_nan($sign) || is_nan($mag)) {
            return self::nan();
        }

        if ($sign === 0.0 || ($mag === 0.0 && $layer === 0)) {
            return self::zero();
        }

        if ($layer > 0 && $mag === -INF) {
            return self::zero();
        }

        if (! is_finite($mag)) {
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
     * Print a double as JavaScript's Number#toString() does (ECMA-262,
     * Number::toString): the fewest digits that read back as the same
     * double, plain from 1e-6 up to 1e21 and in exponent form outside it.
     */
    private static function jsNumber(float $value): string
    {
        if (is_nan($value)) {
            return 'NaN';
        }

        if ($value === 0.0) {
            return '0';
        }

        if (is_infinite($value)) {
            return $value > 0 ? 'Infinity' : '-Infinity';
        }

        // The value is 0.d1d2...dk * 10^n.
        [$digits, $n] = self::shortestDigits(abs($value));
        $k = strlen($digits);
        $sign = $value < 0 ? '-' : '';

        if ($k <= $n && $n <= 21) {
            return $sign . $digits . str_repeat('0', $n - $k);
        }

        if (0 < $n && $n <= 21) {
            return $sign . substr($digits, 0, $n) . '.' . substr($digits, $n);
        }

        if (-6 < $n && $n <= 0) {
            return $sign . '0.' . str_repeat('0', -$n) . $digits;
        }

        $exponent = $n - 1;
        $mantissa = $k === 1 ? $digits : $digits[0] . '.' . substr($digits, 1);

        return $sign . $mantissa . 'e' . ($exponent < 0 ? '-' : '+') . abs($exponent);
    }

    /**
     * The fewest decimal digits that read back as $value, with the exponent n
     * that places them: $value = 0.digits * 10^n.
     *
     * With serialize_precision at -1, var_export() prints the same shortest
     * digits V8 does (zend_dtoa's mode 0), but in its own layout, which
     * switches to exponent form past 15 digits. Only the digits are kept.
     *
     * @return array{string, int}
     */
    private static function shortestDigits(float $value): array
    {
        $saved = ini_set('serialize_precision', '-1');

        try {
            $printed = var_export($value, true);
        } finally {
            if ($saved !== false) {
                ini_set('serialize_precision', $saved);
            }
        }

        [$mantissa, $exponent] = array_pad(explode('E', $printed), 2, '0');
        [$whole, $fraction] = array_pad(explode('.', $mantissa), 2, '');

        $all = $whole . $fraction;
        $digits = ltrim($all, '0');
        $n = strlen($whole) + (int) $exponent - (strlen($all) - strlen($digits));

        return [rtrim($digits, '0'), $n];
    }
}
