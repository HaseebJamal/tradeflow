<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRouteRecordContext
{
    /**
     * Keeps a row-action route authoritative. New-transaction selectors are
     * intentionally untouched; this only examines records already bound to a route.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $user = $request->user();

        if (!$route || !$user) {
            return $next($request);
        }

        foreach ($route->parameters() as $name => $record) {
            if (!$record instanceof Model) {
                continue;
            }

            // Admin routes are already restricted to Super Admin users. They
            // deliberately operate across businesses, so applying the signed
            // in user's business_id here makes valid review actions look like
            // tampered row actions. Submitted route identifiers remain strict.
            if ($user->role !== 'super_admin') {
                $this->ensureBusinessOwnership($record, $user->business_id);
            }
            $this->ensureSubmittedIdentifierMatches($request, (string) $name, $record);
        }

        return $next($request);
    }

    private function ensureBusinessOwnership(Model $record, mixed $businessId): void
    {
        if ($businessId === null || !array_key_exists('business_id', $record->getAttributes())) {
            return;
        }

        abort_unless((int) $record->getAttribute('business_id') === (int) $businessId, 403, 'The selected record does not match the requested action.');
    }

    private function ensureSubmittedIdentifierMatches(Request $request, string $parameter, Model $record): void
    {
        $inputKey = $parameter.'_id';
        $submitted = $request->input($inputKey);

        if ($submitted === null || $submitted === '') {
            return;
        }

        abort_unless((string) $submitted === (string) $record->getKey(), 403, 'The selected record does not match the requested action.');
    }
}
