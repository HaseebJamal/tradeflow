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
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'integer'],
            'items.*.accepted_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.rejected_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
