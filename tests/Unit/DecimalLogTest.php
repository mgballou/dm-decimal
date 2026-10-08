<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal\Tests\Unit;

use DreadMajesty\Decimal\DecimalLog;

// Each expected value is what Node 22.23.2 (V8 12.4.254, the engine's pinned
// Node) returns for Math.log10(x) or Math.pow(10, x), on both its x64 and arm64
// builds. The inputs are ones where the platform libm lands on a different
// double, or where textbook fdlibm does.

function bits(float $x): string
{
    return bin2hex(pack('E', $x));
}

test('log10 lands on V8\'s double', function (float $x, float $v8): void {
    expect(bits(DecimalLog::log10($x)))->toBe(bits($v8));
})->with([
    [0.9170208421502695, -0.03762079352519263],
    [1.4878766715154879, 0.17256693450285357],
    [1.3083477447282896, 0.1167231901232668],
    [1.3509089457557357, 0.13062607760197406],
]);

test('pow10 lands on V8\'s double', function (float $x, float $v8): void {
    expect(bits(DecimalLog::pow10($x)))->toBe(bits($v8));
})->with([
    [0.8279563881229794, 6.729090793226229],
    [0.7858512960150055, 6.1073287197901],
    [0.3720239018970821, 2.3551789003129286],
    [2.3225647790434323, 210.16712293932147],
    [-298.62631230554734, 2.364218952325573E-299],
    [-304.8080255839016, 1.5558739737535643E-305],
    // Textbook fdlibm lands 1 ULP low on these; V8 groups one division differently.
    [0.700651200101791, 5.01939299339832],
    [14.079644363035982, 120128032205324.69],
    // A subnormal result, where scaling by 2^n in one step would give 0.
    [-323.4625878044564, 5.0E-324],
]);

test('log10 and pow10 keep the edge cases', function (): void {
    expect(DecimalLog::log10(1.0))->toBe(0.0);
    expect(DecimalLog::log10(1000.0))->toBe(3.0);
    expect(DecimalLog::log10(0.0))->toBe(-INF);
    expect(DecimalLog::log10(INF))->toBe(INF);
    expect(is_nan(DecimalLog::log10(-1.0)))->toBeTrue();
    expect(DecimalLog::log10(5e-324))->toBe(-323.3062153431158);
    expect(DecimalLog::pow10(0.0))->toBe(1.0);
    expect(DecimalLog::pow10(3.0))->toBe(1000.0);
    expect(DecimalLog::pow10(-3.0))->toBe(0.001);
    expect(DecimalLog::pow10(400.0))->toBe(INF);
    expect(DecimalLog::pow10(-400.0))->toBe(0.0);
    expect(DecimalLog::pow10(INF))->toBe(INF);
    expect(is_nan(DecimalLog::pow10(NAN)))->toBeTrue();
});
