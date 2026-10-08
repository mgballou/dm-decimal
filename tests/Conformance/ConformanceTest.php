<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal\Tests\Conformance;

use DreadMajesty\Decimal\Decimal;
use DreadMajesty\Decimal\DecimalMath;

$vectorFile = json_decode(
    file_get_contents(__DIR__ . '/../../fixtures/v1.json'),
    true,
);

$chain = $vectorFile['chain'];

foreach ($vectorFile['vectors'] as $vector) {
    $label = $vector['label'];
    $isToleranced = $vector['kind'] === 'toleranced';
    $tolerance = $vector['tolerance'] !== null ? (float) $vector['tolerance'] : null;

    test("conformance: {$label}", function () use ($vector, $chain, $tolerance): void {
        $state = ConformanceEngine::deserialize($vector['startState'], $chain);
        $report = ConformanceEngine::catchUp($state, $chain, $vector['elapsedMs']);
        $actualBlob = ConformanceEngine::serialize($state, $chain);

        $expectedBlob = $vector['expected']['endState'];
        $errors = [];

        // Compare resources
        foreach ($expectedBlob['resources'] as $key => $expected) {
            $actual = $actualBlob['resources'][$key] ?? '0';
            $err = compareDecimal("resources.{$key}", $actual, $expected, $tolerance);
            if ($err !== null) {
                $errors[] = $err;
            }
        }

        // Compare gens
        foreach ($expectedBlob['gens'] as $key => $expectedGen) {
            $actualGen = $actualBlob['gens'][$key] ?? null;
            if ($actualGen === null) {
                $errors[] = "gens.{$key}: missing";

                continue;
            }

            $err = compareDecimal("gens.{$key}.owned", $actualGen['owned'], $expectedGen['owned'], $tolerance);
            if ($err !== null) {
                $errors[] = $err;
            }

            $err = compareDecimal("gens.{$key}.lifetimeProduced", $actualGen['lifetimeProduced'], $expectedGen['lifetimeProduced'], $tolerance);
            if ($err !== null) {
                $errors[] = $err;
            }

            if (isset($expectedGen['purchased'])) {
                $err = compareDecimal("gens.{$key}.purchased", $actualGen['purchased'] ?? '0', $expectedGen['purchased'], $tolerance);
                if ($err !== null) {
                    $errors[] = $err;
                }
            }

            if ($actualGen['progressMs'] !== $expectedGen['progressMs']) {
                $errors[] = "gens.{$key}.progressMs: expected {$expectedGen['progressMs']}, got {$actualGen['progressMs']}";
            }

            $expectedRunning = $expectedGen['running'] ?? false;
            $actualRunning = $actualGen['running'] ?? false;
            if ($actualRunning !== $expectedRunning) {
                $errors[] = "gens.{$key}.running: expected " . var_export($expectedRunning, true) . ', got ' . var_export($actualRunning, true);
            }
        }

        // Compare scalars
        $err = compareDecimal('souls', $actualBlob['souls'], $expectedBlob['souls'], $tolerance);
        if ($err !== null) {
            $errors[] = $err;
        }

        $err = compareDecimal('lifetimeEvil', $actualBlob['lifetimeEvil'], $expectedBlob['lifetimeEvil'], $tolerance);
        if ($err !== null) {
            $errors[] = $err;
        }

        if (isset($expectedBlob['soulsSpent'])) {
            $err = compareDecimal('soulsSpent', $actualBlob['soulsSpent'] ?? '0', $expectedBlob['soulsSpent'], $tolerance);
            if ($err !== null) {
                $errors[] = $err;
            }
        }

        // Compare stats
        if ($actualBlob['stats']['playTimeMs'] !== $expectedBlob['stats']['playTimeMs']) {
            $errors[] = "stats.playTimeMs: expected {$expectedBlob['stats']['playTimeMs']}, got {$actualBlob['stats']['playTimeMs']}";
        }
        if ($actualBlob['stats']['runMs'] !== $expectedBlob['stats']['runMs']) {
            $errors[] = "stats.runMs: expected {$expectedBlob['stats']['runMs']}, got {$actualBlob['stats']['runMs']}";
        }
        if ($actualBlob['stats']['smites'] !== $expectedBlob['stats']['smites']) {
            $errors[] = "stats.smites: expected {$expectedBlob['stats']['smites']}, got {$actualBlob['stats']['smites']}";
        }
        if ($actualBlob['stats']['prestiges'] !== $expectedBlob['stats']['prestiges']) {
            $errors[] = "stats.prestiges: expected {$expectedBlob['stats']['prestiges']}, got {$actualBlob['stats']['prestiges']}";
        }

        // Compare smite state
        if (($actualBlob['smiteActiveMs'] ?? 0) !== ($expectedBlob['smiteActiveMs'] ?? 0)) {
            $errors[] = 'smiteActiveMs: expected ' . ($expectedBlob['smiteActiveMs'] ?? 0) . ', got ' . ($actualBlob['smiteActiveMs'] ?? 0);
        }
        if (($actualBlob['smiteCooldownMs'] ?? 0) !== ($expectedBlob['smiteCooldownMs'] ?? 0)) {
            $errors[] = 'smiteCooldownMs: expected ' . ($expectedBlob['smiteCooldownMs'] ?? 0) . ', got ' . ($actualBlob['smiteCooldownMs'] ?? 0);
        }

        // Compare produced
        $expectedProduced = $vector['expected']['produced'];
        $actualProduced = [];
        foreach ($report['produced'] as $id => $amount) {
            $actualProduced[$id] = (string) $amount;
        }

        $allKeys = array_unique(array_merge(array_keys($expectedProduced), array_keys($actualProduced)));
        foreach ($allKeys as $key) {
            $err = compareDecimal(
                "produced.{$key}",
                $actualProduced[$key] ?? '0',
                $expectedProduced[$key] ?? '0',
                $tolerance,
            );
            if ($err !== null) {
                $errors[] = $err;
            }
        }

        expect($errors)->toBeEmpty(implode("\n", $errors));
    });
}

function compareDecimal(string $path, string $actual, string $expected, ?float $tolerance): ?string
{
    if ($actual === $expected) {
        return null;
    }

    if ($tolerance === null) {
        return "{$path}: expected \"{$expected}\", got \"{$actual}\"";
    }

    // Every vector must print what break_eternity.js prints. For a toleranced
    // vector, say how far apart the values are too.
    $da = Decimal::fromString($actual);
    $de = Decimal::fromString($expected);

    if (DecimalMath::eq($de, Decimal::zero())) {
        return "{$path}: expected 0, got \"{$actual}\"";
    }

    $relError = DecimalMath::div(
        DecimalMath::sub($da, $de)->abs(),
        $de->abs(),
    )->toNumber();

    $detail = $relError > $tolerance
        ? 'relative error ' . sprintf('%.3e', $relError) . " exceeds {$tolerance}"
        : (DecimalMath::eq($da, $de) ? 'same value' : 'relative error ' . sprintf('%.3e', $relError));

    return "{$path}: expected \"{$expected}\", got \"{$actual}\" ({$detail})";
}
