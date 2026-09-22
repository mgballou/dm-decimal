<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a Decimal as a JSON column with its three fields: sign, layer, mag.
 *
 * @implements CastsAttributes<Decimal, Decimal|string|float|int>
 */
final class DecimalCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Decimal
    {
        if ($value === null) {
            return Decimal::zero();
        }

        if (is_string($value)) {
            /** @var mixed $decoded */
            $decoded = json_decode($value, true);
            if (
                is_array($decoded)
                && array_key_exists('sign', $decoded)
                && array_key_exists('layer', $decoded)
                && array_key_exists('mag', $decoded)
            ) {
                return Decimal::fromComponentsNoNormalize(
                    (float) $decoded['sign'],
                    (int) $decoded['layer'],
                    (float) $decoded['mag'],
                );
            }

            return Decimal::fromString($value);
        }

        if (is_int($value) || is_float($value)) {
            return Decimal::fromNumber($value);
        }

        return Decimal::zero();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value === null) {
            $decimal = Decimal::zero();
        } elseif ($value instanceof Decimal) {
            $decimal = $value;
        } else {
            $decimal = Decimal::from($value);
        }

        return (string) json_encode([
            'sign' => $decimal->sign,
            'layer' => $decimal->layer,
            'mag' => $decimal->mag,
        ]);
    }
}
