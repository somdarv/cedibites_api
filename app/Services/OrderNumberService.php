<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderNumberService
{
    /**
     * Generate a unique order number, retrying inside a transaction if two
     * concurrent requests race to the same value. The orders table must have
     * a unique index on order_number to make this safe.
     */
    public function generate(): string
    {
        return DB::transaction(function () {
            $last = $this->lastAlphabeticCode();
            $next = $last ? $this->increment($last) : 'A001';

            while (Order::lockForUpdate()->where('order_number', $next)->exists()) {
                $next = $this->increment($next);
            }

            return $next;
        });
    }

    private function lastAlphabeticCode(): ?string
    {
        $candidates = Order::query()
            ->pluck('order_number')
            ->filter(fn (string $n) => (bool) preg_match('/^[A-Z]+\d{3}$/', $n));

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sort(fn (string $a, string $b) => $this->compareCodes($a, $b))
            ->last();
    }

    private function compareCodes(string $a, string $b): int
    {
        $pa = $this->parse($a);
        $pb = $this->parse($b);

        $len = max(strlen($pa['letters']), strlen($pb['letters']));
        $la = str_pad($pa['letters'], $len, ' ', STR_PAD_RIGHT);
        $lb = str_pad($pb['letters'], $len, ' ', STR_PAD_RIGHT);
        $cmp = strcmp($la, $lb);
        if ($cmp !== 0) {
            return $cmp;
        }

        return $pa['num'] <=> $pb['num'];
    }

    /**
     * @return array{letters: string, num: int}
     */
    private function parse(string $code): array
    {
        preg_match('/^([A-Z]+)(\d{3})$/', $code, $m);

        return [
            'letters' => $m[1],
            'num' => (int) $m[2],
        ];
    }

    private function increment(string $code): string
    {
        $p = $this->parse($code);
        if ($p['num'] < 100) {
            return $p['letters'].str_pad((string) ($p['num'] + 1), 3, '0', STR_PAD_LEFT);
        }

        return $this->advancePrefix($p['letters']).'001';
    }

    private function advancePrefix(string $letters): string
    {
        if ($letters === 'A') {
            return 'AB';
        }

        if (strlen($letters) === 2 && $letters[0] === 'A' && $letters[1] >= 'B' && $letters[1] < 'Z') {
            return 'A'.chr(ord($letters[1]) + 1);
        }

        if ($letters === 'AZ') {
            return 'BA';
        }

        if (strlen($letters) === 2 && $letters[0] === 'B' && $letters[1] >= 'A' && $letters[1] < 'Z') {
            return 'B'.chr(ord($letters[1]) + 1);
        }

        if ($letters === 'BZ') {
            return 'CA';
        }

        if (strlen($letters) === 2 && $letters[0] >= 'B' && $letters[0] < 'Z' && $letters[1] === 'Z') {
            return chr(ord($letters[0]) + 1).'A';
        }

        if (strlen($letters) === 2 && $letters[1] < 'Z') {
            return $letters[0].chr(ord($letters[1]) + 1);
        }

        throw new \RuntimeException("Order number prefix exhausted: {$letters}");
    }
}
