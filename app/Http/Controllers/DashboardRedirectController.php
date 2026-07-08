<?php

namespace App\Http\Controllers;

class DashboardRedirectController extends Controller
{
    public function __invoke()
    {
        return match (auth()->user()->role) {
            'super_admin' => redirect()->route('admin.dashboard'),
            'retailer' => redirect()->route('retailer.dashboard'),
            'manager' => redirect()->route('business.dashboard'),
            'sales_staff' => redirect()->route('business.orders.index'),
            'inventory_staff' => redirect()->route('business.inventory'),
            'accountant' => redirect()->route('business.payments'),
            'delivery_staff' => redirect()->route('business.deliveries'),
            default => redirect()->route('business.dashboard'),
        };
    }
}
