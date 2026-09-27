<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal;

// Adapted from V8 12.4.254 src/base/ieee754.cc, which adapts fdlibm:
//
// Copyright (C) 1993 by Sun Microsystems, Inc. All rights reserved.
//
// Developed at SunSoft, a Sun Microsystems, Inc. business.
// Permission to use, copy, modify, and distribute this
// software is freely granted, provided that this notice
// is preserved.
//
// Copyright 2016 the V8 project authors. All rights reserved. See LICENSE.

/**
 * V8's fdlibm-derived log, log10 and pow, transliterated to PHP.
 *
 * break_eternity.js calls Math.log10() and Math.pow(10, x), which V8 12.4
 * (Node 22, the engine's pinned Node) answers from src/base/ieee754.cc. PHP's
 * log10() and ** call the platform libm, which lands on a different double
 * for about one pow10 input in ten. This class carries V8's code so the PHP
 * Decimal lands where the engine does.
 *
 * Plain PHP arithmetic never fuses a multiply and an add, so these give the
 * same bits on every platform, and match Node 22's x64 build on every input
 * tried. Node's arm64 build lets the compiler fuse some of them and lands
 * 1 ULP away on about one pow10 input in two thousand, and one log10 input
 * in twenty thousand. V8 13 (Node 24) sends
 * Math.pow to the platform libm, so this pow does not match it.
 *
 * Source: V8 12.4.254 (Node v22), deps/v8/src/base/ieee754.cc
 *
 * @internal
 */
final class V8Math
{
    /**
     * V8's log(x) — fdlibm __ieee754_log.
     *
     * Transliterated from V8 12.4.254 ieee754.cc lines 1638-1717.
     */
    public static function log(float $x): float
    {
        $ln2_hi = 6.93147180369123816490e-01;
        $ln2_lo = 1.90821492927058770002e-10;
        $two54 = 1.80143985094819840000e+16;
        $Lg1 = 6.666666666666735130e-01;
        $Lg2 = 3.999999999940941908e-01;
        $Lg3 = 2.857142874366239149e-01;
        $Lg4 = 2.222219843214978396e-01;
        $Lg5 = 1.818357216161805012e-01;
        $Lg6 = 1.531383769920937332e-01;
        $Lg7 = 1.479819860511658591e-01;

        [$hx, $lx] = Ieee754::extractWords($x);

        $k = 0;
        if ($hx < 0x00100000) {
            if ((($hx & 0x7FFFFFFF) | $lx) === 0) {
                return -INF;
            }
            if ($hx < 0) {
                return NAN;
            }
            $k -= 54;
            $x *= $two54;
            $hx = Ieee754::getHighWord($x);
        }
        if ($hx >= 0x7FF00000) {
            return $x + $x;
        }
        $k += ($hx >> 20) - 1023;
        $hx &= 0x000FFFFF;
        $i = ($hx + 0x95F64) & 0x100000;
        $x = Ieee754::setHighWord($x, $hx | ($i ^ 0x3FF00000));
        $k += ($i >> 20);
        $f = $x - 1.0;

        if ((0x000FFFFF & (2 + $hx)) < 3) {
            if ($f == 0.0) {
                if ($k === 0) {
                    return 0.0;
                }
                $dk = (float) $k;

                return $dk * $ln2_hi + $dk * $ln2_lo;
            }
            $R = $f * $f * (0.5 - 0.33333333333333333 * $f);
            if ($k === 0) {
                return $f - $R;
            }
            $dk = (float) $k;

            return $dk * $ln2_hi - (($R - $dk * $ln2_lo) - $f);
        }

        $s = $f / (2.0 + $f);
        $dk = (float) $k;
        $z = $s * $s;
        $i = $hx - 0x6147A;
        $w = $z * $z;
        $j = 0x6B851 - $hx;
        $t1 = $w * ($Lg2 + $w * ($Lg4 + $w * $Lg6));
        $t2 = $z * ($Lg1 + $w * ($Lg3 + $w * ($Lg5 + $w * $Lg7)));
        $i |= $j;
        $R = $t2 + $t1;

        if ($i > 0) {
            $hfsq = 0.5 * $f * $f;
            if ($k === 0) {
                return $f - ($hfsq - $s * ($hfsq + $R));
            }

            return $dk * $ln2_hi - (($hfsq - ($s * ($hfsq + $R) + $dk * $ln2_lo)) - $f);
        }

        if ($k === 0) {
            return $f - $s * ($f - $R);
        }

        return $dk * $ln2_hi - (($s * ($f - $R) - $dk * $ln2_lo) - $f);
    }

