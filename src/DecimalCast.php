<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use UnexpectedValueException;

/**
 * Stores a Decimal as a JSON column with its three fields: sign, layer, mag.
 *
 * JSON has no number for NaN, the infinities or -0, so a field holding one is
 * written as the string "NaN", "Infinity", "-Infinity" or "-0", the way the
 * differential fixture writes them. Every other value is stored as a number.
 *
 * A null column reads as zero, and setting null stores zero. A column holding
 * a plain string such as "1e1000", or a bare number, reads through
 * Decimal::from. A JSON object missing sign, layer or mag throws an
 * UnexpectedValueException rather than reading as zero.
 *
 * @implements CastsAttributes<Decimal, Decimal|string|float|int|null>
 */
final class DecimalCast implements CastsAttributes
{
    private const array FIELDS = ['sign', 'layer', 'mag'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Decimal
    {
        if (is_int($value) || is_float($value)) {
            return Decimal::fromNumber($value);
        }

        if (! is_string($value)) {
            return Decimal::zero();
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return Decimal::fromString($value);
        }

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $decoded)) {
                throw new UnexpectedValueException("DecimalCast cannot read {$key}: the JSON has no {$field}: {$value}");
            }
        }

        $layer = $decoded['layer'];
        if (! is_int($layer)) {
            throw new UnexpectedValueException("DecimalCast cannot read {$key}: the layer is not a whole number: {$value}");
        }

        return Decimal::fromComponentsNoNormalize(
            self::decodeField($decoded['sign'], $key, $value),
            $layer,
            self::decodeField($decoded['mag'], $key, $value),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $decimal = $value === null ? Decimal::zero() : Decimal::from($value);

        return json_encode([
            'sign' => self::encodeField($decimal->sign),
            'layer' => $decimal->layer,
            'mag' => self::encodeField($decimal->mag),
        ], JSON_THROW_ON_ERROR);
    }

    private static function encodeField(float $value): float|string
    {
        return match (true) {
            is_nan($value) => 'NaN',
            $value === INF => 'Infinity',
            $value === -INF => '-Infinity',
            $value === 0.0 && fdiv(1, $value) < 0 => '-0',
            default => $value,
        };
    }

    private static function decodeField(mixed $field, string $key, string $value): float
    {
        return match (true) {
            is_int($field), is_float($field) => (float) $field,
            $field === 'NaN' => NAN,
            $field === 'Infinity' => INF,
            $field === '-Infinity' => -INF,
            $field === '-0' => -0.0,
            default => throw new UnexpectedValueException("DecimalCast cannot read {$key}: a field is not a number: {$value}"),
        };
    }
}
