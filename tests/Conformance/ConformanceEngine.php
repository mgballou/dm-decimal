<?php

declare(strict_types=1);

namespace DreadMajesty\Decimal\Tests\Conformance;

use DreadMajesty\Decimal\Decimal;
use DreadMajesty\Decimal\DecimalMath;

/**
 * A PHP transliteration of the TypeScript engine's step/catchUp loop,
 * using the PHP Decimal for all arithmetic. This is the conformance
 * target: if the PHP step loop produces the same state as the TypeScript
 * one, the Decimal arithmetic is correct.
 *
 * Uses associative arrays for state and content, not Eloquent models.
 * The proper models arrive in M3.
 *
 * @phpstan-type TierState array{owned: Decimal, purchased: Decimal, progressMs: int, lifetimeProduced: Decimal, running: bool}
 * @phpstan-type GameState array{saveVersion: int, resources: array<string, Decimal>, gens: array<string, TierState>, souls: Decimal, soulsSpent: Decimal, lifetimeEvil: Decimal, earnedAchievements: list<string>, unlocked: array<string, bool>, overseers: array<string, list<string>>, smiteActiveMs: int, smiteCooldownMs: int, smiteApathy: float, smiteBlow: float, smiteRungs: array<string, int>, smiteKept: array<string, int>, stats: array{playTimeMs: int, smites: int, prestiges: int, runMs: int}}
 * @phpstan-type TierDef array{id: string, name: string, plural: string, produces: string, yield: string, cycleMs: int, costResource: string, baseCost: string, costRate: float, overseers: list<array{id: string, name: string, cost: string, effect: array{kind: string, factor?: float|int}}>, art: string}
 * @phpstan-type Content array{version: string, tiers: list<TierDef>, milestones: list<array{at: int, multiplier: int}>, achievements: list<array{id: string, name: string, description: string, condition: array<string, mixed>, multiplier: int}>, unlockFraction: float, prestige: array{k: int, scale: string, exponent: float, perSoul: float}, offlineCapMs: int, smite: array{cooldownMs: int, apathy: array{perBlow: int, cap: int}, climbGrowth: int, upgrades: list<array{id: string, name: string, base: float|int, unit: string, rungs: list<array{evil: string, souls: string, value: float|int}>}>}}
 * @phpstan-type SaveBlob array{saveVersion: int, resources: array<string, string>, gens: array<string, array{owned: string, progressMs: int, lifetimeProduced: string, running?: bool, purchased?: string}>, souls: string, lifetimeEvil: string, earnedAchievements?: list<string>, unlocked?: array<string, bool>, overseers?: array<string, list<string>>, smiteActiveMs?: int, smiteCooldownMs?: int, smiteApathy?: float, smiteBlow?: float, smiteRungs?: array<string, int>, smiteKept?: array<string, int>, soulsSpent?: string, stats: array{playTimeMs: int, smites: int, prestiges: int, runMs: int}, savedAtMs: int}
 */
final class ConformanceEngine
{
    private const int BASE_DT_MS = 100;

    private const int COARSE_DT_MS = 1000;

    private const int COARSEN_ABOVE_MS = 3_600_000;

