<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Converts stored audit metadata into concise, business-readable text.
 * It deliberately never changes the underlying action, route, IDs, or JSON
 * metadata used for filtering and technical investigation.
 */
class AuditDescriptionService
{
    public function describe(AuditLog $log): string
    {
        $actor = $log->user_name ?: auth()->user()?->name ?: (($log->actor_id || $log->user_id) ? 'Deleted User' : 'System');
        $action = trim((string) $log->action);
        $values = is_array($log->new_values) ? $log->new_values : [];
        $module = $this->moduleName($log->module);
        $target = $this->targetName($values, $log->description);
        $staff = $this->staffTarget($log, $values);

        $description = match (true) {
            $action === 'product_created' => 'created product '.$target.'.',
            $action === 'bulk_pricing_updated' => 'updated selling prices for '.$target.'.',
            str_starts_with($action, 'product_active') => 'activated product '.$target.'.',
            str_starts_with($action, 'product_inactive') => 'deactivated product '.$target.'.',
            $action === 'Stock Count Created' => 'created stock count '.($values['reference'] ?? 'draft').'.',
            $action === 'Stock Count Updated' => 'updated stock count '.($values['reference'] ?? 'draft').'.',
            $action === 'Stock Count Finalized' => 'finalized stock count '.($values['reference'] ?? 'draft').' with '.((int) ($values['adjusted_products'] ?? 0)).' adjustment'.((int) ($values['adjusted_products'] ?? 0) === 1 ? '' : 's').'.',
            $action === 'Stock Count Cancelled' => 'cancelled stock count '.($values['reference'] ?? 'draft').'.',
            $action === 'End of Day Report exported' => 'exported the End of Day Report for '.($values['date'] ?? 'the selected date').'.',
            $action === 'Customer payment recorded' => 'recorded customer payment'.$this->forAmount($values['amount'] ?? null).'.',
            $action === 'customer_balance_adjusted' => $this->balanceAdjustmentDescription($values, 'receivable'),
            $action === 'supplier_balance_adjusted' => $this->balanceAdjustmentDescription($values, 'payable'),
            $action === 'Inventory adjustment recorded' => 'adjusted inventory by '.self::quantity($values['quantity'] ?? 0).' unit'.(abs((float) ($values['quantity'] ?? 0)) === 1.0 ? '' : 's').'.',
            $action === 'notification_marked_read' => 'marked notification '.($values['title'] ?? 'as read').'.',
            $action === 'notifications_marked_all_read' => 'marked '.((int) ($values['count'] ?? 0)).' notifications as read.',
            $action === 'staff created' => 'created staff user '.$staff.'.',
            $action === 'staff viewed' => 'viewed '.$staff."'s staff profile.",
            $action === 'staff updated' => 'updated '.$staff."'s staff profile.",
            $action === 'staff status changed' => 'updated '.$staff."'s account status.",
            $action === 'staff archived' => 'archived '.$staff.'.',
            $action === 'staff restored' => 'restored '.$staff.'.',
            $action === 'staff deleted' => 'deleted '.$staff.'.',
            str_starts_with($action, 'Purchase order created: ') => 'created purchase '.Str::after($action, ': ').'.',
            str_starts_with($action, 'Goods receipt ') => $this->goodsReceiptDescription($action),
            $action === 'POS register opened' => 'opened the POS register'.$this->forAmount($values['opening_cash'] ?? null, ' with ').'.',
            $action === 'POS register closed' => $this->registerCloseDescription($values),
            str_starts_with($action, 'POS register Cash ') => 'recorded'.$this->forAmount($values['amount'] ?? null).' '.Str::after($action, 'POS register ').($this->reason($values) ? ' — '.$this->reason($values) : '').'.',
            $action === 'POS sale held' || $action === 'POS sale held again' => 'held POS sale '.($values['hold_number'] ?? 'draft').'.',
            $action === 'POS sale resumed' => 'resumed '.($values['hold_number'] ?? 'a held POS sale').'.',
            str_starts_with($action, 'POS price overridden for ') => $this->priceOverrideDescription($action, $values),
            str_starts_with($action, 'Completed POS sale ') => $this->completedSaleDescription($action, $values),
            str_starts_with($action, 'Expense recorded: ') => 'recorded expense '.Str::after($action, ': ').$this->forAmount($values['amount'] ?? null).'.',
            str_starts_with($action, 'Expense deleted: ') => 'deleted expense '.Str::after($action, ': ').'.',
            preg_match('/^(Category|Unit|Supplier)\s+(Created|Updated|Activated|Deactivated|Archived|Restored|Permanently Deleted)$/i', $action) === 1 => $this->namedRecordDescription($action, $target),
            $action === 'login' => 'signed in.',
            $action === 'logout' => 'signed out.',
            str_contains(strtolower($action), 'return') && str_contains(strtolower($action), 'stock') => $this->sentence($action),
            $action !== '' => $this->fallback($action, $module, $target),
            default => 'recorded an activity in '.$module.'.',
        };

        return $actor.' '.$description;
    }

