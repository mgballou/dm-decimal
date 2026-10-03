<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal;

/**
 * IEEE 754 double-precision bit manipulation.
 *
 * PHP's pack/unpack give access to the same 64-bit representation V8's C++
 * macros (EXTRACT_WORDS, SET_HIGH_WORD, etc.) operate on.
 *
 * All bit operations use pack('E', $d) which gives big-endian 8 bytes, then
 * manipulate individual bytes to avoid PHP's signed-int overflow on 64-bit
 * quantities that have bit 63 set.
 *
 * @internal
 */
final class Ieee754
{
    /**
     * Extract the high and low 32-bit words from a double.
     *
     * @return array{int, int} [high, low] — high as signed 32-bit, low as unsigned 32-bit
     */
    public static function extractWords(float $d): array
    {
        $bytes = pack('E', $d);
        /** @var array{1: int} $hiArr */
        $hiArr = unpack('N', $bytes, 0);
        /** @var array{1: int} $loArr */
        $loArr = unpack('N', $bytes, 4);
        $hi = $hiArr[1];
        $lo = $loArr[1];

        // Convert high word to signed 32-bit (fdlibm uses signed comparisons)
        if ($hi >= 0x80000000) {
            $hi = (int) ($hi - 0x100000000);
        }

        return [$hi, $lo];
    }

    public static function getHighWord(float $d): int
    {
        $bytes = pack('E', $d);
        /** @var array{1: int} $arr */
        $arr = unpack('N', $bytes, 0);
        $hi = $arr[1];
        if ($hi >= 0x80000000) {
            $hi = (int) ($hi - 0x100000000);
        }

        return $hi;
    }

    public static function getLowWord(float $d): int
    {
        $bytes = pack('E', $d);
        /** @var array{1: int} $arr */
        $arr = unpack('N', $bytes, 4);

        return $arr[1];
    }

    /**
     * Replace the high 32 bits of a double, keeping the low 32 bits.
     */
    public static function setHighWord(float $d, int $v): float
    {
        $bytes = pack('E', $d);
        $hiBytes = pack('N', $v & 0xFFFFFFFF);
        /** @var array{1: float} $arr */
        $arr = unpack('E', $hiBytes . substr($bytes, 4, 4));

        return $arr[1];
    }

    /**
     * Replace the low 32 bits of a double, keeping the high 32 bits.
     */
    public static function setLowWord(float $d, int $v): float
    {
        $bytes = pack('E', $d);
        $loBytes = pack('N', $v & 0xFFFFFFFF);
        /** @var array{1: float} $arr */
        $arr = unpack('E', substr($bytes, 0, 4) . $loBytes);

        return $arr[1];
    }
}