    /**
     * @param  SaveBlob  $blob
     * @param  Content  $content
     * @return GameState
     */
    public static function deserialize(array $blob, array $content): array
    {
        $contentTierIds = array_map(fn (array $t): string => $t['id'], $content['tiers']);
        $blobTierIds = array_keys($blob['gens']);
        $allTierIds = array_values(array_unique(array_merge($contentTierIds, $blobTierIds)));

        $resources = [];
        foreach ($blob['resources'] as $id => $val) {
            $resources[$id] = Decimal::fromString($val);
        }

        $gens = [];
        foreach ($allTierIds as $id) {
            $saved = $blob['gens'][$id] ?? null;
            $gens[$id] = [
                'owned' => Decimal::fromString($saved['owned'] ?? '0'),
                'purchased' => Decimal::fromString($saved['purchased'] ?? '0'),
                'progressMs' => $saved['progressMs'] ?? 0,
                'lifetimeProduced' => Decimal::fromString($saved['lifetimeProduced'] ?? '0'),
                'running' => $saved['running'] ?? false,
            ];
        }

        $unlocked = [];
        foreach ($allTierIds as $id) {
            $unlocked[$id] = $blob['unlocked'][$id] ?? false;
        }

        $overseers = [];
        foreach ($allTierIds as $id) {
            $overseers[$id] = $blob['overseers'][$id] ?? [];
        }

        $smiteUpgradeIds = array_map(
            fn (array $u): string => $u['id'],
            $content['smite']['upgrades'],
        );

        $smiteRungs = [];
        $smiteKept = [];
        foreach ($smiteUpgradeIds as $id) {
            $smiteRungs[$id] = $blob['smiteRungs'][$id] ?? 0;
            $smiteKept[$id] = $blob['smiteKept'][$id] ?? 0;
        }

        return [
            'saveVersion' => $blob['saveVersion'],
            'resources' => $resources,
            'gens' => $gens,
            'souls' => Decimal::fromString($blob['souls']),
            'soulsSpent' => Decimal::fromString($blob['soulsSpent'] ?? '0'),
            'lifetimeEvil' => Decimal::fromString($blob['lifetimeEvil']),
            'earnedAchievements' => $blob['earnedAchievements'] ?? [],
            'unlocked' => $unlocked,
            'overseers' => $overseers,
            'smiteActiveMs' => $blob['smiteActiveMs'] ?? 0,
            'smiteCooldownMs' => $blob['smiteCooldownMs'] ?? 0,
            'smiteApathy' => $blob['smiteApathy'] ?? 0.0,
            'smiteBlow' => $blob['smiteBlow'] ?? 1.0,
            'smiteRungs' => $smiteRungs,
            'smiteKept' => $smiteKept,
            'stats' => [
                'playTimeMs' => $blob['stats']['playTimeMs'],
                'smites' => $blob['stats']['smites'],
                'prestiges' => $blob['stats']['prestiges'],
                'runMs' => $blob['stats']['runMs'] ?? 0,
            ],
        ];
    }

    /**
     * @param  GameState  $state
     * @param  Content  $content
     * @return SaveBlob
     */
    public static function serialize(array $state, array $content): array
    {
        $tierIds = array_keys($state['gens']);

        $resources = [];
        foreach ($state['resources'] as $id => $val) {
            $resources[$id] = (string) $val;
        }

        $gens = [];
        foreach ($tierIds as $id) {
            $gen = $state['gens'][$id];
            $gens[$id] = [
                'owned' => (string) $gen['owned'],
                'progressMs' => $gen['progressMs'],
                'lifetimeProduced' => (string) $gen['lifetimeProduced'],
                'running' => $gen['running'],
                'purchased' => (string) $gen['purchased'],
            ];
        }

        $unlocked = [];
        foreach ($tierIds as $id) {
            $unlocked[$id] = $state['unlocked'][$id];
        }

        $overseers = [];
        foreach ($tierIds as $id) {
            $overseers[$id] = $state['overseers'][$id];
        }

        $smiteUpgradeIds = array_map(
            fn (array $u): string => $u['id'],
            $content['smite']['upgrades'],
        );

        $smiteRungs = [];
        $smiteKept = [];
        foreach ($smiteUpgradeIds as $id) {
            $smiteRungs[$id] = $state['smiteRungs'][$id];
            $smiteKept[$id] = $state['smiteKept'][$id];
        }

        return [
            'saveVersion' => $state['saveVersion'],
            'resources' => $resources,
            'gens' => $gens,
            'souls' => (string) $state['souls'],
            'lifetimeEvil' => (string) $state['lifetimeEvil'],
            'earnedAchievements' => $state['earnedAchievements'],
            'unlocked' => $unlocked,
            'overseers' => $overseers,
            'smiteActiveMs' => $state['smiteActiveMs'],
            'smiteCooldownMs' => $state['smiteCooldownMs'],
            'smiteApathy' => $state['smiteApathy'],
            'smiteBlow' => $state['smiteBlow'],
            'smiteRungs' => $smiteRungs,
            'smiteKept' => $smiteKept,
            'soulsSpent' => (string) $state['soulsSpent'],
            'stats' => $state['stats'],
            'savedAtMs' => 0,
        ];
    }

