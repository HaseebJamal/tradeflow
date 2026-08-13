<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;

/**
 * Stores the active POS cart state alongside the authenticated session.
 *
 * The browser cart remains the editing surface, but resume protection must
 * not trust a value supplied to the resume request itself.
 */
class PosDraftCartService
{
    private const SESSION_PREFIX = 'tradeflow.pos.draft';

    /** @param array<int, array<string, mixed>> $cart */
    public function sync(Session $session, int $businessId, int $userId, int $registerId, array $cart): void
    {
        $session->put($this->key($businessId, $userId, $registerId), [
            'item_count' => count($cart),
            'updated_at' => now()->timestamp,
        ]);
    }

    public function hasItems(Session $session, int $businessId, int $userId, int $registerId): bool
    {
        return (int) data_get($session->get($this->key($businessId, $userId, $registerId), []), 'item_count', 0) > 0;
    }

    public function clear(Session $session, int $businessId, int $userId, int $registerId): void
    {
        $session->forget($this->key($businessId, $userId, $registerId));
    }

    private function key(int $businessId, int $userId, int $registerId): string
    {
        return implode('.', [self::SESSION_PREFIX, $businessId, $userId, $registerId]);
    }
}
