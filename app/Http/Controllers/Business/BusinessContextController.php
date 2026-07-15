<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\CompanyPermissionService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class BusinessContextController extends Controller
{
    public function profile(Request $request)
    {
        return view('business.context-profile', [
            'business' => $this->business($request)->loadMissing('owner'),
        ]);
    }

    public function notifications(Request $request)
    {
        $business = $this->business($request)->loadMissing('owner');
        abort_unless(app(CompanyPermissionService::class)->allowsUser($request->user(), 'notifications.view', $business), 403);
        $notifications = $business->owner
            ? $business->owner->notifications()->latest()->paginate(20)
            : new LengthAwarePaginator([], 0, 20);

        return view('auth.notifications', [
            'notifications' => $notifications,
            'business' => $business,
        ]);
    }

    private function business(Request $request): Business
    {
        abort_unless($request->user()?->role === 'super_admin', 403);

        $business = $request->attributes->get('super_admin_business_context');
        abort_unless($business instanceof Business, 403);

        return $business;
    }
}
