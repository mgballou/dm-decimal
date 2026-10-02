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

## Installation

### Install from GitHub (available now)

Until the package is listed on Packagist, point Composer at this GitHub repository
and require its `main` branch:

```sh
composer config repositories.dm-decimal vcs https://github.com/mgballou/dm-decimal
composer require mgballou/dm-decimal:dev-main
```

### Install from Packagist (after listing)

After the package is listed on Packagist, install the latest release with:

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
| 4 | within 1e-11 relative | log space: three runs that climb past 9e15 (as far as 1e100), one edge case |

CI runs all ten on PHP 8.3 and 8.4, next to the unit tests and PHPStan at level 9.

## Where it does not match V8 yet

break_eternity.js calls V8's `Math.log10` and `Math.pow(10, x)`. PHP's `log10()`
and `10 ** $x` go to the platform's libm, which gives a different last bit on
3–9% of inputs. `DecimalLog` uses PHP's own functions for now, so the four
toleranced vectors pass on the tolerance, not bit for bit. The engine repo's
[log10/pow10 agreement](https://github.com/mgballou/dread-majesty/blob/main/docs/superpowers/plans/2026-09-18-deployment-and-agreement.md)
sets bit-identical output as the target. Porting V8's `ieee754.cc` into
`DecimalLog` closes the gap, and those four vectors can then be held to exact.

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
also MIT; both notices are in [LICENSE](LICENSE).
