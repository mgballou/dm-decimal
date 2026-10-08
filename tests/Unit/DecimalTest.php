<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal\Tests\Unit;

use DreadMajesty\Decimal\Decimal;

test('fromNumber creates layer 0 values', function (): void {
    $d = Decimal::fromNumber(42);
    expect($d->sign)->toBe(1.0);
    expect($d->layer)->toBe(0);
    expect($d->mag)->toBe(42.0);
    expect((string) $d)->toBe('42');
});

test('fromNumber handles zero', function (): void {
    $d = Decimal::fromNumber(0);
    expect($d->isZero())->toBeTrue();
    expect((string) $d)->toBe('0');
});

test('fromNumber handles negative', function (): void {
    $d = Decimal::fromNumber(-15);
    expect($d->sign)->toBe(-1.0);
    expect($d->mag)->toBe(15.0);
    expect((string) $d)->toBe('-15');
});

test('fromString parses plain integers', function (): void {
    $d = Decimal::fromString('100');
    expect((string) $d)->toBe('100');
});

test('fromString parses scientific notation', function (): void {
    $d = Decimal::fromString('1.5e10');
    expect($d->toNumber())->toBe(1.5e10);
});

test('fromString parses large exponents', function (): void {
    $d = Decimal::fromString('1e100');
    expect($d->layer)->toBe(1);
    expect((string) $d)->toBe('1e100');
});

test('fromString parses negative values', function (): void {
    $d = Decimal::fromString('-42');
    expect($d->sign)->toBe(-1.0);
    expect($d->toNumber())->toBe(-42.0);
});

test('fromString handles zero', function (): void {
    expect((string) Decimal::fromString('0'))->toBe('0');
});

test('toString matches JavaScript for layer 0 integers', function (): void {
    expect((string) Decimal::fromNumber(15))->toBe('15');
    expect((string) Decimal::fromNumber(180))->toBe('180');
    expect((string) Decimal::fromNumber(1500))->toBe('1500');
});

// Each string is what break_eternity.js 2.1.3 prints on Node 22.
test('toString matches JavaScript where Number#toString changes form', function (): void {
    expect((string) Decimal::fromNumber(1e-6))->toBe('0.000001');
    expect((string) Decimal::fromComponentsNoNormalize(-1.0, 0, 1e-7))->toBe('-1e-7');
    expect((string) Decimal::fromComponentsNoNormalize(1.0, 0, 5e-324))->toBe('5e-324');
    expect((string) Decimal::fromNumber(123456789012345680))->toBe('1.2345678901234576e17');
    expect((string) Decimal::fromComponents(-1.0, 1, -400.5))->toBe('-3.1622776601683795e-401');
    expect((string) Decimal::fromComponents(1.0, 1, 1e21))->toBe('ee21');
    expect((string) Decimal::fromComponents(1.0, 6, 1.5))->toBe('eeeee31.622776601683793');
    expect((string) Decimal::fromComponentsNoNormalize(1.0, 6, 1.5))->toBe('(e^6)1.5');
});

test('toString prints shortest digits whatever serialize_precision holds', function (): void {
    $saved = ini_set('serialize_precision', '17');

    try {
        expect((string) Decimal::fromNumber(0.1))->toBe('0.1');
        expect(ini_get('serialize_precision'))->toBe('17');
    } finally {
        ini_set('serialize_precision', (string) $saved);
    }
});

test('toNumber round trips for layer 0', function (): void {
    $d = Decimal::fromNumber(3.14159);
    expect($d->toNumber())->toBe(3.14159);
});

test('normalization pushes large values to layer 1', function (): void {
    $d = Decimal::fromString('1e20');
    expect($d->layer)->toBe(1);
});

test('fromComponents creates a valid decimal', function (): void {
    $d = Decimal::fromComponents(1.0, 0, 42.0);
    expect((string) $d)->toBe('42');
});

test('neg produces negation', function (): void {
    $d = Decimal::fromNumber(42);
    expect((string) $d->neg())->toBe('-42');
});

test('abs produces absolute value', function (): void {
    $d = Decimal::fromNumber(-42);
    expect((string) $d->abs())->toBe('42');
});

test('recip computes reciprocal for layer 0', function (): void {
    $d = Decimal::fromNumber(4);
    $r = $d->recip();
    expect($r->toNumber())->toBe(0.25);
});

test('special values', function (): void {
    expect(Decimal::nan()->isNan())->toBeTrue();
    expect(Decimal::inf()->isInfinite())->toBeTrue();
    expect(Decimal::zero()->isZero())->toBeTrue();
    expect((string) Decimal::nan())->toBe('NaN');
    expect((string) Decimal::inf())->toBe('Infinity');
    expect((string) Decimal::negInf())->toBe('-Infinity');
});
