<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCompanyPermissionsRequest;
use App\Models\Business;
use App\Models\CompanyPermission;
use App\Models\PermissionDefinition;
use App\Models\PermissionTemplate;
use App\Notifications\CompanyPermissionsUpdatedNotification;
use App\Services\CompanyPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompanyPermissionController extends Controller
{
    public function index(Request $request) { return $this->screen($request, 'all'); }
    public function modules(Request $request) { return redirect()->route('admin.permissions.index', $request->only(['company_id', 'manage_company_id'])); }
    public function features(Request $request) { return redirect()->route('admin.permissions.index', $request->only(['company_id', 'manage_company_id'])); }
    public function actions(Request $request) { return redirect()->route('admin.permissions.index', $request->only(['company_id', 'manage_company_id'])); }

    public function update(UpdateCompanyPermissionsRequest $request)
    {
        $data = $request->validated();
        $lockedCompanyId = $request->session()->get('admin.permissions.locked_company_id');
        if ($lockedCompanyId !== null && (int) $data['company_id'] !== (int) $lockedCompanyId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'company_id' => 'The selected company cannot be changed for this action.',
            ]);
        }
        $scopeDefinitions = $this->definitionsForScope($data['scope']);
        $permissionService = app(CompanyPermissionService::class);
        $selected = collect($data['permissions'] ?? [])
            ->map(fn ($permission) => $permissionService->normalise((string) $permission))
            ->unique()
            ->values()
            ->all();

        $company = Business::findOrFail($data['company_id']);
        DB::transaction(function () use ($request, $company, $data, $selected, $scopeDefinitions) {
            $definitions = in_array($data['scope'], ['modules', 'all'], true)
                ? app(CompanyPermissionService::class)->activeDefinitions()
                : $scopeDefinitions;
            $current = CompanyPermission::where('company_id', $company->id)->whereIn('permission_key', $definitions->pluck('permission_key'))->pluck('allowed', 'permission_key')->map(fn ($value) => (bool) $value)->all();
            $moduleDefinitions = $definitions->filter(fn (PermissionDefinition $definition) => $definition->permission_key === strtolower($definition->module).'.view');
            $enabledModules = $moduleDefinitions
                ->filter(fn (PermissionDefinition $definition) => in_array(strtolower($definition->permission_key), $selected, true))
                ->pluck('module')
                ->map(fn ($module) => strtolower($module))
                ->all();

            foreach ($definitions as $definition) {
                $key = strtolower($definition->permission_key);
                $moduleEnabled = in_array(strtolower($definition->module), $enabledModules, true);
                $newValue = in_array($data['scope'], ['modules', 'all'], true)
                    ? ($moduleDefinitions->contains('permission_key', $definition->permission_key)
                        ? $moduleEnabled
                        : ($moduleEnabled && in_array($key, $selected, true)))
                    : in_array($key, $selected, true);
                $oldValue = $current[$key] ?? null;
                CompanyPermission::updateOrCreate(
                    ['company_id' => $data['company_id'], 'permission_key' => $definition->permission_key],
                    ['allowed' => $newValue, 'assigned_by' => auth()->id()]
                );
                if ($oldValue !== $newValue) {
                    \App\Models\AuditLog::create([
                        'user_id' => auth()->id(), 'actor_id' => auth()->id(), 'actor_role' => auth()->user()?->role,
                        'business_id' => $company->id, 'module' => 'Permissions', 'action' => $newValue ? 'permission granted' : 'permission revoked',
                        'description' => $key.' changed for '.$company->business_name,
                        'old_values' => ['permission_key' => $key, 'allowed' => $oldValue],
                        'new_values' => ['permission_key' => $key, 'allowed' => $newValue],
                        'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                    ]);
                }
            }
            \App\Models\AuditLog::create([
                'user_id' => auth()->id(), 'actor_id' => auth()->id(), 'actor_role' => auth()->user()?->role,
                'business_id' => $company->id, 'module' => 'Permissions', 'action' => 'company permissions updated',
                'description' => 'Company permissions updated for '.$company->business_name,
                'new_values' => ['enabled_permissions' => $selected], 'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
            $company->owner?->notify(new CompanyPermissionsUpdatedNotification($company));
        });
        app(CompanyPermissionService::class)->clear($company->id);

        return redirect()->route('admin.permissions.index', $lockedCompanyId !== null
            ? ['manage_company_id' => $company->id]
            : ['company_id' => $company->id])
            ->with('success', 'Permissions updated successfully for '.$company->business_name.'.');
    }

    public function templates(Request $request)
    {
        return view('super-admin.permissions.templates', [
            'templates' => PermissionTemplate::with('items')->latest()->get(),
            'definitions' => app(CompanyPermissionService::class)->activeDefinitions(),
            'companies' => Business::orderBy('business_name')->get(),
        ]);
    }

    public function storeTemplate(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:permission_templates,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::exists('permission_definitions', 'permission_key')->where('status', 'active')],
        ]);

        $template = PermissionTemplate::create(['name' => $data['name'], 'description' => $data['description'] ?? null, 'created_by' => auth()->id(), 'status' => 'active']);
        $template->items()->createMany(collect($data['permissions'] ?? [])->map(fn ($key) => ['permission_key' => $key, 'allowed' => true])->all());

        $this->audit($request, 'permission template created');
        return back()->with('success', 'Permission template created.');
    }

    public function applyTemplate(Request $request, PermissionTemplate $template)
    {
        $data = $request->validate(['company_id' => ['required', 'exists:businesses,id']]);
        $permissionService = app(CompanyPermissionService::class);
        $selected = $template->items()->where('allowed', true)->pluck('permission_key')
            ->map(fn ($permission) => $permissionService->normalise((string) $permission))
            ->unique()
            ->all();
        $allKeys = $permissionService->activeDefinitions()->pluck('permission_key')->all();

        foreach ($allKeys as $key) {
            CompanyPermission::updateOrCreate(
                ['company_id' => $data['company_id'], 'permission_key' => $key],
                ['allowed' => in_array($key, $selected, true), 'assigned_by' => auth()->id()]
            );
        }

        app(CompanyPermissionService::class)->clear((int) $data['company_id']);
        $this->audit($request, 'permission template applied to company', (int) $data['company_id']);
        return back()->with('success', 'Template applied. You can now override individual company permissions.');
    }

    private function screen(Request $request, string $scope)
    {
        $lockedCompanyId = $request->integer('manage_company_id') ?: null;
        if ($lockedCompanyId) {
            $request->session()->put('admin.permissions.locked_company_id', $lockedCompanyId);
        } else {
            $request->session()->forget('admin.permissions.locked_company_id');
        }

        $companyId = $lockedCompanyId ?: ($request->integer('company_id') ?: null);
        $definitions = $this->definitionsForScope($scope);

        $selectedCompany = $companyId ? Business::findOrFail($companyId) : null;
        if ($selectedCompany) {
            $this->audit($request, 'company permissions loaded', $selectedCompany->id);
        }

        return view('super-admin.permissions.index', [
            'title' => 'Company Permissions',
            'scope' => $scope,
            'companies' => Business::orderBy('business_name')->get(),
            'selectedCompany' => $selectedCompany,
            'lockedCompany' => $lockedCompanyId ? $selectedCompany : null,
            'definitions' => $definitions,
            'selectedPermissions' => old('permissions', $companyId
                ? CompanyPermission::where('company_id', $companyId)->where('allowed', true)->pluck('permission_key')
                    ->map(fn ($permission) => app(CompanyPermissionService::class)->normalise((string) $permission))
                    ->unique()
                    ->all()
                : []),
        ]);
    }

    private function definitionsForScope(string $scope)
    {
        return app(CompanyPermissionService::class)->activeDefinitions()
            ->when($scope !== 'all', fn ($definitions) => $definitions->where(
                'permission_type',
                match ($scope) { 'modules' => 'module', 'features' => 'feature', default => 'action' }
            ))
            ->values();
    }

    private function audit(Request $request, string $action, ?int $companyId = null): void
    {
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(), 'actor_id' => auth()->id(), 'actor_role' => auth()->user()?->role,
            'business_id' => $companyId, 'module' => 'Permissions', 'action' => $action, 'description' => ucfirst($action),
            'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
