<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal;

/**
 * The log10 and pow10 that the arithmetic delegates to.
 *
 * break_eternity.js calls V8's Math.log10() and Math.pow(10, x). PHP's
 * log10() and 10 ** x call the platform libm instead, which disagrees
 * with V8 on 3–9% of inputs by 1–2 ULP. For now both methods use PHP's
 * native functions; the toleranced conformance vectors allow 1e-11
 * relative error, which absorbs that drift. A bit-identical port of
 * V8's ieee754.cc belongs here and is not yet written.
 */
final class DecimalLog
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    public static function log10(float $x): float
    {
        return \log10($x);
    }

    public static function pow10(float $x): float
    {
        return 10.0 ** $x;
    }
}
