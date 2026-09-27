<div align="center">

<img src="docs/assets/mark.svg" width="56" alt="" />

<h1>dm-decimal</h1>

<p><strong>A PHP port of break_eternity's Decimal, held to the numbers<br />
the Dread Majesty engine writes in TypeScript.</strong></p>

<p>Idle games count past 1e308, so a double alone will not do. break_eternity stores<br />
a number as sign, layer and magnitude. This package does the same in PHP, and CI proves<br />
it lands on the TypeScript engine's results.</p>

<a href="https://github.com/mgballou/dm-decimal/actions/workflows/ci.yml"><img src="https://github.com/mgballou/dm-decimal/actions/workflows/ci.yml/badge.svg" alt="CI" /></a>

</div>

<br />

## What it is

`Decimal` is an immutable value with three fields. At layer 0, `mag` is the number
itself. At layer 1, `mag` is its base-10 log, and so on up. `DecimalMath` carries
the arithmetic: compare, add, sub, mul, div, pow, log10, pow10, floor and ceil. The
code follows [break_eternity.js](https://github.com/Patashu/break_eternity.js)
line for line, down to its habit of dropping the smaller operand in `add` when the
two are more than 17 digits apart.

```php
use DreadMajesty\Decimal\Decimal;
use DreadMajesty\Decimal\DecimalMath;

$a = Decimal::fromString('1e300');
$b = DecimalMath::mul($a, $a);

echo $b;          // 1e600
echo $b->layer;   // 1
echo $b->mag;     // 600
```

`DecimalCast` stores a `Decimal` on an Eloquent model as JSON holding
`sign`, `layer` and `mag`. It needs `illuminate/database`; the rest of the package
needs nothing but PHP.

```sh
composer require mgballou/dm-decimal
```

PHP 8.3 or 8.4.

## How the vectors tie it to the engine

[Dread Majesty](https://github.com/mgballou/dread-majesty) is an idle game whose
engine is TypeScript on break_eternity.js. The engine's
[`conformance/emit.ts`](https://github.com/mgballou/dread-majesty/blob/main/packages/engine/conformance/emit.ts)
plays ten fixed scenarios through its own `catchUp` loop and writes each start
state, elapsed time and end state to
[`vectors/v1.json`](https://github.com/mgballou/dread-majesty/blob/main/packages/engine/conformance/vectors/v1.json).
This repo carries that file unchanged as `fixtures/v1.json`, emitted at engine
commit [`85cc6c2`](https://github.com/mgballou/dread-majesty/commit/85cc6c2).

`tests/Conformance/ConformanceEngine.php` ports the same catch-up loop to PHP
with every sum done by this package. Each test loads a start state, runs the loop
for the recorded time, and compares every `Decimal` in the end state with what
TypeScript produced.

| Vectors | Match | What they reach |
|---|---|---|
| 6 | exact, string for string | layer 0: first cycle, five minutes, two hours, after a prestige, one edge case |
| 4 | exact in value, and within 1e-11 relative | log space: three runs that climb past 9e15 (as far as 1e100), one edge case |

The four log-space vectors match value for value, but not always string for
string: PHP prints `9000000000000007` where JavaScript prints
`9.000000000000007e15`, and it prints 17 digits where JavaScript stops at the
fewest that round-trip.

CI runs all ten on PHP 8.3 and 8.4, next to the unit tests and PHPStan at level 9.

## How log10 and pow10 match V8

break_eternity.js calls V8's `Math.log10` and `Math.pow(10, x)`. PHP's `log10()`
and `10 ** $x` go to the platform's libm, which lands on a different double for
about one `pow10` input in ten. So `DecimalLog` runs V8's own fdlibm code
instead, transliterated in `V8Math` from V8 12.4, the V8 in Node 22, which the
engine pins. That meets the engine repo's
[log10/pow10 agreement](https://github.com/mgballou/dread-majesty/blob/main/docs/superpowers/plans/2026-09-18-deployment-and-agreement.md),
which sets bit-identical output as the target.

Plain PHP arithmetic gives the same bits on every platform. On 67,000 inputs,
`V8Math` matched Node 22's x64 build on every one. Node 22's arm64 build lets
the compiler fuse some multiply-adds and lands one ULP away on about one `pow10`
input in two thousand; no vector here hits one of those.

Node 24 carries V8 13, which sends `Math.pow` to the platform's libm. If the
engine moves to Node 24, the vectors change and `pow10` here must change with
them.

## Run it

```sh
composer install
composer test               # unit tests and the vectors
composer test:conformance   # the vectors alone
composer types:check        # PHPStan, level 9
```

To take new vectors, run `pnpm conformance:emit` in `dread-majesty` and copy
`packages/engine/conformance/vectors/v1.json` over `fixtures/v1.json`.

## License

MIT. `Decimal` and `DecimalMath` port break_eternity.js, © 2019 Timothy Stiles,
also MIT. `V8Math` ports V8's fdlibm code, under Sun's fdlibm notice and V8's
BSD license. All the notices are in [LICENSE](LICENSE).
