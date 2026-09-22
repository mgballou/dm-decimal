<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal;

final class DecimalMath
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    public static function cmp(Decimal $a, Decimal $b): int
    {
        if ($a->sign > $b->sign) {
            return 1;
        }
        if ($a->sign < $b->sign) {
            return -1;
        }

        return (int) ($a->sign * self::cmpabs($a, $b));
    }

    public static function cmpabs(Decimal $a, Decimal $b): int
    {
        $layera = $a->mag > 0 ? $a->layer : -$a->layer;
        $layerb = $b->mag > 0 ? $b->layer : -$b->layer;

        if ($layera > $layerb) {
            return 1;
        }
        if ($layera < $layerb) {
            return -1;
        }
        if ($a->mag > $b->mag) {
            return 1;
        }
        if ($a->mag < $b->mag) {
            return -1;
        }

        return 0;
    }

    public static function eq(Decimal $a, Decimal $b): bool
    {
        return $a->sign === $b->sign && $a->layer === $b->layer && $a->mag === $b->mag;
    }

    public static function lt(Decimal $a, Decimal $b): bool
    {
        return self::cmp($a, $b) === -1;
    }

    public static function lte(Decimal $a, Decimal $b): bool
    {
        return ! self::gt($a, $b);
    }

    public static function gt(Decimal $a, Decimal $b): bool
    {
        return self::cmp($a, $b) === 1;
    }

    public static function gte(Decimal $a, Decimal $b): bool
    {
        return ! self::lt($a, $b);
    }

    public static function max(Decimal $a, Decimal $b): Decimal
    {
        return self::lt($a, $b) ? $b : $a;
    }

    public static function min(Decimal $a, Decimal $b): Decimal
    {
        return self::gt($a, $b) ? $b : $a;
    }

    public static function maxabs(Decimal $a, Decimal $b): Decimal
    {
        return self::cmpabs($a, $b) < 0 ? $b : $a;
    }

    public static function add(Decimal $a, Decimal $b): Decimal
    {
        if ($a->isNan() || $b->isNan()) {
            return Decimal::nan();
        }

        if (! is_finite($a->layer)) {
            return $a;
        }
        if (! is_finite($b->layer)) {
            return $b;
        }

        if ($a->sign === 0.0) {
            return $b;
        }
        if ($b->sign === 0.0) {
            return $a;
        }

        // Exact cancellation
        if ($a->sign === -$b->sign && $a->layer === $b->layer && $a->mag === $b->mag) {
            return Decimal::zero();
        }

        // Layer >= 2: drop the smaller operand
        if ($a->layer >= 2 || $b->layer >= 2) {
            return self::maxabs($a, $b);
        }

        // Sort by absolute value: $big >= $small
        if (self::cmpabs($a, $b) > 0) {
            $big = $a;
            $small = $b;
        } else {
            $big = $b;
            $small = $a;
        }

        // Both layer 0: plain addition
        if ($big->layer === 0 && $small->layer === 0) {
            return Decimal::fromNumber($big->sign * $big->mag + $small->sign * $small->mag);
        }

        $layerBig = $big->layer * ($big->mag > 0 ? 1 : -1);
        $layerSmall = $small->layer * ($small->mag > 0 ? 1 : -1);

        // Drop smaller when effective layers differ by >= 2
        if ($layerBig - $layerSmall >= 2) {
            return $big;
        }

        if ($layerBig === 0 && $layerSmall === -1) {
            // big is layer 0, small has negative mag at layer 1 (very small)
            if (abs($small->mag - DecimalLog::log10($big->mag)) > Decimal::MAX_SIGNIFICANT_DIGITS) {
                return $big;
            }
            $magdiff = DecimalLog::pow10(DecimalLog::log10($big->mag) - $small->mag);
            $mantissa = $small->sign + $big->sign * $magdiff;

            return Decimal::fromComponents(
                (float) ($mantissa > 0 ? 1 : ($mantissa < 0 ? -1 : 0)),
                1,
                $small->mag + DecimalLog::log10(abs($mantissa)),
            );
        }

        if ($layerBig === 1 && $layerSmall === 0) {
            // big is layer 1, small is layer 0
            if (abs($big->mag - DecimalLog::log10($small->mag)) > Decimal::MAX_SIGNIFICANT_DIGITS) {
                return $big;
            }
            $magdiff = DecimalLog::pow10($big->mag - DecimalLog::log10($small->mag));
            $mantissa = $small->sign + $big->sign * $magdiff;

            return Decimal::fromComponents(
                (float) ($mantissa > 0 ? 1 : ($mantissa < 0 ? -1 : 0)),
                1,
                DecimalLog::log10($small->mag) + DecimalLog::log10(abs($mantissa)),
            );
        }

        // Both layer 1 or other cases
        if (abs($big->mag - $small->mag) > Decimal::MAX_SIGNIFICANT_DIGITS) {
            return $big;
        }
        $magdiff = DecimalLog::pow10($big->mag - $small->mag);
        $mantissa = $small->sign + $big->sign * $magdiff;

        return Decimal::fromComponents(
            (float) ($mantissa > 0 ? 1 : ($mantissa < 0 ? -1 : 0)),
            1,
            $small->mag + DecimalLog::log10(abs($mantissa)),
        );
    }

    public static function sub(Decimal $a, Decimal $b): Decimal
    {
        return self::add($a, $b->neg());
    }

    public static function mul(Decimal $a, Decimal $b): Decimal
    {
        if ($a->isNan() || $b->isNan()) {
            return Decimal::nan();
        }

        if ($a->sign === 0.0 || $b->sign === 0.0) {
            return Decimal::zero();
        }

        // Self-inverse: x * (1/x) = 1 at same layer
        if ($a->layer === $b->layer && $a->mag === -$b->mag) {
            return Decimal::fromComponentsNoNormalize($a->sign * $b->sign, 0, 1.0);
        }

        // Sort by magnitude
        if ($a->layer > $b->layer || ($a->layer === $b->layer && abs($a->mag) > abs($b->mag))) {
            $big = $a;
            $small = $b;
        } else {
            $big = $b;
            $small = $a;
        }

        $resultSign = $big->sign * $small->sign;

        // Both layer 0: plain multiply
        if ($big->layer === 0 && $small->layer === 0) {
            return Decimal::fromNumber($resultSign * $big->mag * $small->mag);
        }

        // Layer >= 3 or difference >= 2: drop smaller
        if ($big->layer >= 3 || $big->layer - $small->layer >= 2) {
            return Decimal::fromComponentsNoNormalize($resultSign, $big->layer, $big->mag);
        }

        // Layer 1 * layer 0
        if ($big->layer === 1 && $small->layer === 0) {
            return Decimal::fromComponents(
                $resultSign,
                1,
                $big->mag + DecimalLog::log10($small->mag),
            );
        }

        // Layer 1 * layer 1: add exponents
        if ($big->layer === 1 && $small->layer === 1) {
            return Decimal::fromComponents(
                $resultSign,
                1,
                $big->mag + $small->mag,
            );
        }

        // Layer 2 cases: use log-addition
        if ($big->layer === 2 && $small->layer === 1) {
            $newMag = Decimal::fromComponents(1.0, $big->layer - 1, $big->mag);
            $smallMag = Decimal::fromComponents(1.0, $small->layer - 1, $small->mag);

            return Decimal::fromComponents(
                $resultSign,
                $big->layer,
                self::add($newMag, $smallMag)->mag,
            );
        }

        if ($big->layer === 2 && $small->layer === 2) {
            $newMag = Decimal::fromComponents(1.0, $big->layer - 1, $big->mag);
            $smallMag = Decimal::fromComponents(1.0, $small->layer - 1, $small->mag);

            return Decimal::fromComponents(
                $resultSign,
                $big->layer,
                self::add($newMag, $smallMag)->mag,
            );
        }

        return Decimal::nan();
    }

    public static function div(Decimal $a, Decimal $b): Decimal
    {
        return self::mul($a, $b->recip());
    }

    public static function log10(Decimal $a): Decimal
    {
        if ($a->sign <= 0) {
            return Decimal::nan();
        }

        if ($a->layer > 0) {
            return Decimal::fromComponents(
                (float) ($a->mag > 0 ? 1 : ($a->mag < 0 ? -1 : 0)),
                $a->layer - 1,
                abs($a->mag),
            );
        }

        return Decimal::fromComponents($a->sign, 0, DecimalLog::log10($a->mag));
    }

    public static function absLog10(Decimal $a): Decimal
    {
        if ($a->sign === 0.0) {
            return Decimal::nan();
        }

        return self::log10($a->abs());
    }

    public static function pow10(Decimal $a): Decimal
    {
        if ($a->isNan()) {
            return Decimal::nan();
        }

        if (! is_finite($a->mag)) {
            if ($a->mag > 0) {
                return $a->sign > 0 ? Decimal::inf() : Decimal::zero();
            }

            return Decimal::nan();
        }

        if ($a->layer === 0) {
            $newmag = DecimalLog::pow10($a->sign * $a->mag);
            if (is_finite($newmag) && abs($newmag) >= 0.1) {
                return Decimal::fromComponentsNoNormalize(1.0, 0, $newmag);
            }

            if ($a->sign === 0.0) {
                return Decimal::one();
            }

            return Decimal::fromComponents(
                $a->sign,
                $a->layer + 1,
                DecimalLog::log10($a->mag),
            );
        }

        if ($a->sign > 0 && $a->mag >= 0) {
            return Decimal::fromComponentsNoNormalize($a->sign, $a->layer + 1, $a->mag);
        }

        if ($a->sign < 0 && $a->mag >= 0) {
            return Decimal::fromComponentsNoNormalize(-$a->sign, $a->layer + 1, -$a->mag);
        }

        return Decimal::one();
    }

    public static function pow(Decimal $base, Decimal $exp): Decimal
    {
        if ($base->sign === 0.0) {
            return self::eq($exp, Decimal::zero()) ? Decimal::one() : $base;
        }

        if (self::eq($base, Decimal::one())) {
            return $base;
        }

        if ($exp->sign === 0.0) {
            return Decimal::one();
        }

        if (self::eq($exp, Decimal::one())) {
            return $base;
        }

        $result = self::pow10(self::mul(self::absLog10($base), $exp));

        if ($base->sign === -1.0) {
            $mod = fmod(abs($exp->toNumber()), 2.0);
            if ($mod === 1.0) {
                return $result->neg();
            }
            if ($mod === 0.0) {
                return $result;
            }

            return Decimal::nan();
        }

        return $result;
    }

    public static function floor(Decimal $a): Decimal
    {
        if ($a->isNan()) {
            return Decimal::nan();
        }

        if ($a->mag < 0) {
            return $a->sign === -1.0
                ? Decimal::fromComponentsNoNormalize(-1.0, 0, 1.0)
                : Decimal::zero();
        }

        if ($a->sign === -1.0) {
            return self::ceil($a->neg())->neg();
        }

        if ($a->layer === 0) {
            return Decimal::fromComponents($a->sign, 0, floor($a->mag));
        }

        return $a;
    }

    public static function ceil(Decimal $a): Decimal
    {
        if ($a->isNan()) {
            return Decimal::nan();
        }

        if ($a->mag < 0) {
            return $a->sign === 1.0
                ? Decimal::one()
                : Decimal::zero();
        }

        if ($a->sign === -1.0) {
            return self::floor($a->neg())->neg();
        }

        if ($a->layer === 0) {
            return Decimal::fromComponents($a->sign, 0, ceil($a->mag));
        }

        return $a;
    }
}
