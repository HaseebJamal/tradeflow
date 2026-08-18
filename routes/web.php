<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CompanyPermissionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Business\BusinessDashboardController;
use App\Http\Controllers\Business\BusinessContextController;
use App\Http\Controllers\Business\BusinessNotificationController;
use App\Http\Controllers\Business\BusinessSubscriptionController;
use App\Http\Controllers\Business\AuditLogController;
use App\Http\Controllers\Business\CategoryController;
use App\Http\Controllers\Business\CustomerController;
use App\Http\Controllers\Business\DeliveryController;
use App\Http\Controllers\Business\EndOfDayReportController;
use App\Http\Controllers\Business\ExpenseController;
use App\Http\Controllers\Business\InventoryController;
use App\Http\Controllers\Business\BatchExpiryController;
use App\Http\Controllers\Business\InvoiceController;
use App\Http\Controllers\Business\KhataController;
use App\Http\Controllers\Business\OrderController;
use App\Http\Controllers\Business\PaymentController;
use App\Http\Controllers\Business\PosController;
use App\Http\Controllers\Business\ProductController;
use App\Http\Controllers\Business\ProductPerformanceController;
use App\Http\Controllers\Business\ReportController;
use App\Http\Controllers\Business\StaffController;
use App\Http\Controllers\Business\SettingsController;
use App\Http\Controllers\Business\StockMovementAnalyticsController;
use App\Http\Controllers\Business\SupportController;
use App\Http\Controllers\Business\SupplierController;
use App\Http\Controllers\Business\UnitController;
use App\Http\Controllers\BusinessOnboardingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\NewsletterSubscriptionController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Retailer\RetailerController;
use App\Http\Controllers\ThemePreferenceController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;

// Some Windows/XAMPP installations leave public/storage as a plain directory
// instead of Laravel's symbolic link. Serve public-disk files through a
// separate application path, avoiding Laravel's reserved /storage route.
Route::match(['get', 'head'], '/media/{path}', function (Request $request, string $path) {
    $path = ltrim($path, '/');
    abort_if(
        $path === '' || collect(explode('/', $path))->contains(fn (string $segment) => $segment === '.' || $segment === '..'),
        404,
    );

    $disk = Storage::disk('public');
    abort_unless($disk->exists($path), 404);

    // FilesystemAdapter::response() reads the entire file into memory.  That
    // breaks large demo videos and also prevents the browser from requesting
    // the MP4 metadata / seek ranges it needs for playback.  Stream the file
    // in small chunks and honour a single RFC 7233 byte range instead.
    $file = $disk->path($path);
    $size = filesize($file);
    abort_unless($size !== false, 404);

    $start = 0;
    $end = $size - 1;
    $status = 200;
    $range = $request->header('Range');

    if ($range !== null && preg_match('/^bytes=(\d*)-(\d*)$/i', trim($range), $matches)) {
        if ($matches[1] === '' && $matches[2] === '') {
            return response('', 416, ['Content-Range' => "bytes */{$size}"]);
        }

        if ($matches[1] === '') {
            $length = (int) $matches[2];
            $start = max(0, $size - $length);
        } else {
            $start = (int) $matches[1];
        }

        if ($matches[2] !== '') {
            $end = min((int) $matches[2], $size - 1);
        }

        if ($start > $end || $start >= $size) {
            return response('', 416, ['Content-Range' => "bytes */{$size}"]);
        }

        $status = 206;
    }

    $length = $end - $start + 1;
    $headers = [
        'Cache-Control' => 'public, max-age=86400',
        'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
        'Content-Length' => (string) $length,
        'Accept-Ranges' => 'bytes',
        'Content-Disposition' => 'inline; filename="'.str_replace('"', '', basename($path)).'"',
    ];

    if ($status === 206) {
        $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
    }

    if ($request->isMethod('HEAD')) {
        return response('', $status, $headers);
    }

    return response()->stream(function () use ($file, $start, $length): void {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            fseek($handle, $start);
            $remaining = $length;

            while ($remaining > 0 && !feof($handle)) {
                $chunk = fread($handle, min(8 * 1024 * 1024, $remaining));

                if ($chunk === false || $chunk === '') {
                    break;
                }

                echo $chunk;
                $remaining -= strlen($chunk);

                if (function_exists('flush')) {
                    flush();
                }
            }
        } finally {
            fclose($handle);
        }
    }, $status, $headers);
})->where('path', '.*')->name('media.public');

Route::get('/', fn() => view('public.home', [
    'currentSubscription' => auth()->user()?->business_id
        ? Subscription::with('plan')->where('business_id', auth()->user()->business_id)->first()
        : null,
]))->name('public.home');
Route::view('/features', 'public.features')->name('public.features');
Route::redirect('/pricing', '/register-business')->name('public.pricing');
Route::view('/contact', 'public.contact')->name('public.contact');
Route::view('/privacy-security', 'public.privacy-security')->name('privacy.security');
Route::get('/subscribe/{plan}', function (string $plan) {
    return redirect()->route('register.business');
})->name('subscribe.plan');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
// POST is the normal footer flow. GET is retained as a validation-protected
// fallback for already-open pages rendered before the newsletter form gained
// its explicit method attribute; it prevents a stale client from receiving a
// 405 response.
Route::match(['get', 'post'], '/newsletter-subscriptions', [NewsletterSubscriptionController::class, 'store'])->middleware('throttle:5,1')->name('newsletter-subscriptions.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,10')->name('password.update');
    Route::get('/otp-verification', [AuthController::class, 'otpForm'])->name('otp');
});

Route::get('/register-business', [BusinessOnboardingController::class, 'create'])->name('register.business');
Route::post('/register-business', [BusinessOnboardingController::class, 'store'])->name('register.business.store');

