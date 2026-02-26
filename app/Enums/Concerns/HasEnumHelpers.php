<?php

namespace App\Enums\Concerns;

trait HasEnumHelpers
{
    /**
     * Get all enum values as an array.
     */
    public static function values(): array
    {
        return array_column(static::cases(), 'value');
    }

    /**
     * Get all enum names as an array.
     */
    public static function names(): array
    {
        return array_column(static::cases(), 'name');
    }

    /**
     * Get enum as key-value pairs (name => value).
     */
    public static function toArray(): array
    {
        return array_combine(
            array_column(static::cases(), 'name'),
            array_column(static::cases(), 'value')
        );
    }

    /**
     * Get enum as key-value pairs (value => name).
     */
    public static function toSelectArray(): array
    {
        return array_combine(
            array_column(static::cases(), 'value'),
            array_column(static::cases(), 'name')
        );
    }

    /**
     * Try to get an enum case from a value, returning null if not found.
     */
    public static function tryFromValue(mixed $value): ?static
    {
        return static::tryFrom($value);
    }

    /**
     * Check if a value exists in the enum.
     */
    public static function hasValue(mixed $value): bool
    {
        return in_array($value, static::values(), true);
    }

    /**
     * Get a random enum case.
     */
    public static function random(): static
    {
        $cases = static::cases();

        return $cases[array_rand($cases)];
    }
}