    /**
     * V8's log10(x) — fdlibm __ieee754_log10.
     *
     * Transliterated from V8 12.4.254 ieee754.cc lines 2079-2117.
     * Calls self::log() rather than PHP's log().
     */
    public static function log10(float $x): float
    {
        $two54 = 1.80143985094819840000e+16;
        $ivln10 = 4.34294481903251816668e-01;
        $log10_2hi = 3.01029995663611771306e-01;
        $log10_2lo = 3.69423907715893078616e-13;

        [$hx, $lx] = Ieee754::extractWords($x);

        $k = 0;
        if ($hx < 0x00100000) {
            if ((($hx & 0x7FFFFFFF) | $lx) === 0) {
                return -INF;
            }
            if ($hx < 0) {
                return NAN;
            }
            $k -= 54;
            $x *= $two54;
            $hx = Ieee754::getHighWord($x);
            $lx = Ieee754::getLowWord($x);
        }
        if ($hx >= 0x7FF00000) {
            return $x + $x;
        }
        if ($hx === 0x3FF00000 && $lx === 0) {
            return 0.0;
        }
        $k += ($hx >> 20) - 1023;

        // C's (k & 0x80000000) >> 31 on a 32-bit k: the sign bit.
        $i = ($k < 0) ? 1 : 0;
        $hx = ($hx & 0x000FFFFF) | ((0x3FF - $i) << 20);
        $y = (float) ($k + $i);
        $x = Ieee754::setHighWord($x, $hx);
        $x = Ieee754::setLowWord($x, $lx);

        $z = $y * $log10_2lo + $ivln10 * self::log($x);

        return $z + $y * $log10_2hi;
    }

