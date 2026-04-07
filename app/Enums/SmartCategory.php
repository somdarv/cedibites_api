<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

/**
 * Code-defined menu categories whose membership is computed, not manual.
 *
 * Admins can enable/disable these and set display order, but the item
 * selection rule is defined entirely in the paired Resolver class.
 */
enum SmartCategory: string
{
    use HasEnumHelpers;

    case MostPopular = 'most-popular';
    case Trending = 'trending';
    case TopRated = 'top-rated';
    case NewArrivals = 'new-arrivals';
    case BreakfastFavorites = 'breakfast-favorites';
    case LunchPicks = 'lunch-picks';
    case DinnerFavorites = 'dinner-favorites';
    case LateNightBites = 'late-night-bites';
    case OrderAgain = 'order-again';

    /** Human-readable label for the customer-facing menu. */
    public function label(): string
    {
        return match ($this) {
            self::MostPopular => 'Most Popular',
            self::Trending => 'Trending Now',
            self::TopRated => 'Top Rated',
            self::NewArrivals => 'New on the Menu',
            self::BreakfastFavorites => 'Breakfast Favorites',
            self::LunchPicks => 'Lunch Picks',
            self::DinnerFavorites => 'Dinner Favorites',
            self::LateNightBites => 'Late Night Bites',
            self::OrderAgain => 'Order Again',
        };
    }

    /** Phosphor icon name for the frontend. */
    public function icon(): string
    {
        return match ($this) {
            self::MostPopular => 'fire',
            self::Trending => 'trend-up',
            self::TopRated => 'star',
            self::NewArrivals => 'sparkle',
            self::BreakfastFavorites => 'sun',
            self::LunchPicks => 'sun-horizon',
            self::DinnerFavorites => 'moon-stars',
            self::LateNightBites => 'moon',
            self::OrderAgain => 'arrow-counter-clockwise',
        };
    }

    /** Whether this category requires a logged-in customer to resolve. */
    public function requiresCustomer(): bool
    {
        return match ($this) {
            self::OrderAgain => true,
            default => false,
        };
    }

    /**
     * Time window (24h format) during which this category is visible.
     * Null means always visible (no time restriction).
     *
     * @return array{start: int, end: int}|null Hours in [0..23]
     */
    public function visibleHours(): ?array
    {
        return match ($this) {
            self::BreakfastFavorites => ['start' => 5, 'end' => 11],
            self::LunchPicks => ['start' => 11, 'end' => 15],
            self::DinnerFavorites => ['start' => 17, 'end' => 22],
            self::LateNightBites => ['start' => 21, 'end' => 3],
            default => null,
        };
    }

    /** Whether this category is time-restricted. */
    public function isTimeBased(): bool
    {
        return $this->visibleHours() !== null;
    }

    /** Check if this category should be visible at the given hour (0–23). */
    public function isVisibleAtHour(int $hour): bool
    {
        $window = $this->visibleHours();
        if ($window === null) {
            return true;
        }

        // Handle overnight windows (e.g. 21 → 3)
        if ($window['start'] > $window['end']) {
            return $hour >= $window['start'] || $hour < $window['end'];
        }

        return $hour >= $window['start'] && $hour < $window['end'];
    }

    /**
     * The hour range used to compute time-based popularity.
     * This is the "when do orders count" window, which is wider than the
     * visibility window to capture enough data.
     *
     * @return array{start: int, end: int}|null
     */
    public function orderHours(): ?array
    {
        return match ($this) {
            self::BreakfastFavorites => ['start' => 5, 'end' => 11],
            self::LunchPicks => ['start' => 11, 'end' => 16],
            self::DinnerFavorites => ['start' => 16, 'end' => 22],
            self::LateNightBites => ['start' => 21, 'end' => 4],
            default => null,
        };
    }

    /** Default number of items to return for this smart category. */
    public function defaultLimit(): int
    {
        return match ($this) {
            self::MostPopular => 12,
            self::Trending => 8,
            self::TopRated => 10,
            self::NewArrivals => 8,
            self::OrderAgain => 10,
            default => 10,
        };
    }
}
