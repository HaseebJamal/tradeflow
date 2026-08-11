<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    protected $fillable = ['owner_id', 'created_by', 'selected_plan_id', 'selected_billing_cycle', 'plan_selection_source', 'selected_plan_price', 'selected_plan_snapshot', 'trial_eligible', 'requested_trial_days', 'subscription_request_status', 'plan_selected_at', 'subscription_admin_note', 'business_name', 'business_type', 'business_description', 'category', 'phone', 'address', 'city', 'registration_number', 'tax_number', 'website', 'logo', 'status', 'archived_at', 'archived_by', 'archived_status'];

    protected $casts = ['archived_at' => 'datetime', 'selected_plan_snapshot' => 'array', 'trial_eligible' => 'boolean', 'plan_selected_at' => 'datetime'];

    protected static function booted(): void
    {
        static::created(function (self $business): void {
            app(\App\Services\BusinessDocumentFooterService::class)->for($business);
        });
    }

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function users() { return $this->hasMany(User::class); }
    public function documents() { return $this->hasMany(BusinessDocument::class); }
    public function documentFooter(): HasOne { return $this->hasOne(BusinessDocumentFooter::class); }
    public function footerChangeRequests() { return $this->hasMany(BusinessFooterChangeRequest::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function customers() { return $this->hasMany(Customer::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function expenses() { return $this->hasMany(Expense::class); }
    public function subscription() { return $this->hasOne(Subscription::class); }
    public function renewalInvoices() { return $this->hasMany(RenewalInvoice::class); }
    public function selectedPlan() { return $this->belongsTo(SubscriptionPlan::class, 'selected_plan_id'); }
    public function reports() { return $this->hasMany(BusinessReport::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function assignments() { return $this->hasMany(BusinessUserAssignment::class); }
    public function portfolioAdmins() { return $this->belongsToMany(User::class, 'business_user_assignments')->wherePivot('status', 'Active'); }
    public function companyPermissions() { return $this->hasMany(CompanyPermission::class, 'company_id'); }
    public function approvalLogs() { return $this->hasMany(CompanyApprovalLog::class, 'company_id')->latest('changed_at'); }
    public function approvalHistory() { return $this->approvalLogs(); }
    public function archivedBy() { return $this->belongsTo(User::class, 'archived_by'); }

    public function getDisplayBusinessTypeAttribute(): ?string
    {
        if ($this->business_type === 'Other' && filled($this->business_description)) {
            return $this->business_description;
        }

        return $this->business_type;
    }
}
