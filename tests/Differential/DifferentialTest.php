<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal\Tests\Differential;

use DreadMajesty\Decimal\Decimal;
use DreadMajesty\Decimal\DecimalMath;

// fixtures/differential.json holds seeded inputs and break_eternity.js's
// results for them; tools/differential/generate.mjs writes it. Every result
// must match in sign, layer and mag, bit for bit, and print the same string.

/**
 * Cases known not to match, by name, each with the reason.
 *
 * @var array<string, string>
 */
const INEXACT = [];

/** @var array{cases: list<array{name: string, op: string, a: list<int|float|string>, b?: list<int|float|string>, want: int|list<int|float|string>, str?: string}>} $fixture */
$fixture = json_decode(
    (string) file_get_contents(__DIR__ . '/../../fixtures/differential.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);

$byOp = [];
foreach ($fixture['cases'] as $case) {
    $byOp[$case['op']][] = $case;
}

foreach ($byOp as $op => $cases) {
    test("{$op} matches break_eternity.js", function () use ($cases): void {
        $misses = [];

        foreach ($cases as $case) {
            $miss = differentialMiss($case);
            $known = array_key_exists($case['name'], INEXACT);

            if ($miss !== null && ! $known) {
                $misses[] = "{$case['name']}: {$miss}";
            }
            if ($miss === null && $known) {
                $misses[] = "{$case['name']}: listed as inexact but matches; remove it from INEXACT";
            }
        }

        expect($misses)->toBe([], implode("\n", $misses));
    });
}

test('every inexact case names a case in the fixture', function () use ($fixture): void {
    $names = array_column($fixture['cases'], 'name');

    expect(array_diff(array_keys(INEXACT), $names))->toBe([]);
});

/**
 * @param  array{name: string, op: string, a: list<int|float|string>, b?: list<int|float|string>, want: int|list<int|float|string>, str?: string}  $case
 */
function differentialMiss(array $case): ?string
{
    $a = differentialDecimal($case['a']);
    $b = isset($case['b']) ? differentialDecimal($case['b']) : null;

    if ($case['op'] === 'cmp') {
        $got = DecimalMath::cmp($a, $b ?? Decimal::zero());

        return $got === $case['want'] ? null : "want {$case['want']}, got {$got}";
    }

    $got = match ($case['op']) {
        'add' => DecimalMath::add($a, $b ?? Decimal::zero()),
        'sub' => DecimalMath::sub($a, $b ?? Decimal::zero()),
        'mul' => DecimalMath::mul($a, $b ?? Decimal::zero()),
        'div' => DecimalMath::div($a, $b ?? Decimal::zero()),
        'pow' => DecimalMath::pow($a, $b ?? Decimal::zero()),
        'log10' => DecimalMath::log10($a),
        'pow10' => DecimalMath::pow10($a),
        'floor' => DecimalMath::floor($a),
        'ceil' => DecimalMath::ceil($a),
        'toString' => $a,
    };

    /** @var list<int|float|string> $want */
    $want = $case['want'];
    $expected = differentialDecimal($want);

    if (
        differentialSame($got->sign, $expected->sign)
        && $got->layer === $expected->layer
        && differentialSame($got->mag, $expected->mag)
    ) {
        $want = $case['str'] ?? null;

        return $want === null || (string) $got === $want
            ? null
            : "want \"{$want}\", got \"{$got}\"";
    }

    return sprintf(
        'want [%s, %s, %s], got [%s, %s, %s]',
        differentialShow($expected->sign),
        $expected->layer,
        differentialShow($expected->mag),
        differentialShow($got->sign),
        $got->layer,
        differentialShow($got->mag),
    );
}

/**
 * The fixture writes NaN, the infinities and -0 as strings. break_eternity
 * keeps a NaN or infinite layer; the PHP port stores the layer as an int, so
 * those arrive as Decimal::nan()'s 0 and Decimal::inf()'s PHP_INT_MAX.
 *
 * @param  list<int|float|string>  $triple
 */
function differentialDecimal(array $triple): Decimal
{
    [$sign, $layer, $mag] = $triple;

    $layer = match ($layer) {
        'NaN' => 0,
        'Infinity' => PHP_INT_MAX,
        default => (int) $layer,
    };

    return Decimal::fromComponentsNoNormalize(differentialFloat($sign), $layer, differentialFloat($mag));
}

function differentialFloat(int|float|string $value): float
{
    return match ($value) {
        'NaN' => NAN,
        'Infinity' => INF,
        '-Infinity' => -INF,
        '-0' => -0.0,
        default => (float) $value,
    };
}

/** Two doubles with the same bits; any NaN matches any NaN. */
function differentialSame(float $got, float $want): bool
{
    if (is_nan($got) || is_nan($want)) {
        return is_nan($got) && is_nan($want);
    }

    return pack('E', $got) === pack('E', $want);
}

function differentialShow(float $value): string
{
    return is_nan($value) ? 'NaN' : var_export($value, true);
}
