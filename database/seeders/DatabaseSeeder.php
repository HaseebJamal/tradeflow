<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\BusinessReport;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\KhataLedger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\PlatformSetting;
use App\Services\BarcodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::create(['name' => 'Super Admin', 'email' => 'admin@tradeflow.com', 'phone' => '03000000000', 'password' => Hash::make('password'), 'role' => 'super_admin', 'status' => 'active']);

        $owner = User::create(['name' => 'Ali Khan', 'email' => 'owner@tradeflow.test', 'phone' => '03001111111', 'password' => Hash::make('password'), 'role' => 'business_owner', 'status' => 'active']);
        $business = Business::create(['owner_id' => $owner->id, 'business_name' => 'Apex Foods', 'business_type' => 'Distributor', 'category' => 'Grocery', 'phone' => '042-0000000', 'address' => 'Model Town, Lahore', 'city' => 'Lahore', 'status' => 'Approved']);
        $owner->update(['business_id' => $business->id]);

        foreach ([
            ['Manager Demo', 'manager@tradeflow.test', 'manager'],
            ['Sales Demo', 'sales@tradeflow.test', 'sales_staff'],
            ['Inventory Demo', 'inventory@tradeflow.test', 'inventory_staff'],
            ['Accountant Demo', 'accountant@tradeflow.test', 'accountant'],
            ['Delivery Demo', 'delivery@tradeflow.test', 'delivery_staff'],
        ] as [$name, $email, $role]) {
            User::create(['name' => $name, 'email' => $email, 'phone' => '03002222222', 'password' => Hash::make('password'), 'role' => $role, 'status' => 'active', 'business_id' => $business->id]);
        }

        $retailer = User::create(['name' => 'Retail Mart', 'email' => 'retailer@tradeflow.test', 'phone' => '03003333333', 'password' => Hash::make('password'), 'role' => 'retailer', 'status' => 'active']);

        $plans = collect([
            ['name' => 'Basic', 'price' => 0, 'product_limit' => 100, 'staff_limit' => 3, 'order_limit' => 500],
            ['name' => 'Standard', 'price' => 4999, 'product_limit' => 1000, 'staff_limit' => 15, 'order_limit' => 5000],
            ['name' => 'Premium', 'price' => 12999, 'product_limit' => 10000, 'staff_limit' => 50, 'order_limit' => 50000],
        ])->map(fn ($plan) => SubscriptionPlan::create($plan));
        Subscription::create(['business_id' => $business->id, 'subscription_plan_id' => $plans[1]->id, 'starts_at' => now(), 'ends_at' => now()->addMonth(), 'status' => 'Active']);

        $category = Category::create(['business_id' => $business->id, 'name' => 'Grocery', 'type' => 'Product']);
        $products = collect([
            ['name' => 'Basmati Rice 25kg', 'wholesale_price' => 8200, 'retail_price' => 8800, 'stock_quantity' => 240],
            ['name' => 'Cooking Oil 5L', 'wholesale_price' => 11500, 'retail_price' => 12200, 'stock_quantity' => 95],
            ['name' => 'Tea Pack 950g', 'wholesale_price' => 9800, 'retail_price' => 10400, 'stock_quantity' => 8],
        ])->map(function ($item) use ($business, $category) {
            $product = Product::create($item + ['business_id' => $business->id, 'category_id' => $category->id, 'minimum_order_quantity' => 1, 'status' => 'Active']);
            app(BarcodeService::class)->assign($product);
            Inventory::create(['business_id' => $business->id, 'product_id' => $product->id, 'available_stock' => $product->stock_quantity, 'low_stock_alert' => 10]);
            return $product;
        });

        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Retail Mart', 'business_name' => 'Retail Mart', 'phone' => $retailer->phone, 'address' => 'Main Market', 'city' => 'Lahore', 'customer_type' => 'Retailer', 'credit_limit' => 150000, 'current_balance' => 82000, 'status' => 'Active']);

        $order = Order::create(['order_number' => 'ORD-2201', 'business_id' => $business->id, 'customer_id' => $customer->id, 'retailer_id' => $retailer->id, 'subtotal' => 82000, 'discount' => 0, 'total' => 82000, 'status' => 'Packing']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $products[0]->id, 'quantity' => 10, 'price' => 8200, 'total' => 82000]);
        KhataLedger::create(['customer_id' => $customer->id, 'order_id' => $order->id, 'type' => 'Debit', 'amount' => 82000, 'description' => 'Order ORD-2201', 'balance' => 82000]);
        Payment::create(['order_id' => $order->id, 'customer_id' => $customer->id, 'method' => 'Cash', 'amount' => 25000, 'payment_date' => now(), 'status' => 'Partial']);

        Expense::create(['business_id' => $business->id, 'category' => 'Transport', 'title' => 'Monthly deliveries', 'amount' => 45000, 'date' => now(), 'description' => 'Demo expense']);
        SupportTicket::create(['business_id' => $business->id, 'user_id' => $owner->id, 'subject' => 'Approval question', 'message' => 'Demo support ticket', 'priority' => 'Medium', 'status' => 'Open']);

        foreach (['Food', 'Beverages', 'Electronics', 'Medicine', 'Cosmetics', 'Mobile Accessories'] as $name) {
            Category::firstOrCreate(['business_id' => null, 'name' => $name], ['type' => 'Product', 'status' => 'Active']);
        }

        foreach ([['Pending Review', 7], ['Verified', 6], ['Rejected', 5]] as [$status, $month]) {
            BusinessReport::create([
                'business_id' => $business->id,
                'report_type' => 'Business Performance',
                'month' => $month,
                'year' => 2026,
                'total_sales' => 250000 + ($month * 10000),
                'total_orders' => 40 + $month,
                'total_expense' => 45000,
                'profit' => 205000 + ($month * 10000),
                'status' => $status,
            ]);
        }

        PlatformSetting::firstOrCreate([], ['company_name' => 'TradeFlow', 'support_email' => 'support@tradeflow.com', 'support_phone' => '+92 300 0000000', 'trial_days' => 14, 'default_plan_id' => $plans[0]->id, 'max_upload_size' => 2048]);
    }
}