    public function routeVisit(User $user, string $routeName, Request $request): string
    {
        $subject = $this->routeSubject($request);

        return match ($routeName) {
            'business.staff.show' => $user->name.' viewed '.($subject ? $subject."'s" : 'a').' staff profile.',
            'business.suppliers.show' => $user->name.' viewed supplier '.($subject ?: 'profile').'.',
            'business.customers.show' => $user->name.' viewed '.($subject ? $subject."'s" : 'a').' customer profile.',
            'business.products.show' => $user->name.' viewed product '.($subject ?: 'details').'.',
            'business.orders.show' => $user->name.' viewed sale '.($subject ?: 'details').'.',
            'business.reports.customer-aging' => $user->name.' viewed Customer Aging Report.',
            'business.reports.supplier-aging' => $user->name.' viewed Supplier Aging Report.',
            'business.reports.product-performance' => $user->name.' viewed Product Performance Report.',
            'business.reports.end-of-day' => $user->name.' viewed End of Day Report.',
            default => $user->name.' viewed the '.$this->moduleName((string) Str::of($routeName)->replace(['admin.', 'business.', 'staff.'], '')->before('.')).' workspace.',
        };
    }

    /** @return array<int, array{label:string, old:string, new:string}> */
    public static function valueChanges(mixed $oldValues, mixed $newValues): array
    {
        $old = self::sanitizeValues(is_array($oldValues) ? $oldValues : []);
        $new = self::sanitizeValues(is_array($newValues) ? $newValues : []);
        $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));

        return collect($keys)->map(fn ($key) => [
            'label' => Str::of((string) $key)->replace('_', ' ')->headline()->toString(),
            'old' => self::displayValue($old[$key] ?? null, (string) $key),
            'new' => self::displayValue($new[$key] ?? null, (string) $key),
        ])->values()->all();
    }

    public static function sanitizeValues(mixed $values): mixed
    {
        if (! is_array($values)) return $values;

        foreach ($values as $key => $value) {
            if (preg_match('/password|token|secret|api.?key|remember|authorization|proof|image|file/i', (string) $key)) {
                unset($values[$key]);
            } elseif (is_array($value)) {
                $values[$key] = self::sanitizeValues($value);
            }
        }

        return $values;
    }

    private static function displayValue(mixed $value, string $field = ''): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => collect($value)->map(fn ($item, $key) => Str::headline((string) $key).': '.self::displayValue($item, (string) $key))->implode('; '),
            is_numeric($value) && preg_match('/amount|price|cost|balance|total|cash|variance/i', $field) => 'Rs '.number_format((float) $value, 0, '.', ','),
            is_numeric($value) => (string) $value,
            default => (string) $value,
        };
    }

    private function targetName(array $values, ?string $fallback): string
    {
        foreach (['target_name', 'product_name', 'name', 'supplier_name', 'customer_name', 'reference', 'purchase_number', 'invoice_number'] as $key) {
            if (filled($values[$key] ?? null)) return (string) $values[$key];
        }

        return filled($fallback) && ! str_contains((string) $fallback, 'route.') ? trim((string) $fallback) : 'this record';
    }

    private function staffTarget(AuditLog $log, array $values): string
    {
        if (filled($values['name'] ?? null)) return (string) $values['name'];
        if (preg_match('/\sfor\s+(.+)$/i', (string) $log->description, $matches)) return trim($matches[1]);
        return 'this staff user';
    }

    private function moduleName(string $module): string
    {
        return match (strtolower(trim($module))) {
            'pos' => 'POS', 'goodsreceipt', 'goods receipt' => 'Goods Receipt',
            'purchasereturn', 'purchase return' => 'Purchase Return',
            'customerpayment', 'customer payment' => 'Customer Payment',
            default => Str::of($module ?: 'General')->replace(['_', '-'], ' ')->headline()->toString(),
        };
    }

    private function goodsReceiptDescription(string $action): string
    {
        return preg_match('/Goods receipt\s+([^\s]+)\s+recorded for\s+(.+)/i', $action, $matches)
            ? 'received goods for '.$matches[2].' under '.$matches[1].'.'
            : $this->sentence($action);
    }

    private function completedSaleDescription(string $action, array $values): string
    {
        return 'completed sale '.Str::after($action, 'Completed POS sale ').$this->forAmount($values['grand_total'] ?? null).'.';
    }

    private function registerCloseDescription(array $values): string
    {
        $variance = (float) ($values['variance'] ?? 0);
        if (abs($variance) < 0.0001) return 'closed the POS register with no cash variance.';
        return 'closed the POS register with '.$this->money(abs($variance)).' cash '.($variance < 0 ? 'shortage' : 'excess').'.';
    }

    private function priceOverrideDescription(string $action, array $values): string
    {
        $product = Str::after($action, 'POS price overridden for ');
        return 'changed '.$product.' sale price from '.$this->money($values['standard_price'] ?? 0).' to '.$this->money($values['override_price'] ?? 0).'.';
    }

    private function balanceAdjustmentDescription(array $values, string $kind): string
    {
        $target = (string) ($values[$kind === 'receivable' ? 'customer_name' : 'supplier_name'] ?? 'this account');
        $direction = (string) ($values['direction'] ?? 'adjusted');
        $reason = $this->reason($values);
        $possessive = str_ends_with(strtolower($target), 's') ? "'" : "'s";
        return $direction.' '.$target.$possessive.' '.$kind.$this->forAmount($values['amount'] ?? null, ' by ').($reason ? ' ('.$reason.')' : '').'.';
    }

    private function namedRecordDescription(string $action, string $target): string
    {
        preg_match('/^(Category|Unit|Supplier)\s+(.+)$/i', $action, $matches);
        $entity = strtolower($matches[1] ?? 'record');
        $verb = match (strtolower($matches[2] ?? 'updated')) {
            'created' => 'created', 'updated' => 'updated', 'activated' => 'activated',
            'deactivated' => 'deactivated', 'archived' => 'archived', 'restored' => 'restored',
            'permanently deleted' => 'deleted', default => 'updated',
        };

        return $verb.' '.$entity.' '.$target.'.';
    }

    private function fallback(string $action, string $module, string $target): string
    {
        if (preg_match('/^(created|updated|viewed|deleted|deactivated|activated|approved|rejected|printed|exported|recorded|received|returned|finalized|cancelled)\b/i', $action)) {
            return rtrim($this->sentence($action), '.').'.';
        }

        return 'updated a '.$module.' record'.($target !== 'this record' ? ': '.$target : '').'.';
    }

    private function sentence(string $value): string
    {
        return Str::of(trim($value))->replace(['_', '-'], ' ')->lower()->ucfirst()->append('.')->toString();
    }

    private function forAmount(mixed $amount, string $prefix = ' for '): string
    {
        return is_numeric($amount) ? $prefix.$this->money((float) $amount) : '';
    }

    private function money(mixed $amount): string { return 'Rs '.number_format((float) $amount, 0, '.', ','); }
    private static function quantity(mixed $value): string { return rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.'); }
    private function reason(array $values): string { return trim((string) ($values['reason'] ?? '')); }

    private function routeSubject(Request $request): ?string
    {
        foreach (['staff', 'supplier', 'customer', 'product', 'order'] as $parameter) {
            $subject = $request->route()?->parameter($parameter);
            if (! is_object($subject)) continue;
            foreach (['name', 'supplier_name', 'owner_name', 'shop_name', 'order_number'] as $attribute) {
                if (filled($subject->{$attribute} ?? null)) return (string) $subject->{$attribute};
            }
        }

        return null;
    }
}
