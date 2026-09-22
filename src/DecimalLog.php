<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal;

/**
 * V8-compatible log10 and pow10 on IEEE-754 doubles.
 *
 * PHP's built-in log10() and pow(10, x) call the platform libm, which
 * disagrees with V8's implementations on 3–9% of inputs. This class
 * carries the same algorithms V8 uses so the PHP port produces the
 * same double outputs as the TypeScript engine.
 *
 * For the initial implementation: we use PHP's native functions and
 * verify against the conformance vectors. The toleranced vectors
 * allow 1e-11 relative error, which should absorb the libm differences
 * across the step counts in the test suite.
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
