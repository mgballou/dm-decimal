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

```php
use DreadMajesty\Decimal\DecimalCast;
use DreadMajesty\Decimal\DecimalMath;
use Illuminate\Database\Eloquent\Model;

// In the migration: $table->json('gold');
class Player extends Model
{
    protected $fillable = ['gold'];

    protected $casts = ['gold' => DecimalCast::class];
}

$player = Player::create(['gold' => '1e1000']);
$player->gold = DecimalMath::mul($player->gold, $player->gold);
$player->save();

echo $player->fresh()->gold;           // 1e2000
echo $player->getRawOriginal('gold');  // {"sign":1,"layer":1,"mag":2000}
```

JSON has no number for NaN, the infinities or -0, so the cast stores those as the
strings `"NaN"`, `"Infinity"`, `"-Infinity"` and `"-0"`. A null column reads as
zero, and setting null stores zero. A column that holds a plain string such as
`1e1000` reads through `Decimal::fromString`, so a string column can take the cast
in place. JSON missing `sign`, `layer` or `mag` throws an `UnexpectedValueException`
rather than reading as zero.

## Install

The package is not on Packagist yet, so point Composer at this repository in
your `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/mgballou/dm-decimal",
        "no-api": true
    }
]
```

`no-api` has Composer clone over plain HTTPS, so it needs no GitHub token. Then:

```sh
composer require mgballou/dm-decimal:dev-main
```

Once it reaches Packagist, `composer require mgballou/dm-decimal` alone will do.

PHP 8.3 or later; CI tests 8.3 and 8.4.

## How the vectors tie it to the engine

[Dread Majesty](https://github.com/mgballou/dread-majesty) is an idle game whose
engine is TypeScript on break_eternity.js. The engine's
[`conformance/emit.ts`](https://github.com/mgballou/dread-majesty/blob/main/packages/engine/conformance/emit.ts)
plays ten fixed scenarios through its own `catchUp` loop and writes each start
state, elapsed time and end state to
[`vectors/v1.json`](https://github.com/mgballou/dread-majesty/blob/main/packages/engine/conformance/vectors/v1.json).
This repo carries that file unchanged as `fixtures/v1.json`. The file records
engine commit `85cc6c2`, the commit it was emitted on top of; it landed in
[`04141ac`](https://github.com/mgballou/dread-majesty/commit/04141ac).

`tests/Conformance/ConformanceEngine.php` ports the same catch-up loop to PHP
with every sum done by this package. Each test loads a start state, runs the loop
for the recorded time, and compares every `Decimal` in the end state with what
TypeScript produced.

| Vectors | Match | What they reach |
|---|---|---|
| 6 | exact, string for string | layer 0: first cycle, five minutes, thirty minutes, two hours, after a prestige, one edge case |
| 4 | exact, string for string (the engine asks only 1e-11 relative) | log space: three runs that climb past 9e15 (as far as 1e100), one edge case |

All ten print what JavaScript prints, because `Decimal` prints a number as
break_eternity.js 2.1.3 does. At each place break_eternity.js calls
`Number#toString()`, this package prints the fewest digits that read back as
the same double, and switches to exponent form below 1e-6 and from 1e21, as
JavaScript does.

CI runs all ten on PHP 8.3 and 8.4, next to the unit tests and PHPStan at level 9.

## How the differential test ties it to break_eternity.js

The vectors reach only the operations the engine's ten scenarios happen to use.
`tests/Differential` reaches the rest: `fixtures/differential.json` holds 6,239
seeded cases across every operation listed above and `toString`, with inputs at
layer 0, layer 1 and layer 2 and the edge cases (zero, negatives, 1e308 and past
it, infinity). Each case records break_eternity.js 2.1.3's result and the string
it prints, and the test holds the PHP result to it in sign, layer and mag, bit for
bit, and in the string, character for character. All 6,239 match. A case that ever cannot
match goes in the test's `INEXACT` list by name, with the reason.

That includes break_eternity's quirks at infinity, which this package keeps so a
server agrees with a client: `-1 * Infinity` is `Infinity`, `0 * Infinity` is
`Infinity`, and `Infinity * 0` is NaN.

break_eternity.js keeps a NaN or infinite layer; this package stores the layer as
an `int`, so the test reads a NaN layer as `Decimal::nan()`'s 0 and an infinite
one as `Decimal::inf()`'s `PHP_INT_MAX`.

The fixture is committed, so CI needs no Node. To regenerate it, run the generator
under Node 22 on x64 (on Apple silicon, the x64 build runs under Rosetta):

```sh
cd tools/differential
npm ci
npm run generate
```

It refuses any other Node or architecture, for the reasons in the next section.

## How log10 and pow10 match V8

break_eternity.js calls V8's `Math.log10` and `Math.pow(10, x)`. PHP's `log10()`
and `10 ** $x` go to the platform's libm, which lands on a different double for
about one `pow10` input in ten. So `DecimalLog` runs V8's own fdlibm code
instead, transliterated in `V8Math` from V8 12.4, the V8 in Node 22. The engine's
`.nvmrc` names Node 22, and the vectors were emitted under it. (The engine's CI
runs Node 24, but it checks the log-space vectors only to 1e-11.) That meets the engine repo's
[log10/pow10 agreement](https://github.com/mgballou/dread-majesty/blob/main/docs/superpowers/plans/2026-09-18-deployment-and-agreement.md),
which sets bit-identical output as the target.

Plain PHP arithmetic gives the same bits on every platform. On 67,000 inputs,
`V8Math` matched Node 22's x64 build on every one. Node 22's arm64 build lets
the compiler fuse some multiply-adds and lands one ULP away on about one `pow10`
input in two thousand; no vector here hits one of those.

Node 24 carries V8 13, which sends `Math.pow` to the platform's libm. If the
engine emits its vectors under Node 24, they change and `pow10` here must change
with them.

## Run it

```sh
composer install
composer test               # unit tests and the vectors
composer test:conformance   # the vectors alone
composer test:differential  # the break_eternity.js cases alone
composer types:check        # PHPStan, level 9
```

To take new vectors, run `pnpm conformance:emit` in `dread-majesty` and copy
`packages/engine/conformance/vectors/v1.json` over `fixtures/v1.json`.

## License

MIT. `Decimal` and `DecimalMath` port break_eternity.js, © 2019 Timothy Stiles,
also MIT. `V8Math` ports V8's fdlibm code, under Sun's fdlibm notice and V8's
BSD license. All the notices are in [LICENSE](LICENSE).
