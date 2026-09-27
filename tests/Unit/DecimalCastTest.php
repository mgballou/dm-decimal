<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal\Tests\Unit;

use DreadMajesty\Decimal\Decimal;
use DreadMajesty\Decimal\DecimalCast;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

// Every case saves through Eloquent into an in-memory SQLite table, then reads
// the row back as a fresh model. No Laravel app: Capsule alone, the way a
// stranger's own test would set it up.

final class CastLedger extends Model
{
    public $timestamps = false;

    protected $table = 'ledgers';

    protected $guarded = [];

    protected $casts = ['amount' => DecimalCast::class];
}

beforeEach(function (): void {
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('ledgers', function (Blueprint $table): void {
        $table->increments('id');
        $table->json('amount')->nullable();
    });
});

function castSaveAndRead(Decimal|string|float|int|null $value): Decimal
{
    $saved = CastLedger::query()->create(['amount' => $value]);

    return CastLedger::query()->findOrFail($saved->getKey())->amount;
}

function castInsertRawAndRead(?string $column): Decimal
{
    $id = Capsule::table('ledgers')->insertGetId(['amount' => $column]);

    return CastLedger::query()->findOrFail($id)->amount;
}

function castStored(): mixed
{
    return Capsule::table('ledgers')->value('amount');
}

/** The same sign, layer and mag, bit for bit; any NaN matches any NaN. */
function expectSameDecimal(Decimal $got, Decimal $want): void
{
    $bits = fn (float $x): string => is_nan($x) ? 'NaN' : bin2hex(pack('E', $x));

    expect([$bits($got->sign), $got->layer, $bits($got->mag)])
        ->toBe([$bits($want->sign), $want->layer, $bits($want->mag)]);
}

dataset('decimals', [
    'zero' => [Decimal::zero()],
    'one' => [Decimal::one()],
    'negative' => [Decimal::fromNumber(-2.5)],
    'layer 0 with no short binary form' => [Decimal::fromNumber(0.1)],
    'smallest subnormal' => [Decimal::fromNumber(5e-324)],
    '9e15' => [Decimal::fromNumber(9e15)],
    '1e308' => [Decimal::fromNumber(1e308)],
    'minus 1e308' => [Decimal::fromNumber(-1e308)],
    'largest double' => [Decimal::fromNumber(PHP_FLOAT_MAX)],
    'layer 1, 1e309' => [Decimal::fromComponents(1, 1, 309)],
    'layer 1, minus 1e400' => [Decimal::fromComponents(-1, 1, 400)],
    'layer 1, 1e-400' => [Decimal::fromComponents(1, 1, -400)],
    'layer 2, 1e1e20' => [Decimal::fromComponents(1, 2, 20)],
    'negative zero mag' => [Decimal::fromComponentsNoNormalize(1.0, 0, -0.0)],
    'infinity' => [Decimal::inf()],
    'negative infinity' => [Decimal::negInf()],
    'NaN' => [Decimal::nan()],
]);

test('a Decimal survives a save and a fresh read', function (Decimal $value): void {
    expectSameDecimal(castSaveAndRead($value), $value);
})->with('decimals');

test('a value set from a string, an int or a float reads back as Decimal::from made it', function (string|int|float $value): void {
    expectSameDecimal(castSaveAndRead($value), Decimal::from($value));
})->with([
    'string' => ['1e1000'],
    'negative string' => ['-2.5e-7'],
    'int' => [42],
    'float' => [0.1],
    'float past 9e15' => [1.5e300],
]);

test('the column holds sign, layer and mag as JSON', function (): void {
    castSaveAndRead(Decimal::fromNumber(42));

    expect(castStored())->toBe('{"sign":1,"layer":0,"mag":42}');
});

test('the column spells NaN, the infinities and -0 as strings, since JSON has no number for them', function (Decimal $value, string $json): void {
    castSaveAndRead($value);

    expect(castStored())->toBe($json);
})->with([
    'infinity' => [Decimal::inf(), '{"sign":1,"layer":' . PHP_INT_MAX . ',"mag":"Infinity"}'],
    'negative infinity' => [Decimal::negInf(), '{"sign":-1,"layer":' . PHP_INT_MAX . ',"mag":"Infinity"}'],
    'NaN' => [Decimal::nan(), '{"sign":"NaN","layer":0,"mag":"NaN"}'],
    'negative zero mag' => [Decimal::fromComponentsNoNormalize(1.0, 0, -0.0), '{"sign":1,"layer":0,"mag":"-0"}'],
]);

test('a null column reads as zero', function (): void {
    expectSameDecimal(castInsertRawAndRead(null), Decimal::zero());
});

test('setting null stores zero', function (): void {
    expectSameDecimal(castSaveAndRead(null), Decimal::zero());
    expect(castStored())->toBe('{"sign":0,"layer":0,"mag":0}');
});

test('a legacy plain-string column reads through Decimal::fromString', function (string $column): void {
    expectSameDecimal(castInsertRawAndRead($column), Decimal::fromString($column));
})->with([
    'number' => ['42'],
    'layer 1' => ['1e1000'],
    'layer 2' => ['1e1e20'],
    'negative' => ['-2.5'],
    'Infinity' => ['Infinity'],
    'NaN' => ['NaN'],
]);

test('a JSON column missing a field refuses to read rather than turning into zero', function (string $column): void {
    castInsertRawAndRead($column);
})->with([
    'no mag' => ['{"sign":1,"layer":0}'],
    'no layer' => ['{"sign":1,"mag":42}'],
    'no sign' => ['{"layer":0,"mag":42}'],
    'empty object' => ['{}'],
])->throws(\UnexpectedValueException::class, 'amount');

test('a JSON column with a field that is not a number refuses to read', function (string $column): void {
    castInsertRawAndRead($column);
})->with([
    'mag as a word' => ['{"sign":1,"layer":0,"mag":"lots"}'],
    'sign as null' => ['{"sign":null,"layer":0,"mag":42}'],
    'layer as a fraction' => ['{"sign":1,"layer":0.5,"mag":42}'],
    'layer as a string' => ['{"sign":1,"layer":"1","mag":42}'],
])->throws(\UnexpectedValueException::class, 'amount');
