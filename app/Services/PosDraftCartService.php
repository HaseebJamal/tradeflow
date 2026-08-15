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
    public function sync(Session $session, int $businessId, int $userId, int $registerId, array $cart, ?int $generation = null): bool
    {
        $key = $this->key($businessId, $userId, $registerId);
        $currentGeneration = (int) data_get($session->get($key, []), 'generation', -1);

        // The browser can have an older debounced request in flight when a
        // cashier clears the cart. Never let that request restore a draft
        // after a newer clear/resume state has reached the session.
        if ($generation !== null && $generation < $currentGeneration) {
            return false;
        }

        $session->put($key, [
            'item_count' => count($cart),
            'generation' => $generation ?? ($currentGeneration + 1),
            'updated_at' => now()->timestamp,
        ]);

        return true;
    }

    public function hasItems(Session $session, int $businessId, int $userId, int $registerId): bool
    {
        return $this->itemCount($session, $businessId, $userId, $registerId) > 0;
    }

    public function itemCount(Session $session, int $businessId, int $userId, int $registerId): int
    {
        return (int) data_get($session->get($this->key($businessId, $userId, $registerId), []), 'item_count', 0);
    }

    public function generation(Session $session, int $businessId, int $userId, int $registerId): int
    {
        return max(0, (int) data_get($session->get($this->key($businessId, $userId, $registerId), []), 'generation', 0));
    }

    public function clear(Session $session, int $businessId, int $userId, int $registerId, ?int $generation = null): bool
    {
        // Retain an empty tombstone rather than forgetting the key. This
        // preserves ordering and prevents an older in-flight non-empty sync
        // from recreating the draft after a hold, completion, or clear.
        return $this->sync($session, $businessId, $userId, $registerId, [], $generation);
    }

    private function key(int $businessId, int $userId, int $registerId): string
    {
        return implode('.', [self::SESSION_PREFIX, $businessId, $userId, $registerId]);
    }
}
