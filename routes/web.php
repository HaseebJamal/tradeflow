<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Business\BusinessDashboardController;
use App\Http\Controllers\Business\CustomerController;
use App\Http\Controllers\Business\DeliveryController;
use App\Http\Controllers\Business\ExpenseController;
use App\Http\Controllers\Business\InventoryController;
use App\Http\Controllers\Business\InvoiceController;
use App\Http\Controllers\Business\KhataController;
use App\Http\Controllers\Business\OrderController;
use App\Http\Controllers\Business\PaymentController;
use App\Http\Controllers\Business\ProductController;
use App\Http\Controllers\Business\ReportController;
use App\Http\Controllers\Business\StaffController;
use App\Http\Controllers\Business\StaffDashboardController;
use App\Http\Controllers\Business\SettingsController;
use App\Http\Controllers\BusinessOnboardingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Retailer\RetailerController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('public.home');
Route::view('/features', 'public.features')->name('public.features');
Route::view('/pricing', 'public.pricing')->name('public.pricing');
Route::view('/contact', 'public.contact')->name('public.contact');
Route::view('/privacy-security', 'public.privacy-security')->name('privacy.security');
Route::get('/subscribe/{plan}', function (string $plan) {
    abort_unless(in_array($plan, ['basic', 'standard', 'premium'], true), 404);
    return view('public.subscribe', ['plan' => $plan]);
})->name('subscribe.plan');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'forgotForm'])->name('password.request');
    Route::get('/otp-verification', [AuthController::class, 'otpForm'])->name('otp');
});

Route::get('/register-business', [BusinessOnboardingController::class, 'create'])->name('register.business');
Route::post('/register-business', [BusinessOnboardingController::class, 'store'])->name('register.business.store');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard.redirect');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::match(['post', 'put'], '/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:super_admin'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/businesses', [AdminController::class, 'businesses'])->name('businesses');
    Route::get('/businesses/{business}', [AdminController::class, 'businessShow'])->name('businesses.show');
    Route::patch('/businesses/{business}/status', [AdminController::class, 'updateStatus'])->name('businesses.status');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus'])->name('users.status');
    Route::get('/subscriptions', [AdminController::class, 'subscriptions'])->name('subscriptions');
    Route::post('/subscription-plans', [AdminController::class, 'storePlan'])->name('subscription-plans.store');
    Route::post('/subscriptions', [AdminController::class, 'activateSubscription'])->name('subscriptions.activate');
    Route::get('/support-tickets', [AdminController::class, 'supportTickets'])->name('support-tickets');
    Route::patch('/support-tickets/{ticket}', [AdminController::class, 'updateTicket'])->name('support-tickets.update');
    Route::get('/categories', [AdminController::class, 'categories'])->name('categories');
    Route::post('/categories', [AdminController::class, 'storeCategory'])->name('categories.store');
    Route::get('/payments', [AdminController::class, 'payments'])->name('payments');
    Route::get('/notifications', [AdminController::class, 'notifications'])->name('notifications');
    Route::post('/notifications', [AdminController::class, 'storeAnnouncement'])->name('notifications.store');
    Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('audit-logs');
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::put('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
    Route::get('/business-reports', [AdminController::class, 'businessReports'])->name('business-reports');
    Route::get('/business-reports/{business}', [AdminController::class, 'businessReportShow'])->name('business-reports.show');
    Route::post('/business-reports/{report}/approve', [AdminController::class, 'approveReport'])->name('business-reports.approve');
    Route::post('/business-reports/{report}/reject', [AdminController::class, 'rejectReport'])->name('business-reports.reject');
    Route::get('/business-reports/{report}/pdf', [AdminController::class, 'reportPdf'])->name('business-reports.pdf');
});