Route::middleware('auth')->group(function () {
    Route::put('/theme-preference', ThemePreferenceController::class)->name('theme.preference.update');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard.redirect');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::match(['post', 'put'], '/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::post('/profile/email-change-requests', [ProfileController::class, 'requestEmailChange'])->name('profile.email-change-requests.store');
    Route::patch('/profile/email-change-requests/{changeRequest}/approve', [ProfileController::class, 'approveEmailChangeRequest'])->name('profile.email-change-requests.approve');
    Route::patch('/profile/email-change-requests/{changeRequest}/reject', [ProfileController::class, 'rejectEmailChangeRequest'])->name('profile.email-change-requests.reject');
    Route::patch('/profile/email-change-requests/{changeRequest}/request-changes', [ProfileController::class, 'requestEmailChangeChanges'])->name('profile.email-change-requests.request-changes');
    Route::patch('/profile/user-detail-change-requests/{changeRequest}/approve', [ProfileController::class, 'approveUserDetailChangeRequest'])->name('profile.user-detail-change-requests.approve');
    Route::patch('/profile/user-detail-change-requests/{changeRequest}/apply', [ProfileController::class, 'applyUserDetailChangeRequest'])->name('profile.user-detail-change-requests.apply');
    Route::patch('/profile/user-detail-change-requests/{changeRequest}/reject', [ProfileController::class, 'rejectUserDetailChangeRequest'])->name('profile.user-detail-change-requests.reject');
    Route::post('/activity/heartbeat', [AdminController::class, 'heartbeat'])->middleware('business.subscription.access')->name('activity.heartbeat');
    Route::get('/notifications', function (\Illuminate\Http\Request $request) {
        if ($request->user()?->role === 'super_admin' && $request->session()->has('super_admin_business_context_id')) {
            return redirect()->route('business.context.notifications');
        }

        return app(BusinessNotificationController::class)->index($request);
    })->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [BusinessNotificationController::class, 'markRead'])->name('notifications.read');
    Route::delete('/notifications/{notification}', [BusinessNotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::patch('/notifications/read-all', [BusinessNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::prefix('business')->name('business.')->middleware(['super_admin.context', 'role:super_admin,business_owner,custom_staff', 'business.approved', 'track.activity'])->group(function () {
        Route::get('/support', [SupportController::class, 'index'])->name('support');
        Route::post('/support', [SupportController::class, 'store'])->name('support.store');
        Route::get('/subscription', [BusinessSubscriptionController::class, 'index'])->name('subscription.index')->middleware('business.permission:Subscriptions');
        Route::get('/subscription/history', [BusinessSubscriptionController::class, 'history'])->name('subscription.history')->middleware('business.permission:Subscriptions');
        Route::post('/subscription/requests', [BusinessSubscriptionController::class, 'storeRequest'])->name('subscription.requests.store')->middleware('business.permission:Subscriptions');
        Route::patch('/subscription/requests/{changeRequest}/cancel', [BusinessSubscriptionController::class, 'cancelRequest'])->name('subscription.requests.cancel')->middleware('business.permission:Subscriptions');
    });
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'record.context', 'role:super_admin', 'track.activity'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/business-requests', [\App\Http\Controllers\Admin\BusinessRequestController::class, 'index'])->name('business-requests.index');
    Route::get('/business-requests/{source}/{requestId}', [\App\Http\Controllers\Admin\BusinessRequestController::class, 'show'])
        ->whereIn('source', \App\Services\BusinessRequestIndexService::SOURCES)
        ->whereNumber('requestId')
        ->name('business-requests.show');
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('/companies/{company}/onboarding', [CompanyController::class, 'onboarding'])->name('companies.onboarding');
    Route::post('/companies/{company}/onboarding/email', [CompanyController::class, 'sendOnboardingEmail'])->name('companies.onboarding.email');
    Route::post('/companies/{company}/onboarding/whatsapp-draft', [CompanyController::class, 'openOnboardingWhatsAppDraft'])->name('companies.onboarding.whatsapp');
    Route::post('/companies/{company}/onboarding/done', [CompanyController::class, 'finishOnboarding'])->name('companies.onboarding.done');
    Route::get('/companies/pending', fn(\Illuminate\Http\Request $request) => app(CompanyController::class)->index($request, 'pending'))->name('companies.pending');
    Route::get('/companies/approved', fn(\Illuminate\Http\Request $request) => app(CompanyController::class)->index($request, 'approved'))->name('companies.approved');
    Route::get('/companies/rejected', fn(\Illuminate\Http\Request $request) => app(CompanyController::class)->index($request, 'rejected'))->name('companies.rejected');
    Route::get('/companies/suspended', fn(\Illuminate\Http\Request $request) => app(CompanyController::class)->index($request, 'suspended'))->name('companies.suspended');
    Route::get('/companies/archived', fn(\Illuminate\Http\Request $request) => app(CompanyController::class)->index($request, 'archived'))->name('companies.archived');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::get('/companies/{company}/document-footer', [CompanyController::class, 'editDocumentFooter'])->name('companies.document-footer.edit');
    Route::put('/companies/{company}/document-footer', [CompanyController::class, 'updateDocumentFooter'])->name('companies.document-footer.update');
    Route::patch('/companies/{company}/document-footer/reset', [CompanyController::class, 'resetDocumentFooter'])->name('companies.document-footer.reset');
    Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::post('/companies/{company}/documents', [CompanyController::class, 'uploadVerificationDocuments'])->name('companies.documents.store');
    Route::patch('/companies/{company}/status', [CompanyController::class, 'updateStatus'])->name('companies.status');
    Route::patch('/companies/{company}/documents/{document}/verification', [CompanyController::class, 'verifyDocument'])->name('companies.documents.verify');
    Route::patch('/companies/{company}/registration-plan', [CompanyController::class, 'updateRegistrationPlan'])->name('companies.registration-plan.update');
    Route::patch('/companies/{company}/archive', [CompanyController::class, 'archive'])->name('companies.archive');
    Route::patch('/companies/{company}/restore', [CompanyController::class, 'restore'])->name('companies.restore');
    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');
    Route::get('/business-detail-change-requests', [CompanyController::class, 'detailChangeRequests'])->name('business-detail-change-requests.index');
    Route::patch('/business-detail-change-requests/{changeRequest}/approve', [CompanyController::class, 'approveDetailChangeRequest'])->name('business-detail-change-requests.approve');
    Route::patch('/business-detail-change-requests/{changeRequest}/apply', [CompanyController::class, 'applyDetailChangeRequest'])->name('business-detail-change-requests.apply');
    Route::patch('/business-detail-change-requests/{changeRequest}/reject', [CompanyController::class, 'rejectDetailChangeRequest'])->name('business-detail-change-requests.reject');
    Route::get('/footer-change-requests', [\App\Http\Controllers\Admin\FooterChangeRequestController::class, 'index'])->name('footer-change-requests.index');
    Route::patch('/footer-change-requests/{footerChangeRequest}/review', [\App\Http\Controllers\Admin\FooterChangeRequestController::class, 'review'])->name('footer-change-requests.review');
    Route::post('/companies/{company}/open-dashboard', [CompanyController::class, 'openDashboard'])->name('companies.open-dashboard');
    Route::post('/company-context/return', [CompanyController::class, 'returnToDashboard'])->name('company-context.return');
    Route::post('/companies/{company}/owner/reset-password', [CompanyController::class, 'resetOwnerPassword'])->name('companies.owner.reset-password');
    Route::post('/companies/{company}/staff/{staff}/reset-password', [CompanyController::class, 'resetStaffPassword'])->name('companies.staff.reset-password');
    Route::get('/permissions', [CompanyPermissionController::class, 'index'])->name('permissions.index');
    Route::get('/permissions/modules', [CompanyPermissionController::class, 'modules'])->name('permissions.modules');
    Route::get('/permissions/features', [CompanyPermissionController::class, 'features'])->name('permissions.features');
    Route::get('/permissions/actions', [CompanyPermissionController::class, 'actions'])->name('permissions.actions');
    Route::put('/permissions/company', [CompanyPermissionController::class, 'update'])->name('permissions.company.update');
    Route::get('/permissions/templates', [CompanyPermissionController::class, 'templates'])->name('permissions.templates');
    Route::post('/permissions/templates', [CompanyPermissionController::class, 'storeTemplate'])->name('permissions.templates.store');
    Route::post('/permissions/templates/{template}/apply', [CompanyPermissionController::class, 'applyTemplate'])->name('permissions.templates.apply');
    Route::get('/notifications-center', [AdminNotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications-center/unread', fn(\Illuminate\Http\Request $request) => app(AdminNotificationController::class)->index($request, 'unread'))->name('notifications.unread');
    Route::get('/notifications-center/registrations', fn(\Illuminate\Http\Request $request) => app(AdminNotificationController::class)->index($request, 'registrations'))->name('notifications.registrations');
    Route::get('/notifications-center/alerts', fn(\Illuminate\Http\Request $request) => app(AdminNotificationController::class)->index($request, 'alerts'))->name('notifications.alerts');
    Route::patch('/notifications-center/{notification}/read', [AdminNotificationController::class, 'markRead'])->name('notifications.read');
    Route::delete('/notifications-center/{notification}', [AdminNotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::get('/notifications-center/{notification}', [AdminNotificationController::class, 'show'])->name('notifications.show');
    Route::get('/notifications-center/{notification}/review', [AdminNotificationController::class, 'review'])->name('notifications.review');
    Route::patch('/notifications-center/read-all', [AdminNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/approvals/pending', fn(\Illuminate\Http\Request $request) => app(CompanyController::class)->index($request, 'pending'))->name('approvals.pending');
    Route::get('/approvals/history', [CompanyController::class, 'approvalHistory'])->name('approvals.history');
    Route::get('/approvals/history/{approvalLog}', [CompanyController::class, 'approvalHistoryShow'])->name('approvals.history.show');
    Route::get('/approvals/rejected', fn(\Illuminate\Http\Request $request) => app(CompanyController::class)->index($request, 'rejected'))->name('approvals.rejected');
    Route::get('/approvals/suspended', fn(\Illuminate\Http\Request $request) => app(CompanyController::class)->index($request, 'suspended'))->name('approvals.suspended');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.show');
    Route::get('/profile/settings', [ProfileController::class, 'edit'])->name('profile.settings');
    Route::get('/profile/password', [ProfileController::class, 'edit'])->name('profile.password');
    Route::get('/profile/security', fn() => view('super-admin.profile-security'))->name('profile.security');
    Route::get('/live-activity', [AdminController::class, 'liveActivity'])->name('live-activity');
    Route::get('/businesses', [AdminController::class, 'businesses'])->name('businesses');
    Route::get('/businesses/{business}', [AdminController::class, 'businessShow'])->name('businesses.show');
    Route::patch('/businesses/{business}/status', [AdminController::class, 'updateStatus'])->name('businesses.status');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus'])->name('users.status');
    Route::patch('/users/{user}/reset-password', [AdminController::class, 'resetUserPassword'])->name('users.reset-password');
    Route::get('/subscriptions', [AdminController::class, 'subscriptions'])->name('subscriptions');
    Route::put('/subscriptions/trial-settings', [AdminController::class, 'updateDefaultTrialDays'])->name('subscriptions.trial-settings.update');
    Route::post('/subscription-plans', [AdminController::class, 'storePlan'])->name('subscription-plans.store');
    Route::patch('/subscription-plans/{plan}', [AdminController::class, 'updatePlan'])->name('subscription-plans.update');
    Route::delete('/subscription-plans/{plan}', [AdminController::class, 'destroyPlan'])->name('subscription-plans.destroy');
    Route::patch('/subscription-plans/{plan}/status', [AdminController::class, 'setPlanStatus'])->name('subscription-plans.status');
    Route::patch('/subscription-plans/{plan}/archive', [AdminController::class, 'archivePlan'])->name('subscription-plans.archive');
    Route::patch('/subscription-plans/{plan}/restore', [AdminController::class, 'restorePlan'])->name('subscription-plans.restore');
    Route::patch('/subscription-plans/{plan}/visibility', [AdminController::class, 'togglePlanVisibility'])->name('subscription-plans.visibility');
    Route::patch('/subscription-plans/{plan}/recommended', [AdminController::class, 'togglePlanRecommendation'])->name('subscription-plans.recommended');
    Route::post('/subscriptions', [AdminController::class, 'activateSubscription'])->name('subscriptions.activate');
    Route::patch('/subscriptions/{subscription}', [AdminController::class, 'updateSubscription'])->name('subscriptions.update');
    Route::patch('/subscriptions/{subscription}/cancel', [AdminController::class, 'cancelSubscription'])->name('subscriptions.cancel');
    Route::delete('/subscriptions/{subscription}', [AdminController::class, 'destroySubscription'])->name('subscriptions.destroy');
    Route::patch('/subscriptions/{subscription}/transition', [AdminController::class, 'transitionSubscription'])->name('subscriptions.transition');
    Route::patch('/subscriptions/{subscription}/extend-trial', [AdminController::class, 'extendTrial'])->name('subscriptions.extend-trial');
    // Accept PUT as well as PATCH during the transition from the prior
    // management forms. Both methods execute the same validated, audited
    // partial update and this prevents a stale cached form from producing a
    // 405 while users refresh to the current PATCH markup.
    Route::match(['put', 'patch'], '/subscriptions/{subscription}/trial', [AdminController::class, 'adjustTrial'])->name('subscriptions.trial.adjust');
    Route::match(['put', 'patch'], '/subscriptions/{subscription}/paid-access', [AdminController::class, 'adjustPaidAccess'])->name('subscriptions.paid-access.adjust');
    Route::match(['put', 'patch'], '/subscriptions/{subscription}/reactivate-paid-access', [AdminController::class, 'reactivatePaidAccess'])->name('subscriptions.paid-access.reactivate');
    Route::match(['put', 'patch'], '/businesses/{business}/restore-access', [AdminController::class, 'restoreRestrictedAccess'])->name('businesses.access.restore');
    Route::get('/subscription-change-requests/{changeRequest}/review', [AdminController::class, 'subscriptionChangeRequestReview'])->name('subscription-change-requests.show');
    Route::patch('/subscription-change-requests/{changeRequest}/review-details', [AdminController::class, 'updateSubscriptionChangeRequestReview'])->name('subscription-change-requests.review-details');
    Route::patch('/subscription-change-requests/{changeRequest}', [AdminController::class, 'reviewSubscriptionChangeRequest'])->name('subscription-change-requests.review');
    Route::get('/support-tickets', [AdminController::class, 'supportTickets'])->name('support-tickets');
    Route::patch('/support-tickets/{ticket}', [AdminController::class, 'updateTicket'])->name('support-tickets.update');
    Route::get('/newsletter-subscribers', [AdminController::class, 'newsletterSubscribers'])->name('newsletter-subscribers.index');
    Route::patch('/newsletter-subscribers/{subscriber}', [AdminController::class, 'updateNewsletterSubscriber'])->name('newsletter-subscribers.update');
    Route::delete('/newsletter-subscribers/{subscriber}', [AdminController::class, 'destroyNewsletterSubscriber'])->name('newsletter-subscribers.destroy');
    Route::get('/payments', [AdminController::class, 'payments'])->name('payments');
    Route::post('/payments', [AdminController::class, 'storePlatformPayment'])->name('payments.store');
    Route::get('/payments/renewals/{invoice}/pdf', [AdminController::class, 'renewalInvoicePdf'])->name('renewal-invoices.pdf');
    Route::post('/payments/renewals/{invoice}/email', [AdminController::class, 'sendRenewalInvoiceEmail'])->name('renewal-invoices.email');
    Route::post('/payments/renewals/{invoice}/whatsapp', [AdminController::class, 'openRenewalInvoiceWhatsApp'])->name('renewal-invoices.whatsapp');
    Route::patch('/payments/renewals/{invoice}/amount', [AdminController::class, 'updateRenewalInvoiceAmount'])->name('renewal-invoices.amount');
    Route::patch('/payments/renewals/{invoice}/cancel', [AdminController::class, 'cancelRenewalInvoice'])->name('renewal-invoices.cancel');
    Route::patch('/payments/{payment}/approve', [AdminController::class, 'approvePlatformPayment'])->name('payments.approve');
    Route::patch('/payments/{payment}/reject', [AdminController::class, 'rejectPlatformPayment'])->name('payments.reject');
    Route::delete('/payments/{payment}', [AdminController::class, 'destroyPlatformPayment'])->name('payments.destroy');
    Route::get('/payments/{payment}/proof', [AdminController::class, 'platformPaymentProof'])->name('payments.proof');
    Route::get('/payments/{payment}/receipt', [AdminController::class, 'platformPaymentReceipt'])->name('payments.receipt');
    Route::get('/notifications', [AdminController::class, 'notifications'])->name('notifications');
    Route::post('/notifications', [AdminController::class, 'storeAnnouncement'])->name('notifications.store');
    Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('audit-logs');
    Route::get('/audit-logs/live', [AdminController::class, 'liveAuditLogs'])->name('audit-logs.live');
    Route::get('/audit-logs/export/csv', [AdminController::class, 'exportAuditLogsCsv'])->name('audit-logs.export.csv');
    Route::get('/audit-logs/export/pdf', [AdminController::class, 'exportAuditLogsPdf'])->name('audit-logs.export.pdf');
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::put('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
    // Browser multipart uploads are transported as POST and may not retain the
    // hidden PUT override when infrastructure rejects an oversized body. Accept
    // both transports for this authenticated Super Admin upload endpoint.
    Route::match(['put', 'post'], '/settings/demo-video', [AdminController::class, 'updateDemoVideoSettings'])->name('settings.demo-video.update');
    Route::put('/settings/whatsapp-contact', [AdminController::class, 'updateWhatsAppContact'])->name('settings.whatsapp.update');
    Route::patch('/settings/demo-video/active', [AdminController::class, 'toggleDemoVideoActive'])->name('settings.demo-video.active');
    Route::patch('/settings/whatsapp-contact/active', [AdminController::class, 'toggleWhatsAppActive'])->name('settings.whatsapp.active');
    Route::delete('/settings/demo-video', [AdminController::class, 'removeDemoVideo'])->name('settings.demo-video.destroy');
    Route::delete('/settings/whatsapp-contact', [AdminController::class, 'removeWhatsAppContact'])->name('settings.whatsapp.destroy');
    Route::put('/settings/restore-default-logo', [AdminController::class, 'restoreDefaultLogo'])->name('settings.restore-default-logo');
    Route::put('/settings/reset-defaults', [AdminController::class, 'resetSettingsDefaults'])->name('settings.reset-defaults');
    Route::get('/business-reports', [AdminController::class, 'businessReports'])->name('business-reports');
    Route::get('/business-reports/export/excel', [AdminController::class, 'businessReportsExcel'])->name('business-reports.export.excel');
    Route::get('/business-reports/export/pdf', [AdminController::class, 'businessReportsPdf'])->name('business-reports.export.pdf');
    Route::get('/business-reports/history', [AdminController::class, 'businessReportHistory'])->name('business-reports.history');
    Route::get('/business-reports/reports/{report}/edit', [AdminController::class, 'editBusinessReport'])->name('business-reports.edit');
    Route::put('/business-reports/reports/{report}', [AdminController::class, 'updateBusinessReport'])->name('business-reports.update');
    Route::get('/business-reports/{business}', [AdminController::class, 'businessReportShow'])->name('business-reports.show');
    Route::post('/business-reports/{report}/approve', [AdminController::class, 'approveReport'])->name('business-reports.approve');
    Route::post('/business-reports/{report}/reject', [AdminController::class, 'rejectReport'])->name('business-reports.reject');
    Route::get('/business-reports/{report}/pdf', [AdminController::class, 'reportPdf'])->name('business-reports.pdf');
});

Route::prefix('business')->name('business.')->middleware(['auth', 'super_admin.context', 'record.context', 'role:super_admin,business_owner,custom_staff', 'business.approved', 'business.subscription.access', 'business.action', 'track.activity'])->group(function () {
    Route::get('/access-denied', function (\App\Services\BusinessWorkspaceAccessService $workspaceAccess) {
        // Re-check current permissions so a stale denial page does not survive
        // a Super Admin permission update in the same authenticated session.
        $destination = $workspaceAccess->firstEnabledRoute(request()->user());

        return $destination
            ? redirect()->route($destination)
            : view('business.access-denied');
    })->name('access-denied');
    // Keep the basic dashboard available to approved businesses even when no
    // operational module is enabled. Permission middleware uses it as its
    // one-way denial destination, so it must not guard itself.
    Route::get('/dashboard', BusinessDashboardController::class)->name('dashboard');
    Route::get('/context-profile', [BusinessContextController::class, 'profile'])->name('context.profile');
    Route::get('/context-notifications', [BusinessContextController::class, 'notifications'])->name('context.notifications');
    Route::patch('/products/{product}/low-stock-alert', [ProductController::class, 'updateLowStockAlert'])->name('products.low-stock-alert')->middleware('business.permission:Products');
    Route::patch('/products/{product}/status', [ProductController::class, 'updateStatus'])->name('products.status')->middleware('business.permission:Products');
    Route::get('/products-bulk', [ProductController::class, 'bulk'])->name('products.bulk')->middleware('business.permission:Products');
    Route::post('/products-bulk', [ProductController::class, 'bulkStore'])->name('products.bulk.store')->middleware('business.permission:Products');
    Route::get('/products-template.csv', [ProductController::class, 'csvTemplate'])->name('products.template')->middleware('business.permission:Products');
    Route::get('/products-export.csv', [ProductController::class, 'export'])->name('products.export')->middleware('business.permission:Products');
    Route::get('/products/bulk-pricing', [ProductController::class, 'bulkPricing'])->name('products.bulk-pricing')->middleware('business.permission:Products');
    Route::patch('/products/bulk-pricing', [ProductController::class, 'updateBulkPricing'])->name('products.bulk-pricing.update')->middleware('business.permission:Products');
    Route::get('/products/lookup', [ProductController::class, 'lookup'])->name('products.lookup')->middleware('business.permission:Products');
    Route::post('/products/labels/preview', [ProductController::class, 'labelPreview'])->name('products.labels.preview')->middleware('business.permission:Products');
    Route::patch('/products/{product}/archive', [ProductController::class, 'archive'])->name('products.archive')->middleware('business.permission:Products');
    Route::patch('/products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore')->middleware('business.permission:Products');
    Route::resource('products', ProductController::class)->middleware('business.permission:Products');
    Route::patch('/categories/{category}/status', [CategoryController::class, 'toggleStatus'])->name('categories.status')->middleware('business.permission:Categories');
    Route::patch('/categories/{category}/archive', [CategoryController::class, 'archive'])->name('categories.archive')->middleware('business.permission:Categories');
    Route::patch('/categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore')->middleware('business.permission:Categories');
    Route::resource('categories', CategoryController::class)->middleware('business.permission:Categories');
    Route::patch('/units/{unit}/status', [UnitController::class, 'toggleStatus'])->name('units.status')->middleware('business.permission:Units');
    Route::patch('/units/{unit}/archive', [UnitController::class, 'archive'])->name('units.archive')->middleware('business.permission:Units');
    Route::patch('/units/{unit}/restore', [UnitController::class, 'restore'])->name('units.restore')->middleware('business.permission:Units');
    Route::resource('units', UnitController::class)->middleware('business.permission:Units');
    Route::patch('/customers/{customer}/archive', [CustomerController::class, 'archive'])->name('customers.archive')->middleware('business.permission:Customers');
    Route::patch('/customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore')->middleware('business.permission:Customers');
    Route::patch('/customers/{customer}/status', [CustomerController::class, 'updateStatus'])->name('customers.status')->middleware('business.permission:Customers');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy')->middleware('business.permission:Customers');
    Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement')->middleware('business.permission:Customers');
    Route::post('/customers/{customer}/balance-adjustments', [\App\Http\Controllers\Business\BalanceAdjustmentController::class, 'storeCustomer'])->name('customers.balance-adjustments.store')->middleware('business.permission:Customers');
    Route::post('/customers/{customer}/balance-adjustments/{adjustment}/reverse', [\App\Http\Controllers\Business\BalanceAdjustmentController::class, 'reverseCustomer'])->name('customers.balance-adjustments.reverse')->middleware('business.permission:Customers');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory')->middleware('business.permission:Inventory');
    Route::get('/inventory/purchase-suggestions', [\App\Http\Controllers\Business\ReorderSuggestionController::class, 'index'])->name('inventory.purchase-suggestions.index')->middleware('business.permission:Inventory');
    Route::get('/inventory/reorder-settings', [\App\Http\Controllers\Business\ReorderSuggestionController::class, 'settings'])->name('inventory.purchase-suggestions.settings')->middleware('business.permission:Inventory');
    Route::patch('/inventory/reorder-settings', [\App\Http\Controllers\Business\ReorderSuggestionController::class, 'updateSettings'])->name('inventory.purchase-suggestions.settings.update')->middleware('business.permission:Inventory');
    Route::get('/inventory/history', [InventoryController::class, 'history'])->name('inventory.history')->middleware('business.permission:Inventory');
    Route::get('/inventory/batches', [BatchExpiryController::class, 'index'])->name('inventory.batches.index')->middleware('business.permission:Inventory');
    Route::post('/inventory/batches/{product}/opening-allocation', [BatchExpiryController::class, 'allocateOpening'])->name('inventory.batches.opening-allocation')->middleware('business.permission:Inventory');
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust')->middleware('business.permission:Inventory');
    Route::patch('/inventory/{inventory}/alert', [InventoryController::class, 'updateAlert'])->name('inventory.alert')->middleware('business.permission:Inventory');
    Route::get('/inventory/stock-counts', [\App\Http\Controllers\Business\StockCountController::class, 'index'])->name('inventory.stock-counts.index')->middleware('business.permission:Inventory');
    Route::post('/inventory/stock-counts', [\App\Http\Controllers\Business\StockCountController::class, 'create'])->name('inventory.stock-counts.create')->middleware('business.permission:Inventory');
    Route::get('/inventory/stock-counts/{stockCount}', [\App\Http\Controllers\Business\StockCountController::class, 'show'])->name('inventory.stock-counts.show')->middleware('business.permission:Inventory');
    Route::get('/inventory/stock-counts/{stockCount}/edit', [\App\Http\Controllers\Business\StockCountController::class, 'edit'])->name('inventory.stock-counts.edit')->middleware('business.permission:Inventory');
    Route::patch('/inventory/stock-counts/{stockCount}', [\App\Http\Controllers\Business\StockCountController::class, 'update'])->name('inventory.stock-counts.update')->middleware('business.permission:Inventory');
    Route::post('/inventory/stock-counts/{stockCount}/items', [\App\Http\Controllers\Business\StockCountController::class, 'addProduct'])->name('inventory.stock-counts.items.store')->middleware('business.permission:Inventory');
    Route::patch('/inventory/stock-counts/{stockCount}/start', [\App\Http\Controllers\Business\StockCountController::class, 'start'])->name('inventory.stock-counts.start')->middleware('business.permission:Inventory');
    Route::post('/inventory/stock-counts/{stockCount}/finalize', [\App\Http\Controllers\Business\StockCountController::class, 'finalize'])->name('inventory.stock-counts.finalize')->middleware('business.permission:Inventory');
    Route::patch('/inventory/stock-counts/{stockCount}/cancel', [\App\Http\Controllers\Business\StockCountController::class, 'cancel'])->name('inventory.stock-counts.cancel')->middleware('business.permission:Inventory');
    Route::resource('customers', CustomerController::class)->only(['index', 'store', 'show', 'update'])->middleware('business.permission:Customers');
    Route::patch('/suppliers/{supplier}/archive', [SupplierController::class, 'archive'])->name('suppliers.archive')->middleware('business.permission:Suppliers');
    Route::patch('/suppliers/{supplier}/restore', [SupplierController::class, 'restore'])->name('suppliers.restore')->middleware('business.permission:Suppliers');
    Route::post('/suppliers/{supplier}/balance-adjustments', [\App\Http\Controllers\Business\BalanceAdjustmentController::class, 'storeSupplier'])->name('suppliers.balance-adjustments.store')->middleware('business.permission:Suppliers');
    Route::post('/suppliers/{supplier}/balance-adjustments/{adjustment}/reverse', [\App\Http\Controllers\Business\BalanceAdjustmentController::class, 'reverseSupplier'])->name('suppliers.balance-adjustments.reverse')->middleware('business.permission:Suppliers');
    Route::resource('suppliers', SupplierController::class)->middleware('business.permission:Suppliers');
    Route::get('/purchases', [\App\Http\Controllers\Business\PurchaseController::class, 'index'])->name('purchases.index')->middleware('business.permission:Purchases');
    Route::get('/purchases/create', [\App\Http\Controllers\Business\PurchaseController::class, 'create'])->name('purchases.create')->middleware('business.permission:Purchases');
    Route::get('/purchases/from-suggestions', [\App\Http\Controllers\Business\PurchaseController::class, 'fromSuggestions'])->name('purchases.from-suggestions')->middleware('business.permission:Purchases');
    Route::post('/purchases/suppliers', [SupplierController::class, 'store'])->name('purchases.suppliers.store')->middleware(['business.permission:Suppliers', 'company.permission:suppliers.create']);
    Route::post('/purchases', [\App\Http\Controllers\Business\PurchaseController::class, 'store'])->name('purchases.store')->middleware('business.permission:Purchases');
    Route::get('/purchases/lookup', [\App\Http\Controllers\Business\PurchaseController::class, 'lookup'])->name('purchases.lookup')->middleware('business.permission:Purchases');
    Route::get('/purchase-returns', [\App\Http\Controllers\Business\PurchaseReturnController::class, 'index'])->name('purchase-returns.index')->middleware(['business.permission:Purchase Returns', 'business.permission:Purchases']);
    Route::get('/purchase-returns/create', [\App\Http\Controllers\Business\PurchaseReturnController::class, 'create'])->name('purchase-returns.create')->middleware(['business.permission:Purchase Returns', 'business.permission:Purchases']);
    Route::post('/purchase-returns/start', [\App\Http\Controllers\Business\PurchaseReturnController::class, 'start'])->name('purchase-returns.start')->middleware(['business.permission:Purchase Returns', 'business.permission:Purchases']);
    Route::get('/purchase-returns/{purchaseReturn}', [\App\Http\Controllers\Business\PurchaseReturnController::class, 'show'])->name('purchase-returns.show')->middleware(['business.permission:Purchase Returns', 'business.permission:Purchases']);
    Route::get('/purchase-returns/{purchaseReturn}/edit', [\App\Http\Controllers\Business\PurchaseReturnController::class, 'edit'])->name('purchase-returns.edit')->middleware(['business.permission:Purchase Returns', 'business.permission:Purchases']);
    Route::get('/purchases/{purchase}/edit', [\App\Http\Controllers\Business\PurchaseController::class, 'edit'])->name('purchases.edit')->middleware('business.permission:Purchases');
    Route::put('/purchases/{purchase}', [\App\Http\Controllers\Business\PurchaseController::class, 'update'])->name('purchases.update')->middleware('business.permission:Purchases');
    Route::post('/purchases/{purchase}/cancel', [\App\Http\Controllers\Business\PurchaseController::class, 'cancel'])->name('purchases.cancel')->middleware('business.permission:Purchases');
    Route::get('/purchases/{purchase}', [\App\Http\Controllers\Business\PurchaseController::class, 'show'])->name('purchases.show')->middleware('business.permission:Purchases');
    Route::get('/purchases/{purchase}/receiving', [\App\Http\Controllers\Business\GoodsReceiptController::class, 'create'])->name('purchases.receiving.create')->middleware('business.permission:Purchases');
    Route::post('/purchases/{purchase}/receipts', [\App\Http\Controllers\Business\GoodsReceiptController::class, 'store'])->name('purchases.receipts.store')->middleware('business.permission:Purchases');
    Route::get('/goods-receipts/{goodsReceipt}', [\App\Http\Controllers\Business\GoodsReceiptController::class, 'show'])->name('goods-receipts.show')->middleware('business.permission:Purchases');
    Route::post('/purchases/{purchase}/receive', [\App\Http\Controllers\Business\PurchaseController::class, 'receive'])->name('purchases.receive')->middleware('business.permission:Purchases');
    Route::post('/purchases/{purchase}/payments', [\App\Http\Controllers\Business\PurchaseController::class, 'pay'])->name('purchases.pay')->middleware('business.permission:Purchases');
    Route::post('/purchases/{purchase}/refund-settlements', [\App\Http\Controllers\Business\PurchaseController::class, 'settleReceiptRefund'])->name('purchases.refund-settlements.store')->middleware('business.permission:Purchases');
    Route::post('/purchases/{purchase}/returns', [\App\Http\Controllers\Business\PurchaseController::class, 'processReturn'])->name('purchases.return')->middleware('business.permission:Purchases');
    // Sales is the consolidated home for orders, customer payments, and sales invoices.
    Route::get('/sales', [OrderController::class, 'index'])->name('sales.index')->middleware('business.permission:Sales');
    Route::get('/sales/lookup', [OrderController::class, 'lookup'])->name('sales.lookup')->middleware('business.permission:Sales');
    Route::prefix('pos')->name('pos.')->middleware('business.permission:POS')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('/products', [PosController::class, 'products'])->name('products');
        Route::get('/barcode', [PosController::class, 'barcode'])->name('barcode');
        Route::post('/register/open', [PosController::class, 'openRegister'])->name('register.open');
        Route::patch('/register/{register}/close', [PosController::class, 'closeRegister'])->name('register.close');
        Route::get('/register/history', [PosController::class, 'registerHistory'])->name('register.history');
        Route::get('/register/{register}/reconciliation', [PosController::class, 'reconciliation'])->name('register.reconciliation');
        Route::post('/register/{register}/cash-movements', [PosController::class, 'recordCashMovement'])->name('register.cash-movements.store');
        Route::post('/sales', [PosController::class, 'store'])->name('sales.store');
        Route::get('/history', [PosController::class, 'history'])->name('history');
        Route::get('/held-sales/search', [PosController::class, 'searchHeldSales'])->name('held-sales.search');
        Route::get('/invoices/search', [PosController::class, 'searchInvoices'])->name('invoices.search');
        Route::post('/invoices/{invoice}/deliveries', [DeliveryController::class, 'assignFromPosInvoice'])->name('delivery.assign');
        Route::get('/receipts/{invoice}/view', [PosController::class, 'receiptView'])->name('receipt.view');
        Route::get('/receipts/{invoice}/download', [PosController::class, 'receiptDownload'])->name('receipt.download');
        Route::get('/receipts/{invoice}/print', [PosController::class, 'receiptPrint'])->name('receipt.print');
        // Keep legacy receipt links working for existing bookmarks and notifications.
        Route::get('/sales/{order}/receipt/pdf', [PosController::class, 'receiptPdf'])->name('receipt.pdf');
        Route::get('/sales/{order}/receipt', [PosController::class, 'receipt'])->name('receipt');
        Route::post('/hold', [PosController::class, 'hold'])->name('hold');
        Route::put('/draft', [PosController::class, 'syncDraft'])->name('draft.sync');
        Route::post('/draft/clear', [PosController::class, 'clearDraft'])->name('draft.clear');
        Route::post('/resume/{heldSale}', [PosController::class, 'resume'])->name('resume');
    });
    // New-sale creation is unavailable while sales remain a history and
    // management module.
    Route::get('/sales/create', fn() => redirect()->route('business.sales.index')->with('info', 'New sales are not available from this module.'))->name('sales.create');
    Route::post('/sales', fn() => redirect()->route('business.sales.index')->with('info', 'New sales are not available from this module.'))->name('sales.store');
    Route::get('/sales/returns', [\App\Http\Controllers\Business\SalesReturnController::class, 'index'])->name('sales.returns.index')->middleware(['business.permission:Sales Returns', 'business.permission:Sales']);
    Route::get('/sales/returns/create', [\App\Http\Controllers\Business\SalesReturnController::class, 'create'])->name('sales.returns.create')->middleware(['business.permission:Sales Returns', 'business.permission:Sales']);
    Route::post('/sales/returns/start', [\App\Http\Controllers\Business\SalesReturnController::class, 'start'])->name('sales.returns.start')->middleware(['business.permission:Sales Returns', 'business.permission:Sales']);
    Route::get('/sales/returns/process/{order}', [\App\Http\Controllers\Business\SalesReturnController::class, 'process'])->name('sales.returns.process')->middleware(['business.permission:Sales Returns', 'business.permission:Sales']);
    Route::post('/sales/returns/process/{order}', [\App\Http\Controllers\Business\SalesReturnController::class, 'store'])->name('sales.returns.store')->middleware(['business.permission:Sales Returns', 'business.permission:Sales']);
    Route::get('/sales/returns/records/{salesReturn}', [\App\Http\Controllers\Business\SalesReturnController::class, 'show'])->name('sales.returns.show')->middleware(['business.permission:Sales Returns', 'business.permission:Sales']);
    Route::get('/sales/returns/records/{salesReturn}/edit', [\App\Http\Controllers\Business\SalesReturnController::class, 'edit'])->name('sales.returns.edit')->middleware(['business.permission:Sales Returns', 'business.permission:Sales']);
    Route::get('/sales/payments', [PaymentController::class, 'index'])->name('sales.payments.index')->middleware('business.permission:Sales');
    Route::post('/sales/payments', [PaymentController::class, 'store'])->name('sales.payments.store')->middleware(['business.permission:Sales', 'company.permission:sales.payments']);
    Route::get('/sales/invoices', [InvoiceController::class, 'index'])->name('sales.invoices.index')->middleware('business.permission:Sales');
    Route::get('/sales/invoices/{order}', [InvoiceController::class, 'show'])->name('sales.invoices.show')->middleware('business.permission:Sales');
    Route::get('/sales/invoices/{order}/pdf', [InvoiceController::class, 'pdf'])->name('sales.invoices.pdf')->middleware('business.permission:Sales');
    Route::get('/sales/invoices/{order}/pdf/download', [InvoiceController::class, 'downloadPdf'])->name('sales.invoices.pdf.download')->middleware('business.permission:Sales');
    Route::patch('/sales/invoice-records/{invoice}', [InvoiceController::class, 'update'])->name('sales.invoices.update')->middleware('business.permission:Sales');
    Route::patch('/sales/invoice-records/{invoice}/issue', [InvoiceController::class, 'issue'])->name('sales.invoices.issue')->middleware('business.permission:Sales');
    Route::patch('/sales/invoice-records/{invoice}/void', [InvoiceController::class, 'void'])->name('sales.invoices.void')->middleware('business.permission:Sales');
    Route::patch('/sales/invoice-records/{invoice}/reissue', [InvoiceController::class, 'reissue'])->name('sales.invoices.reissue')->middleware('business.permission:Sales');
    Route::post('/sales/invoice-records/{invoice}/credit-notes', [InvoiceController::class, 'creditNote'])->name('sales.invoices.credit-notes.store')->middleware('business.permission:Sales');
    Route::get('/sales/{order}/edit', [OrderController::class, 'edit'])->name('sales.edit')->middleware('business.permission:Sales');
    Route::put('/sales/{order}', [OrderController::class, 'update'])->name('sales.update')->middleware(['business.permission:Sales', 'company.permission:sales.edit']);
    Route::patch('/sales/{order}/cancel', [OrderController::class, 'cancel'])->name('sales.cancel')->middleware(['business.permission:Sales', 'company.permission:sales.update_status']);
    Route::patch('/sales/{order}/void', [OrderController::class, 'void'])->name('sales.void')->middleware('business.permission:Sales');
    Route::delete('/sales/{order}', [OrderController::class, 'destroy'])->name('sales.destroy')->middleware('business.permission:Sales');
    Route::get('/sales/{order}', [OrderController::class, 'show'])->name('sales.show')->middleware('business.permission:Sales');
    Route::patch('/sales/{order}/status', [OrderController::class, 'updateStatus'])->name('sales.status')->middleware(['business.permission:Sales', 'company.permission:sales.update_status']);

    // Legacy order URLs remain available as safe redirects or action aliases.
    Route::get('/orders', fn() => redirect()->route('business.sales.index'))->name('orders.index')->middleware('business.permission:Sales');
    Route::get('/orders/create', fn() => redirect()->route('business.sales.index')->with('info', 'New sales are not available from this module.'))->name('orders.create');
    Route::post('/orders', fn() => redirect()->route('business.sales.index')->with('info', 'New sales are not available from this module.'))->name('orders.store');
    Route::get('/orders/{order}/edit', fn($order) => redirect()->route('business.sales.edit', $order))->name('orders.edit')->middleware('business.permission:Sales');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->name('orders.update')->middleware(['business.permission:Sales', 'company.permission:sales.edit']);
    Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel')->middleware(['business.permission:Sales', 'company.permission:sales.update_status']);
    Route::patch('/orders/{order}/void', [OrderController::class, 'void'])->name('orders.void')->middleware('business.permission:Sales');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy')->middleware('business.permission:Sales');
    Route::get('/orders/{order}', fn($order) => redirect()->route('business.sales.show', $order))->name('orders.show')->middleware('business.permission:Sales');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status')->middleware(['business.permission:Sales', 'company.permission:sales.update_status']);
    Route::get('/payments', fn() => redirect()->route('business.sales.payments.index'))->name('payments')->middleware('business.permission:Sales');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store')->middleware(['business.permission:Sales', 'company.permission:sales.payments']);
    Route::get('/khata', [KhataController::class, 'index'])->name('khata')->middleware('business.permission:Accounting');
    Route::post('/khata/journal-entries', [KhataController::class, 'storeJournal'])->name('khata.journal.store')->middleware('business.permission:Accounting');
    Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries')->middleware('business.permission:Deliveries');
    Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show')->middleware('business.permission:Deliveries');
    Route::patch('/deliveries/{delivery}', [DeliveryController::class, 'update'])->name('deliveries.update')->middleware('business.permission:Deliveries');
    Route::patch('/deliveries/{delivery}/start', [DeliveryController::class, 'start'])->name('deliveries.start')->middleware('business.permission:Deliveries');
    Route::patch('/deliveries/{delivery}/proof', [DeliveryController::class, 'uploadProof'])->name('deliveries.proof')->middleware('business.permission:Deliveries');
    Route::patch('/deliveries/{delivery}/deliver', [DeliveryController::class, 'deliver'])->name('deliveries.deliver')->middleware('business.permission:Deliveries');
    Route::patch('/deliveries/{delivery}/fail', [DeliveryController::class, 'fail'])->name('deliveries.fail')->middleware('business.permission:Deliveries');
    Route::post('/deliveries/{delivery}/collection', [DeliveryController::class, 'recordCollection'])->name('deliveries.collection')->middleware('business.permission:Deliveries');
    Route::patch('/deliveries/{delivery}/reopen', [DeliveryController::class, 'reopen'])->name('deliveries.reopen')->middleware('business.permission:Deliveries');
    Route::patch('/deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])->name('deliveries.cancel')->middleware('business.permission:Deliveries');
    Route::get('/deliveries/{delivery}/sheet', [DeliveryController::class, 'sheet'])->name('deliveries.sheet')->middleware('business.permission:Deliveries');
    Route::get('/invoices', fn() => redirect()->route('business.sales.invoices.index'))->name('invoices.index')->middleware('business.permission:Sales');
    Route::get('/invoices/{order}', fn($order) => redirect()->route('business.sales.invoices.show', $order))->name('invoices.show')->middleware('business.permission:Sales');
    Route::get('/invoices/{order}/pdf', fn($order) => redirect()->route('business.sales.invoices.pdf', $order))->name('invoices.pdf')->middleware('business.permission:Sales');
    Route::get('/invoices/{order}/pdf/download', fn($order) => redirect()->route('business.sales.invoices.pdf.download', $order))->name('invoices.pdf.download')->middleware('business.permission:Sales');
    Route::patch('/invoice-records/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update')->middleware('business.permission:Sales');
    Route::patch('/invoice-records/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue')->middleware('business.permission:Sales');
    Route::patch('/invoice-records/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void')->middleware('business.permission:Sales');
    Route::patch('/invoice-records/{invoice}/reissue', [InvoiceController::class, 'reissue'])->name('invoices.reissue')->middleware('business.permission:Sales');
    Route::post('/invoice-records/{invoice}/credit-notes', [InvoiceController::class, 'creditNote'])->name('invoices.credit-notes.store')->middleware('business.permission:Sales');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store')->middleware('business.permission:Expenses');
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index')->middleware('business.permission:Expenses');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy')->middleware('business.permission:Expenses');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports')->middleware('business.permission:Reports');
    Route::get('/reports/end-of-day', [EndOfDayReportController::class, 'index'])->name('reports.end-of-day')->middleware('business.permission:Reports');
    Route::get('/reports/end-of-day/pdf', [EndOfDayReportController::class, 'pdf'])->name('reports.end-of-day.pdf')->middleware('business.permission:Reports');
    Route::get('/reports/customer-aging', [\App\Http\Controllers\Business\AgingReportController::class, 'customers'])->name('reports.customer-aging')->middleware('business.permission:Reports');
    Route::get('/reports/customer-aging/pdf', [\App\Http\Controllers\Business\AgingReportController::class, 'customerPdf'])->name('reports.customer-aging.pdf')->middleware('business.permission:Reports');
    Route::get('/reports/customer-aging/{customer}', [\App\Http\Controllers\Business\AgingReportController::class, 'customerDetails'])->name('reports.customer-aging.show')->middleware('business.permission:Reports');
    Route::get('/reports/supplier-aging', [\App\Http\Controllers\Business\AgingReportController::class, 'suppliers'])->name('reports.supplier-aging')->middleware('business.permission:Reports');
    Route::get('/reports/supplier-aging/pdf', [\App\Http\Controllers\Business\AgingReportController::class, 'supplierPdf'])->name('reports.supplier-aging.pdf')->middleware('business.permission:Reports');
    Route::get('/reports/supplier-aging/{supplier}', [\App\Http\Controllers\Business\AgingReportController::class, 'supplierDetails'])->name('reports.supplier-aging.show')->middleware('business.permission:Reports');
    Route::get('/reports/stock-movement-analytics', [StockMovementAnalyticsController::class, 'index'])->name('reports.stock-movement-analytics')->middleware('business.permission:Reports');
    Route::get('/reports/stock-movement-analytics/pdf', [StockMovementAnalyticsController::class, 'pdf'])->name('reports.stock-movement-analytics.pdf')->middleware('business.permission:Reports');
    Route::get('/reports/product-performance', [ProductPerformanceController::class, 'index'])->name('reports.product-performance')->middleware('business.permission:Reports');
    Route::get('/reports/product-performance/pdf', [ProductPerformanceController::class, 'pdf'])->name('reports.product-performance.pdf')->middleware('business.permission:Reports');
    Route::get('/reports/product-performance/{product}', [ProductPerformanceController::class, 'show'])->name('reports.product-performance.show')->middleware('business.permission:Reports');
    Route::get('/reports/{type}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf')->middleware('business.permission:Reports');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index')->middleware('business.permission:Audit Logs');
    Route::get('/audit-logs/live', [AuditLogController::class, 'live'])->name('audit-logs.live')->middleware('business.permission:Audit Logs');
    Route::get('/audit-logs/export/csv', [AuditLogController::class, 'exportCsv'])->name('audit-logs.export.csv')->middleware('business.permission:Audit Logs');
    Route::get('/audit-logs/export/pdf', [AuditLogController::class, 'exportPdf'])->name('audit-logs.export.pdf')->middleware('business.permission:Audit Logs');
    Route::get('/staff', [StaffController::class, 'index'])->name('staff');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::get('/staff/{staff}', [StaffController::class, 'show'])->name('staff.show');
    Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    Route::patch('/staff/{staff}/status', [StaffController::class, 'updateStatus'])->name('staff.status');
    Route::patch('/staff/{staff}/reset-password', [StaffController::class, 'resetPassword'])->name('staff.reset-password');
    Route::patch('/staff/{staff}/archive', [StaffController::class, 'archive'])->name('staff.archive');
    Route::patch('/staff/{staff}/restore', [StaffController::class, 'restore'])->name('staff.restore');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings/business', [SettingsController::class, 'updateBusiness'])->name('settings.business');
    Route::post('/settings/business/protected-details-request', [SettingsController::class, 'requestProtectedBusinessDetailsChange'])->name('settings.protected-details-request');
    Route::patch('/settings/logo', [SettingsController::class, 'updateLogo'])->name('settings.logo');
    Route::get('/settings/receipt-footer', [SettingsController::class, 'editDocumentFooter'])->name('settings.document-footer.edit');
    Route::put('/settings/receipt-footer', [SettingsController::class, 'updateDocumentFooter'])->name('settings.document-footer.update');
    Route::post('/settings/receipt-footer/requests', [\App\Http\Controllers\Business\FooterChangeRequestController::class, 'store'])->name('settings.document-footer.requests.store');
    Route::patch('/settings/receipt-footer/requests/{footerChangeRequest}/cancel', [\App\Http\Controllers\Business\FooterChangeRequestController::class, 'cancel'])->name('settings.document-footer.requests.cancel');
});

Route::prefix('staff')->name('staff.')->middleware(['auth', 'role:custom_staff', 'business.approved', 'track.activity'])->group(function () {
    // Legacy staff dashboard URL. Do not require Dashboard permission here:
    // staff can validly be assigned an operational module without the optional
    // dashboard module. The shared resolver safely sends them to Dashboard
    // when granted, otherwise to their first effective business permission.
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');
});

Route::prefix('retailer')->name('retailer.')->middleware(['auth', 'role:retailer'])->group(function () {
    Route::get('/dashboard', [RetailerController::class, 'dashboard'])->name('dashboard');
    Route::get('/products', [RetailerController::class, 'products'])->name('products');
    Route::get('/cart', [RetailerController::class, 'cart'])->name('cart');
    Route::post('/cart/order', [RetailerController::class, 'placeOrder'])->name('cart.order');
    Route::get('/orders', [RetailerController::class, 'orders'])->name('orders');
    Route::get('/credit-balance', [RetailerController::class, 'creditBalance'])->name('credit-balance');
});
