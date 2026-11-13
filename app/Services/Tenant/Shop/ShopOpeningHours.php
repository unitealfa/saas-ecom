<?php

namespace App\Services\Tenant\Shop;

use Illuminate\Validation\ValidationException;

/** The seven-day cyclic schedule, with explicit crossings after midnight. */
final class ShopOpeningHours
{
    private const array DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** @param array<string, mixed>|null $hours
     *  @return array<string, list<array{opens_at: string, closes_at: string, closes_next_day: bool}>>|null
     */
    public static function validate(?array $hours): ?array
    {
        if ($hours === null || $hours === []) {
            return null;
        }
        if (array_diff(array_keys($hours), self::DAYS) !== []) {
            self::fail('Jour inconnu dans les horaires.');
        }

        $result = [];
        $intervals = [];
        foreach (self::DAYS as $day => $name) {
            if (! array_key_exists($name, $hours)) {
                continue;
            }
            $slots = $hours[$name];
            if (! is_array($slots) || ! array_is_list($slots) || count($slots) > 12) {
                self::fail("Plages horaires invalides pour {$name}.");
            }
            $result[$name] = [];
            foreach ($slots as $slot) {
                if (! is_array($slot) || array_keys($slot) === []
                    || array_diff(array_keys($slot), ['opens_at', 'closes_at', 'closes_next_day']) !== []
                    || count($slot) !== 3
                    || ! is_string($slot['opens_at'] ?? null)
                    || ! is_string($slot['closes_at'] ?? null)
                    || ! is_bool($slot['closes_next_day'] ?? null)) {
                    self::fail("Intervalle invalide pour {$name}.");
                }
                $start = self::minutes($slot['opens_at']);
                $end = self::minutes($slot['closes_at']) + ($slot['closes_next_day'] ? 1440 : 0);
                if ($end <= $start || $end - $start > 1440) {
                    self::fail('La fermeture doit suivre l’ouverture et durer au plus 24 heures.');
                }
                $intervals[] = [$day * 1440 + $start, $day * 1440 + $end];
                $result[$name][] = $slot;
            }
        }

        // Test adjacent days and wrap-around Sunday -> Monday.
        foreach ($intervals as $i => [$a, $b]) {
            foreach ($intervals as $j => [$c, $d]) {
                if ($i === $j) {
                    continue;
                }
                foreach ([-10080, 0, 10080] as $offset) {
                    if ($a < $d + $offset && $c + $offset < $b) {
                        self::fail('Deux plages horaires se chevauchent.');
                    }
                }
            }
        }

        return $result;
    }

    private static function minutes(string $clock): int
    {
        if (! preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $clock)) {
            self::fail('Les horaires doivent être au format HH:mm.');
        }

        return ((int) substr($clock, 0, 2)) * 60 + (int) substr($clock, 3, 2);
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['opening_hours' => $message]);
    }
}
