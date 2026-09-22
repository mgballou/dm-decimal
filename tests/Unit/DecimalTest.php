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
