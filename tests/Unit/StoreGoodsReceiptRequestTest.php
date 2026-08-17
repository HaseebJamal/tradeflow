<?php

namespace Tests\Unit;

use App\Http\Requests\Business\StoreGoodsReceiptRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreGoodsReceiptRequestTest extends TestCase
{
    public function test_receipt_quantities_require_non_negative_whole_numbers(): void
    {
        $rules = (new StoreGoodsReceiptRequest())->rules();

        $valid = Validator::make($this->payload(['accepted_quantity' => '5', 'damaged_quantity' => '0', 'rejected_quantity' => '0']), $rules);
        $decimal = Validator::make($this->payload(['accepted_quantity' => '5.00', 'damaged_quantity' => '0', 'rejected_quantity' => '0']), $rules);
        $negative = Validator::make($this->payload(['accepted_quantity' => '-1', 'damaged_quantity' => '0', 'rejected_quantity' => '0']), $rules);

        $this->assertFalse($valid->fails());
        $this->assertTrue($decimal->fails());
        $this->assertTrue($negative->fails());
    }

    public function test_a_purchase_line_cannot_be_processed_twice_in_one_receipt(): void
    {
        $rules = (new StoreGoodsReceiptRequest())->rules();
        $payload = $this->payload(['accepted_quantity' => '1', 'damaged_quantity' => '0', 'rejected_quantity' => '0']);
        $payload['items'][] = [
            'purchase_item_id' => 1,
            'accepted_quantity' => '1',
            'damaged_quantity' => '0',
            'rejected_quantity' => '0',
        ];

        $validator = Validator::make($payload, $rules);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('items.0.purchase_item_id'));
        $this->assertTrue($validator->errors()->has('items.1.purchase_item_id'));
    }

    private function payload(array $quantities): array
    {
        return [
            'submission_token' => '6d7f0289-ec04-4d8e-a1e4-1677119cda10',
            'items' => [[
                'purchase_item_id' => 1,
                ...$quantities,
            ]],
        ];
    }
}
