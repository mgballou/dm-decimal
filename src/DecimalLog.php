<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal;

/**
 * The log10 and pow10 that the arithmetic delegates to.
 *
 * break_eternity.js calls V8's Math.log10() and Math.pow(10, x). PHP's
 * log10() and 10 ** x call the platform libm instead, which lands on a
 * different double for some inputs. Both methods run V8 12.4's fdlibm code
 * through V8Math, so the results match the engine's pinned Node bit for bit.
 */
final class DecimalLog
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    public static function log10(float $x): float
    {
        return V8Math::log10($x);
    }

    public static function pow10(float $x): float
    {
        return V8Math::pow10($x);
    }
}
