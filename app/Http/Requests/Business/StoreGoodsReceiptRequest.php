<?php

namespace App\Http\Requests\Business;

use App\Services\CompanyPermissionService;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CompanyPermissionService::class)->allowsUser($this->user(), 'purchases.receive');
    }

    public function rules(): array
    {
        return [
            'submission_token' => ['required', 'uuid'],
            'received_at' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.purchase_item_id' => ['required', 'integer', 'distinct'],
            'items.*.accepted_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.rejected_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.batches' => ['nullable', 'array', 'max:50'],
            'items.*.batches.*.batch_number' => ['nullable', 'string', 'max:120'],
            'items.*.batches.*.quantity' => ['nullable', 'numeric', 'min:0.001'],
            'items.*.batches.*.manufacturing_date' => ['nullable', 'date'],
            'items.*.batches.*.expiry_date' => ['nullable', 'date'],
        ];
    }
}
