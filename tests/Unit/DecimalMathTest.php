<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal\Tests\Unit;

use DreadMajesty\Decimal\Decimal;
use DreadMajesty\Decimal\DecimalMath;

test('add two layer 0 values', function (): void {
    $a = Decimal::fromNumber(10);
    $b = Decimal::fromNumber(5);
    expect((string) DecimalMath::add($a, $b))->toBe('15');
});

test('add with zero', function (): void {
    $a = Decimal::fromNumber(42);
    expect((string) DecimalMath::add($a, Decimal::zero()))->toBe('42');
    expect((string) DecimalMath::add(Decimal::zero(), $a))->toBe('42');
});

test('sub layer 0 values', function (): void {
    $a = Decimal::fromNumber(10);
    $b = Decimal::fromNumber(3);
    expect((string) DecimalMath::sub($a, $b))->toBe('7');
});

test('exact cancellation', function (): void {
    $a = Decimal::fromNumber(42);
    expect(DecimalMath::sub($a, $a)->isZero())->toBeTrue();
});

test('mul layer 0 values', function (): void {
    $a = Decimal::fromNumber(6);
    $b = Decimal::fromNumber(7);
    expect((string) DecimalMath::mul($a, $b))->toBe('42');
});

test('mul by zero', function (): void {
    $a = Decimal::fromNumber(42);
    expect(DecimalMath::mul($a, Decimal::zero())->isZero())->toBeTrue();
});

test('div layer 0 values', function (): void {
    $a = Decimal::fromNumber(42);
    $b = Decimal::fromNumber(6);
    expect((string) DecimalMath::div($a, $b))->toBe('7');
});

test('cmp orders correctly', function (): void {
    $a = Decimal::fromNumber(10);
    $b = Decimal::fromNumber(20);
    expect(DecimalMath::cmp($a, $b))->toBe(-1);
    expect(DecimalMath::cmp($b, $a))->toBe(1);
    expect(DecimalMath::cmp($a, $a))->toBe(0);
});

test('lt, lte, gt, gte', function (): void {
    $a = Decimal::fromNumber(5);
    $b = Decimal::fromNumber(10);
    expect(DecimalMath::lt($a, $b))->toBeTrue();
    expect(DecimalMath::lte($a, $b))->toBeTrue();
    expect(DecimalMath::lte($a, $a))->toBeTrue();
    expect(DecimalMath::gt($b, $a))->toBeTrue();
    expect(DecimalMath::gte($b, $a))->toBeTrue();
    expect(DecimalMath::gte($a, $a))->toBeTrue();
});

test('max and min', function (): void {
    $a = Decimal::fromNumber(3);
    $b = Decimal::fromNumber(7);
    expect((string) DecimalMath::max($a, $b))->toBe('7');
    expect((string) DecimalMath::min($a, $b))->toBe('3');
});

test('floor', function (): void {
    expect((string) DecimalMath::floor(Decimal::fromNumber(3.7)))->toBe('3');
    expect((string) DecimalMath::floor(Decimal::fromNumber(-3.7)))->toBe('-4');
    expect((string) DecimalMath::floor(Decimal::fromNumber(5.0)))->toBe('5');
});

test('pow', function (): void {
    $base = Decimal::fromNumber(10);
    $exp = Decimal::fromNumber(3);
    expect(DecimalMath::pow($base, $exp)->toNumber())->toBe(1000.0);
});

test('log10', function (): void {
    $a = Decimal::fromNumber(1000);
    $result = DecimalMath::log10($a);
    expect(abs($result->toNumber() - 3.0))->toBeLessThan(1e-10);
});

test('layer 1 mul adds exponents', function (): void {
    $a = Decimal::fromString('1e50');
    $b = Decimal::fromString('1e30');
    $result = DecimalMath::mul($a, $b);
    expect((string) $result)->toBe('1e80');
});

test('layer 1 add drops smaller when difference > 17', function (): void {
    $a = Decimal::fromString('1e100');
    $b = Decimal::fromString('1e50');
    $result = DecimalMath::add($a, $b);
    expect((string) $result)->toBe('1e100');
});
