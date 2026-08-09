<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessDocument;
use App\Models\Delivery;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Removes one explicitly confirmed tenant only. Platform settings, plans, permission
 * definitions and platform users are deliberately not part of this service.
 */
class PermanentlyDeleteBusinessService
{
    public function delete(Business $business, Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate(['admin_password' => ['required', 'string']]);
        if (! Hash::check($data['admin_password'], (string) $request->user()->password)) {
            throw ValidationException::withMessages([
                'admin_password' => 'The Super Admin password is incorrect. The company was not deleted.',
            ]);
        }

        $businessId = $business->id;
        $files = DB::transaction(function () use ($businessId, $request): array {
            // Lock the row so a second click/request cannot delete the same
            // company concurrently or write a duplicate destruction audit.
            $business = Business::query()->lockForUpdate()->find($businessId);
            if (! $business) {
                throw ValidationException::withMessages(['company' => 'This company has already been deleted.']);
            }
            $files = $this->tenantFiles($business);
            $businessName = $business->business_name;
            // This audit record is platform-level. The FK is null-on-delete, while
            // the snapshot below preserves the minimum needed deletion evidence.
            AuditLog::create([
                'user_id' => $request->user()->id,
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role,
                'business_id' => $business->id,
                'module' => 'Companies',
                'action' => 'company permanently deleted',
                'record_type' => Business::class,
                'record_id' => $business->id,
                'description' => 'Company permanently deleted: '.$businessName,
                'new_values' => ['company_id' => $business->id, 'company_name' => $businessName],
                'ip_address' => app(AuditIpResolver::class)->capture($request),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);

            // Some tenant relationships intentionally use RESTRICT to protect
            // normal accounting workflows. For a Super Admin-authorised tenant
            // purge, remove their parents in dependency order before the final
            // business delete. Every query is constrained by business_id.
            foreach ([
                'journal_entries', // cascades journal_entry_lines before accounts
                'supplier_advance_applications',
                'goods_receipts', // cascades goods_receipt_items before products/items
                'purchase_returns', // cascades purchase_return_items
                'supplier_payments',
                'purchase_invoices',
                'purchases', // cascades purchase_items
                'sales_quotations', // cascades sales_quotation_items before products
            ] as $table) {
                $this->deleteTenantRows($table, $business->id);
            }

            // These are tenant users only. Platform users are excluded even if a
            // malicious record happens to carry this business ID.
            $tenantUserIds = $this->tenantUsers($business)
                ->pluck('id');

            // Laravel database notifications are polymorphic and have no FK, so
            // remove notifications belonging to these tenant accounts explicitly.
            if ($tenantUserIds->isNotEmpty() && Schema::hasTable('notifications')) {
                DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->whereIn('notifiable_id', $tenantUserIds)
                    ->delete();
            }

            if (Schema::hasTable('announcements')) {
                DB::table('announcements')->where('business_id', $business->id)->delete();
            }

            User::withTrashed()->whereIn('id', $tenantUserIds)->get()
                ->each(fn (User $user) => $user->forceDelete());

            // The business foreign keys cascade through tenant-owned operational
            // rows (sales, purchases, stock, accounting, subscriptions, settings,
            // permissions and related request records). Do not hand-delete global
            // lookup tables or platform configuration.
            $business->delete();

            return $files;
        });

        // Files cannot participate in a SQL rollback. Remove them only after a
        // successful commit, and only when no remaining record references them.
        foreach ($files as $path) {
            if (! $this->isReferenced($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /** @return array<int, string> */
    private function tenantFiles(Business $business): array
    {
        $paths = collect([$business->logo]);

        $paths = $paths
            ->merge(BusinessDocument::where('business_id', $business->id)->pluck('file_path'))
            ->merge(Product::withTrashed()->where('business_id', $business->id)->pluck('image'))
            ->merge(Payment::where('business_id', $business->id)->get(['proof_image', 'screenshot'])->flatMap(fn ($row) => [$row->proof_image, $row->screenshot]))
            ->merge(Delivery::where('business_id', $business->id)->get(['proof_image', 'signature_image', 'payment_proof_image', 'payment_proof'])->flatMap(fn ($row) => [$row->proof_image, $row->signature_image, $row->payment_proof_image, $row->payment_proof]));

        $tenantUsers = $this->tenantUsers($business)->pluck('profile_image');

        return $paths->merge($tenantUsers)
            ->filter()
            ->map(fn ($path) => $this->normalisePath((string) $path))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function tenantUsers(Business $business)
    {
        return User::withTrashed()
            ->whereIn('role', ['business_owner', 'custom_staff', 'retailer'])
            ->where(fn ($query) => $query->where('business_id', $business->id)
                ->orWhere(fn ($query) => $query->whereKey($business->owner_id)->where('role', 'business_owner')));
    }

    private function deleteTenantRows(string $table, int $businessId): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, 'business_id')) {
            DB::table($table)->where('business_id', $businessId)->delete();
        }
    }

    private function normalisePath(string $path): ?string
    {
        $path = preg_replace('#^(?:public/|storage/)#', '', ltrim($path, '/'));

        return $path !== '' && ! str_contains($path, '..') ? $path : null;
    }

    private function isReferenced(string $path): bool
    {
        $columns = [
            'businesses' => ['logo'], 'business_documents' => ['file_path'],
            'products' => ['image'], 'payments' => ['proof_image', 'screenshot'],
            'deliveries' => ['proof_image', 'signature_image', 'payment_proof_image', 'payment_proof'],
            'users' => ['profile_image'], 'goods_receipts' => ['attachment_path'],
        ];

        foreach ($columns as $table => $tableColumns) {
            if (! Schema::hasTable($table)) continue;
            foreach ($tableColumns as $column) {
                if (Schema::hasColumn($table, $column) && DB::table($table)->where($column, $path)->exists()) return true;
            }
        }

        return false;
    }
}