    /**
     * @param  GameState  $state
     * @param  Content  $content
     * @return array{produced: array<string, Decimal>}
     */
    public static function catchUp(array &$state, array $content, int $elapsedMs): array
    {
        $clamped = max(0, $elapsedMs);
        $capped = min($clamped, $content['offlineCapMs']);
        $dt = $capped > self::COARSEN_ABOVE_MS ? self::COARSE_DT_MS : self::BASE_DT_MS;

        /** @var array<string, Decimal> $produced */
        $produced = [];
        $whole = intdiv($capped, $dt);

        for ($i = 0; $i < $whole; $i++) {
            $report = self::step($state, $content, $dt);
            self::accumulate($produced, $report['produced']);
        }

        $remainder = $capped - $whole * $dt;
        if ($remainder > 0) {
            $report = self::step($state, $content, $remainder);
            self::accumulate($produced, $report['produced']);
        }

        $state['stats']['playTimeMs'] += $capped;
        $state['stats']['runMs'] += $capped;

        return ['produced' => $produced];
    }

    /**
     * @param  GameState  $state
     * @param  Content  $content
     * @return array{produced: array<string, Decimal>}
     */
    public static function step(array &$state, array $content, int $dtMs): array
    {
        // Spend countdowns
        $state['smiteActiveMs'] = max(0, $state['smiteActiveMs'] - $dtMs);
        $state['smiteCooldownMs'] = max(0, $state['smiteCooldownMs'] - $dtMs);

        if ($state['smiteActiveMs'] <= 0) {
            $state['smiteBlow'] = 1.0;
        }

        $bleedMs = self::smiteBleedMs($state, $content);
        $state['smiteApathy'] = max(0.0, $state['smiteApathy'] - $dtMs / $bleedMs);

        // Snapshot owned counts
        $owned = [];
        foreach ($content['tiers'] as $tier) {
            $owned[$tier['id']] = $state['gens'][$tier['id']]['owned'];
        }

        /** @var array<string, Decimal> $delta */
        $delta = [];

        foreach ($content['tiers'] as $tier) {
            $gen = &$state['gens'][$tier['id']];
            $appointed = self::hasAutomator($state, $tier);

            if (! $appointed && ! $gen['running']) {
                continue;
            }

            $cycleMs = self::effectiveCycleMs($state, $tier);

            $gen['progressMs'] += $dtMs;
            $cycles = intdiv($gen['progressMs'], $cycleMs);

            if ($appointed) {
                if ($cycles > 0) {
                    $gen['progressMs'] -= $cycles * $cycleMs;
                }
            } elseif ($cycles > 0) {
                $cycles = 1;
                $gen['progressMs'] = 0;
                $gen['running'] = false;
            }

            $count = $owned[$tier['id']] ?? null;
            if ($cycles <= 0 || $count === null || DecimalMath::lte($count, Decimal::zero())) {
                continue;
            }

            $amount = DecimalMath::mul(
                DecimalMath::mul(
                    DecimalMath::mul($count, self::effectiveYield($state, $tier)),
                    Decimal::fromNumber($cycles),
                ),
                self::tierMultiplier($state, $content, $count),
            );

            $delta[$tier['produces']] = DecimalMath::add(
                $delta[$tier['produces']] ?? Decimal::zero(),
                $amount,
            );

            $gen['lifetimeProduced'] = DecimalMath::add($gen['lifetimeProduced'], $amount);
        }
        unset($gen);

        self::commit($state, $delta, $content);

        return ['produced' => $delta];
    }

    /**
     * @param  GameState  $state
     * @param  array<string, Decimal>  $delta
     * @param  Content  $content
     */
    private static function commit(array &$state, array $delta, array $content): void
    {
        $tierIds = array_map(fn (array $t): string => $t['id'], $content['tiers']);
        $resourceIds = array_keys($state['resources']);

        foreach ($delta as $id => $amount) {
            if (in_array($id, $tierIds, true)) {
                $state['gens'][$id]['owned'] = DecimalMath::add(
                    $state['gens'][$id]['owned'],
                    $amount,
                );

                continue;
            }

            if (in_array($id, $resourceIds, true)) {
                $state['resources'][$id] = DecimalMath::add(
                    $state['resources'][$id],
                    $amount,
                );

                if ($id === 'evil') {
                    $state['lifetimeEvil'] = DecimalMath::add(
                        $state['lifetimeEvil'],
                        $amount,
                    );
                }
            }
        }
    }

