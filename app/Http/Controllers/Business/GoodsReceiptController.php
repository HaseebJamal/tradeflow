<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\StoreGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\Purchase;
use App\Services\BusinessActivityService;
use App\Services\CompanyPermissionService;
use App\Services\PurchaseReceivingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private PurchaseReceivingService $receiving,
        private BusinessActivityService $activity,
    ) {}

    public function create(Request $request, Purchase $purchase)
    {
        $purchase = $this->scoped($purchase, $request);
        abort_unless(app(CompanyPermissionService::class)->allowsUser($request->user(), 'purchases.receive'), 403);
        abort_if(in_array($purchase->status, ['Draft', 'Cancelled'], true) || in_array($purchase->receiving_status, ['Returned', 'Fully Received'], true), 422, 'This purchase cannot receive more goods.');

        return view('business.purchases.receiving', [
            'purchase' => $purchase->load(['supplier', 'items.product', 'returns.items']),
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function store(StoreGoodsReceiptRequest $request, Purchase $purchase)
    {
        $purchase = $this->scoped($purchase, $request);
        $data = $request->validated();
        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('goods-receipts/'.$purchase->business_id, 'public');
        }
        $receipt = $this->receiving->record($purchase, $data, $request->user());
        $this->activity->record($purchase->business_id, 'Purchases', 'Goods receipt '.$receipt->grn_number.' recorded for '.$purchase->purchase_number, $receipt->id, null, [
            'purchase_id' => $purchase->id,
            'grn_number' => $receipt->grn_number,
            'receiving_status' => $purchase->fresh()->receiving_status,
        ]);

        return redirect()->route('business.purchases.show', $purchase)->with('success', 'Goods receipt '.$receipt->grn_number.' recorded successfully.');
    }

    public function show(Request $request, GoodsReceipt $goodsReceipt)
    {
        abort_unless($goodsReceipt->business_id === (int) $request->user()->business_id, 404);
        abort_unless(app(CompanyPermissionService::class)->allowsUser($request->user(), 'purchases.receive'), 403);
        return view('business.purchases.goods-receipt', ['receipt' => $goodsReceipt->load(['purchase', 'supplier', 'creator', 'items.product'])]);
    }

    private function scoped(Purchase $purchase, Request $request): Purchase
    {
        abort_unless($purchase->business_id === (int) $request->user()->business_id, 404);
        return $purchase;
    }
}