Route::prefix('business')->name('business.')->middleware(['auth', 'role:business_owner,manager,sales_staff,inventory_staff,accountant,delivery_staff', 'business.approved'])->group(function () {
    Route::get('/dashboard', BusinessDashboardController::class)->name('dashboard');
    Route::patch('/products/{product}/low-stock-alert', [ProductController::class, 'updateLowStockAlert'])->name('products.low-stock-alert')->middleware('business.permission:Products');
    Route::resource('products', ProductController::class)->middleware('business.permission:Products');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory')->middleware('business.permission:Inventory');
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust')->middleware('business.permission:Inventory');
    Route::patch('/inventory/{inventory}/alert', [InventoryController::class, 'updateAlert'])->name('inventory.alert')->middleware('business.permission:Inventory');
    Route::resource('customers', CustomerController::class)->only(['index', 'store', 'show', 'update'])->middleware('business.permission:Customers');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index')->middleware('business.permission:Orders');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create')->middleware('business.permission:Orders');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store')->middleware('business.permission:Orders');
    Route::post('/orders/{order}/assign-delivery', [OrderController::class, 'assignDelivery'])->name('orders.assignDelivery')->middleware('business.permission:Deliveries');
    Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit')->middleware('business.permission:Orders');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->name('orders.update')->middleware('business.permission:Orders');
    Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel')->middleware('business.permission:Orders');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy')->middleware('business.permission:Orders');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show')->middleware('business.permission:Orders');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status')->middleware('business.permission:Orders');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments')->middleware('business.permission:Payments');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store')->middleware('business.permission:Payments');
    Route::get('/khata', [KhataController::class, 'index'])->name('khata')->middleware('business.permission:Khata');
    Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries')->middleware('business.permission:Deliveries');
    Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show')->middleware('business.permission:Deliveries');
    Route::patch('/deliveries/{delivery}', [DeliveryController::class, 'update'])->name('deliveries.update')->middleware('business.permission:Deliveries');
    Route::patch('/deliveries/{delivery}/start', [DeliveryController::class, 'start'])->name('deliveries.start')->middleware('business.permission:Deliveries');
    Route::patch('/deliveries/{delivery}/deliver', [DeliveryController::class, 'deliver'])->name('deliveries.deliver')->middleware('business.permission:Deliveries');
    Route::patch('/deliveries/{delivery}/fail', [DeliveryController::class, 'fail'])->name('deliveries.fail')->middleware('business.permission:Deliveries');
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index')->middleware('business.permission:Invoices');
    Route::get('/invoices/{order}', [InvoiceController::class, 'show'])->name('invoices.show')->middleware('business.permission:Invoices');
    Route::get('/invoices/{order}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf')->middleware('business.permission:Invoices');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store')->middleware('business.permission:Expenses');
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index')->middleware('business.permission:Expenses');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy')->middleware('business.permission:Expenses');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports')->middleware('business.permission:Reports');
    Route::get('/reports/{type}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf')->middleware('business.permission:Reports');
    Route::get('/staff', [StaffController::class, 'index'])->name('staff')->middleware('role:business_owner');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store')->middleware('role:business_owner');
    Route::get('/staff/{staff}', [StaffController::class, 'show'])->name('staff.show')->middleware('role:business_owner');
    Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit')->middleware('role:business_owner');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update')->middleware('role:business_owner');
    Route::patch('/staff/{staff}/status', [StaffController::class, 'updateStatus'])->name('staff.status')->middleware('role:business_owner');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy')->middleware('role:business_owner');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings')->middleware('role:business_owner');
    Route::put('/settings/business', [SettingsController::class, 'updateBusiness'])->name('settings.business')->middleware('role:business_owner');
});

Route::prefix('staff')->name('staff.')->middleware(['auth', 'role:manager,sales_staff,inventory_staff,accountant,delivery_staff', 'business.approved'])->group(function () {
    Route::get('/dashboard', StaffDashboardController::class)->name('dashboard');
});

Route::prefix('retailer')->name('retailer.')->middleware(['auth', 'role:retailer'])->group(function () {
    Route::get('/dashboard', [RetailerController::class, 'dashboard'])->name('dashboard');
    Route::get('/products', [RetailerController::class, 'products'])->name('products');
    Route::get('/cart', [RetailerController::class, 'cart'])->name('cart');
    Route::post('/cart/order', [RetailerController::class, 'placeOrder'])->name('cart.order');
    Route::get('/orders', [RetailerController::class, 'orders'])->name('orders');
    Route::get('/credit-balance', [RetailerController::class, 'creditBalance'])->name('credit-balance');
});