    /**
     * @param  GameState  $state
     * @param  TierDef  $tier
     */
    private static function hasAutomator(array $state, array $tier): bool
    {
        foreach ($tier['overseers'] as $post) {
            if ($post['effect']['kind'] === 'automate'
                && in_array($post['id'], $state['overseers'][$tier['id']], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  GameState  $state
     * @param  TierDef  $tier
     */
    private static function effectiveCycleMs(array $state, array $tier): int
    {
        $factor = 1.0;
        foreach ($tier['overseers'] as $post) {
            if ($post['effect']['kind'] !== 'quicken') {
                continue;
            }
            if (in_array($post['id'], $state['overseers'][$tier['id']], true)) {
                $factor *= $post['effect']['factor'];
            }
        }

        return max(1, (int) round($tier['cycleMs'] / $factor));
    }

    /**
     * @param  GameState  $state
     * @param  TierDef  $tier
     */
    private static function effectiveYield(array $state, array $tier): Decimal
    {
        $amount = Decimal::fromString($tier['yield']);
        foreach ($tier['overseers'] as $post) {
            if ($post['effect']['kind'] !== 'swell') {
                continue;
            }
            if (in_array($post['id'], $state['overseers'][$tier['id']], true)) {
                $amount = DecimalMath::mul($amount, Decimal::fromNumber($post['effect']['factor']));
            }
        }

        return $amount;
    }

    /**
     * @param  GameState  $state
     * @param  Content  $content
     */
    private static function tierMultiplier(array $state, array $content, Decimal $owned): Decimal
    {
        $multiplier = Decimal::one();
        foreach ($content['milestones'] as $milestone) {
            if (DecimalMath::lt($owned, Decimal::fromNumber($milestone['at']))) {
                break;
            }
            $multiplier = DecimalMath::mul($multiplier, Decimal::fromNumber($milestone['multiplier']));
        }

        return DecimalMath::mul($multiplier, self::globalMultiplier($state, $content));
    }

    /**
     * @param  GameState  $state
     * @param  Content  $content
     */
    private static function globalMultiplier(array $state, array $content): Decimal
    {
        $fromSouls = DecimalMath::add(
            Decimal::one(),
            DecimalMath::mul($state['souls'], Decimal::fromNumber($content['prestige']['perSoul'])),
        );
        $fromSmite = $state['smiteActiveMs'] > 0 ? $state['smiteBlow'] : 1.0;

        return DecimalMath::mul(
            DecimalMath::mul($fromSouls, self::achievementMultiplier($state, $content)),
            Decimal::fromNumber($fromSmite),
        );
    }

    /**
     * @param  GameState  $state
     * @param  Content  $content
     */
    private static function achievementMultiplier(array $state, array $content): Decimal
    {
        $multiplier = Decimal::one();
        foreach ($content['achievements'] as $achievement) {
            if ($achievement['multiplier'] === 1) {
                continue;
            }
            if (in_array($achievement['id'], $state['earnedAchievements'], true)) {
                $multiplier = DecimalMath::mul(
                    $multiplier,
                    Decimal::fromNumber($achievement['multiplier']),
                );
            }
        }

        return $multiplier;
    }

    /**
     * @param  GameState  $state
     * @param  Content  $content
     */
    private static function smiteBleedMs(array $state, array $content): float
    {
        return self::smiteValueNow($state, $content, 'forgetting');
    }

    /**
     * @param  GameState  $state
     * @param  Content  $content
     */
    private static function smiteValueNow(array $state, array $content, string $id): float
    {
        $rung = $state['smiteRungs'][$id] ?? 0;

        foreach ($content['smite']['upgrades'] as $upgrade) {
            if ($upgrade['id'] !== $id) {
                continue;
            }

            if ($rung <= 0) {
                return (float) $upgrade['base'];
            }

            $index = min($rung, count($upgrade['rungs'])) - 1;

            return (float) ($upgrade['rungs'][$index]['value'] ?? $upgrade['base']);
        }

        return 0.0;
    }

    /**
     * @param  array<string, Decimal>  $into
     * @param  array<string, Decimal>  $from
     */
    private static function accumulate(array &$into, array $from): void
    {
        foreach ($from as $id => $amount) {
            $into[$id] = DecimalMath::add($into[$id] ?? Decimal::zero(), $amount);
        }
    }
}