    /**
     * V8's pow(x, y) — fdlibm __ieee754_pow.
     *
     * Transliterated from V8 12.4.254 ieee754.cc lines 2645-2906.
     */
    public static function pow(float $x, float $y): float
    {
        $bp = [1.0, 1.5];
        $dp_h = [0.0, 5.84962487220764160156e-01];
        $dp_l = [0.0, 1.35003920212974897128e-08];
        $two53 = 9007199254740992.0;
        $huge = 1.0e300;
        $tiny = 1.0e-300;
        $L1 = 5.99999999999994648725e-01;
        $L2 = 4.28571428578550184252e-01;
        $L3 = 3.33333329818377432918e-01;
        $L4 = 2.72728123808534006489e-01;
        $L5 = 2.30660745775561754067e-01;
        $L6 = 2.06975017800338417784e-01;
        $P1 = 1.66666666666666019037e-01;
        $P2 = -2.77777777770155933842e-03;
        $P3 = 6.61375632143793436117e-05;
        $P4 = -1.65339022054652515390e-06;
        $P5 = 4.13813679705723846039e-08;
        $lg2 = 6.93147180559945286227e-01;
        $lg2_h = 6.93147182464599609375e-01;
        $lg2_l = -1.90465429995776804525e-09;
        $ovt = 8.0085662595372944372e-0017;
        $cp = 9.61796693925975554329e-01;
        $cp_h = 9.61796700954437255859e-01;
        $cp_l = -7.02846165095275826516e-09;
        $ivln2 = 1.44269504088896338700e+00;
        $ivln2_h = 1.44269502162933349609e+00;
        $ivln2_l = 1.92596299112661746887e-08;

        $one = 1.0;
        $two = 2.0;
        $zero = 0.0;

        [$hx, $lx] = Ieee754::extractWords($x);
        [$hy, $ly] = Ieee754::extractWords($y);
        $ix = $hx & 0x7FFFFFFF;
        $iy = $hy & 0x7FFFFFFF;

        if (($iy | $ly) === 0) {
            return $one;
        }

        if ($ix > 0x7FF00000 || (($ix === 0x7FF00000) && ($lx !== 0))
            || $iy > 0x7FF00000 || (($iy === 0x7FF00000) && ($ly !== 0))) {
            return $x + $y;
        }

        $yisint = 0;
        if ($hx < 0) {
            if ($iy >= 0x43400000) {
                $yisint = 2;
            } elseif ($iy >= 0x3FF00000) {
                $k = ($iy >> 20) - 0x3FF;
                if ($k > 20) {
                    $j = $ly >> (52 - $k);
                    if (($j << (52 - $k)) === $ly) {
                        $yisint = 2 - ($j & 1);
                    }
                } elseif ($ly === 0) {
                    $j = $iy >> (20 - $k);
                    if (($j << (20 - $k)) === $iy) {
                        $yisint = 2 - ($j & 1);
                    }
                }
            }
        }

        if ($ly === 0) {
            if ($iy === 0x7FF00000) {
                if ((($ix - 0x3FF00000) | $lx) === 0) {
                    return $y - $y;
                } elseif ($ix >= 0x3FF00000) {
                    return ($hy >= 0) ? $y : $zero;
                } else {
                    return ($hy < 0) ? -$y : $zero;
                }
            }
            if ($iy === 0x3FF00000) {
                if ($hy < 0) {
                    return $one / $x;
                }

                return $x;
            }
            if ($hy === 0x40000000) {
                return $x * $x;
            }
            if ($hy === 0x3FE00000) {
                if ($hx >= 0) {
                    return sqrt($x);
                }
            }
        }

        $ax = abs($x);

        if ($lx === 0) {
            if ($ix === 0x7FF00000 || $ix === 0 || $ix === 0x3FF00000) {
                $z = $ax;
                if ($hy < 0) {
                    $z = $one / $z;
                }
                if ($hx < 0) {
                    if ((($ix - 0x3FF00000) | $yisint) === 0) {
                        $z = NAN;
                    } elseif ($yisint === 1) {
                        $z = -$z;
                    }
                }

                return $z;
            }
        }

        $n = ($hx >> 31) + 1;

        if (($n | $yisint) === 0) {
            return NAN;
        }

        $s = $one;
        if (($n | ($yisint - 1)) === 0) {
            $s = -$one;
        }

        if ($iy > 0x41E00000) {
            if ($iy > 0x43F00000) {
                if ($ix <= 0x3FEFFFFF) {
                    return ($hy < 0) ? $huge * $huge : $tiny * $tiny;
                }

                // C tests $ix >= 0x3FF00000 here, which is always true by now.
                return ($hy > 0) ? $huge * $huge : $tiny * $tiny;
            }
            if ($ix < 0x3FEFFFFF) {
                return ($hy < 0) ? $s * $huge * $huge : $s * $tiny * $tiny;
            }
            if ($ix > 0x3FF00000) {
                return ($hy > 0) ? $s * $huge * $huge : $s * $tiny * $tiny;
            }
            $t = $ax - $one;
            $w = ($t * $t) * (0.5 - $t * (0.3333333333333333333333 - $t * 0.25));
            $u = $ivln2_h * $t;
            $v = $t * $ivln2_l - $w * $ivln2;
            $t1 = $u + $v;
            $t1 = Ieee754::setLowWord($t1, 0);
            $t2 = $v - ($t1 - $u);
        } else {
            $n = 0;
            if ($ix < 0x00100000) {
                $ax *= $two53;
                $n -= 53;
                $ix = Ieee754::getHighWord($ax);
            }
            $n += ($ix >> 20) - 0x3FF;
            $j = $ix & 0x000FFFFF;
            $ix = $j | 0x3FF00000;
            if ($j <= 0x3988E) {
                $k = 0;
            } elseif ($j < 0xBB67A) {
                $k = 1;
            } else {
                $k = 0;
                $n += 1;
                $ix -= 0x00100000;
            }
            $ax = Ieee754::setHighWord($ax, $ix);

            $u = $ax - $bp[$k];
            $v = $one / ($ax + $bp[$k]);
            $ss = $u * $v;
            $s_h = $ss;
            $s_h = Ieee754::setLowWord($s_h, 0);
            $t_h = $zero;
            $t_h = Ieee754::setHighWord($t_h, (($ix >> 1) | 0x20000000) + 0x00080000 + ($k << 18));
            $t_l = $ax - ($t_h - $bp[$k]);
            $s_l = $v * (($u - $s_h * $t_h) - $s_h * $t_l);
            $s2 = $ss * $ss;
            $r = $s2 * $s2 *
                ($L1 + $s2 * ($L2 + $s2 * ($L3 + $s2 * ($L4 + $s2 * ($L5 + $s2 * $L6)))));
            $r += $s_l * ($s_h + $ss);
            $s2 = $s_h * $s_h;
            $t_h = 3.0 + $s2 + $r;
            $t_h = Ieee754::setLowWord($t_h, 0);
            $t_l = $r - (($t_h - 3.0) - $s2);
            $u = $s_h * $t_h;
            $v = $s_l * $t_h + $t_l * $ss;
            $p_h = $u + $v;
            $p_h = Ieee754::setLowWord($p_h, 0);
            $p_l = $v - ($p_h - $u);
            $z_h = $cp_h * $p_h;
            $z_l = $cp_l * $p_h + $p_l * $cp + $dp_l[$k];
            $t = (float) $n;
            $t1 = ((($z_h + $z_l) + $dp_h[$k]) + $t);
            $t1 = Ieee754::setLowWord($t1, 0);
            $t2 = $z_l - ((($t1 - $t) - $dp_h[$k]) - $z_h);
        }

        $y1 = $y;
        $y1 = Ieee754::setLowWord($y1, 0);
        $p_l = ($y - $y1) * $t1 + $y * $t2;
        $p_h = $y1 * $t1;
        $z = $p_l + $p_h;
        [$j, $i] = Ieee754::extractWords($z);

        if ($j >= 0x40900000) {
            if ((($j - 0x40900000) | $i) !== 0) {
                return $s * $huge * $huge;
            }
            if ($p_l + $ovt > $z - $p_h) {
                return $s * $huge * $huge;
            }
        } elseif (($j & 0x7FFFFFFF) >= 0x4090CC00) {
            // $j is signed, so compare it with 0xC090CC00 as a signed 32-bit int.
            if ((($j - (0xC090CC00 - 0x100000000)) | $i) !== 0) {
                return $s * $tiny * $tiny;
            }
            if ($p_l <= $z - $p_h) {
                return $s * $tiny * $tiny;
            }
        }

        $i = $j & 0x7FFFFFFF;
        $k = ($i >> 20) - 0x3FF;
        $n = 0;
        if ($i > 0x3FE00000) {
            $n = $j + (0x00100000 >> ($k + 1));
            $k = (($n & 0x7FFFFFFF) >> 20) - 0x3FF;
            $t = $zero;
            $t = Ieee754::setHighWord($t, $n & ~(0x000FFFFF >> $k));
            $n = (($n & 0x000FFFFF) | 0x00100000) >> (20 - $k);
            if ($j < 0) {
                $n = -$n;
            }
            $p_h -= $t;
        }
        $t = $p_l + $p_h;
        $t = Ieee754::setLowWord($t, 0);
        $u = $t * $lg2_h;
        $v = ($p_l - ($t - $p_h)) * $lg2 + $t * $lg2_l;
        $z = $u + $v;
        $w = $v - ($z - $u);
        $t = $z * $z;
        $t1 = $z - $t * ($P1 + $t * ($P2 + $t * ($P3 + $t * ($P4 + $t * $P5))));
        // Classic fdlibm has (z*t1)/(t1-two) - (w+z*w). V8 wraps the division in
        // base::Divide and takes the whole difference as the divisor, so this does.
        $r = ($z * $t1) / (($t1 - $two) - ($w + $z * $w));
        $z = $one - ($r - $z);
        $j = Ieee754::getHighWord($z);
        // PHP ints are 64-bit; simulate the 32-bit addition V8 does
        $j = self::int32Add($j, $n << 20);
        if (($j >> 20) <= 0) {
            $z = self::scalbn($z, $n);
        } else {
            $z = Ieee754::setHighWord($z, $j);
        }

        return $s * $z;
    }

    /**
     * pow(10, x) — the specific call break_eternity makes.
     */
    public static function pow10(float $x): float
    {
        return self::pow(10.0, $x);
    }

    /**
     * Simulates 32-bit signed integer addition (wrapping on overflow).
     */
    private static function int32Add(int $a, int $b): int
    {
        $result = $a + $b;
        // Wrap to 32-bit signed
        $result &= 0xFFFFFFFF;
        if ($result >= 0x80000000) {
            $result -= 0x100000000;
        }

        return $result;
    }

    /**
     * scalbn(x, n): x times 2^n, rounded once.
     *
     * pow only calls this when the result is subnormal or zero, so n can fall
     * below -1074, where 2^n is itself zero. Scaling by 2^-1000 first is exact
     * for x near 1 and leaves a factor that is still representable.
     */
    private static function scalbn(float $x, int $n): float
    {
        if ($n < -1000) {
            return ($x * 2.0 ** -1000) * 2.0 ** ($n + 1000);
        }

        return $x * 2.0 ** $n;
    }
}
