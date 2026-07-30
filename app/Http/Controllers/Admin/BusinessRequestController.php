<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BusinessRequestIndexService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessRequestController extends Controller
{
    public function index(Request $request, BusinessRequestIndexService $requests)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(BusinessRequestIndexService::SOURCES)],
            'request_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', Rule::in($requests->requestTypes())],
            'status' => ['nullable', Rule::in(['Pending', 'Approved', 'Rejected', 'Cancelled', 'Changes Requested', 'Scheduled', 'Completed', 'Active', 'Applied'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'clear' => ['nullable', 'boolean'],
        ]);

        if (! $request->boolean('clear') && empty($filters['date_from']) && empty($filters['date_to'])) {
            $filters['date_from'] = today()->toDateString();
            $filters['date_to'] = today()->toDateString();
        }

        return view('super-admin.business-requests.index', [
            'requests' => $requests->paginate($filters),
            'filters' => $filters,
            'types' => $requests->requestTypes(),
        ]);
    }

    public function show(BusinessRequestIndexService $requests, string $source, int $requestId)
    {
        return response()->json($requests->detail($source, $requestId));
    }
}
