<?php

namespace Tests\Unit;

use App\Http\Requests\Business\StoreOrUpdateProductRequest;
use App\Http\Requests\Business\StoreProductsRequest;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductSellingPricePolicy;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProductSellingPricePolicyTest extends TestCase
{
    public function test_both_selling_prices_must_be_strictly_greater_than_purchase_price(): void
    {
        $policy = app(ProductSellingPricePolicy::class);

        $this->assertSame([
            'retail_price' => 'Retail Selling Price must be greater than Purchase Price.',
            'wholesale_price' => 'Wholesale Selling Price must be greater than Purchase Price.',
        ], $policy->violations([
            'retail_price' => 450,
            'wholesale_price' => 400,
        ], 450));

        $this->assertSame([], $policy->violations([
            'retail_price' => 500,
            'wholesale_price' => 480,
        ], 450));
    }

    public function test_price_comparisons_are_skipped_without_an_accepted_purchase_price(): void
    {
        $policy = app(ProductSellingPricePolicy::class);

        $this->assertSame([], $policy->violations([
            'retail_price' => 0,
            'wholesale_price' => 0,
        ], null));
    }

    public function test_product_uses_the_receipt_derived_purchase_price_for_attention(): void
    {
        $product = new Product([
            'purchase_cost' => 350,
            'average_purchase_price' => 400,
            'latest_purchase_price' => 450,
            'retail_price' => 500,
            'wholesale_price' => 450,
        ]);
        $product->setRelation('acceptedGoodsReceiptItems', collect([
            new GoodsReceiptItem(['accepted_quantity' => 1]),
        ]));

        $this->assertSame(450.0, $product->currentPurchasePrice());
        $this->assertTrue($product->hasPricingAttention());
    }

    public function test_edit_request_cannot_bypass_the_purchase_price_rule(): void
    {
        $product = new Product([
            'id' => 44,
            'business_id' => 8,
            'latest_purchase_price' => 450,
        ]);
        $product->setRelation('acceptedGoodsReceiptItems', collect([
            new GoodsReceiptItem(['accepted_quantity' => 1]),
        ]));
        $request = StoreOrUpdateProductRequest::create('/business/products/44', 'PUT', [
            'retail_price' => 450,
            'wholesale_price' => 480,
        ]);
        $request->setUserResolver(fn () => new User(['business_id' => 8]));
        $route = new Route(['PUT'], '/business/products/{product}', []);
        $route->bind($request);
        $route->setParameter('product', $product);
        $request->setRouteResolver(fn () => $route);

        $validator = Validator::make($request->all(), []);
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Retail Selling Price must be greater than Purchase Price.',
            $validator->errors()->first('retail_price'),
        );
        $this->assertFalse($validator->errors()->has('wholesale_price'));
    }

    public function test_edit_request_skips_comparison_when_no_goods_have_been_accepted(): void
    {
        $product = new Product([
            'id' => 44,
            'business_id' => 8,
            // A legacy/default cost alone is not valid purchase evidence.
            'latest_purchase_price' => 450,
        ]);
        $product->setRelation('acceptedGoodsReceiptItems', collect());
        $request = StoreOrUpdateProductRequest::create('/business/products/44', 'PUT', [
            'retail_price' => 0,
            'wholesale_price' => 0,
        ]);
        $request->setUserResolver(fn () => new User(['business_id' => 8]));
        $route = new Route(['PUT'], '/business/products/{product}', []);
        $route->bind($request);
        $route->setParameter('product', $product);
        $request->setRouteResolver(fn () => $route);

        $validator = Validator::make($request->all(), []);
        $request->withValidator($validator);

        $this->assertFalse($validator->fails());
    }

    public function test_new_product_rows_do_not_compare_selling_prices_to_default_zero(): void
    {
        $request = StoreProductsRequest::create('/business/products', 'POST', [
            'products' => [[
                'product_name' => 'New product',
                'retail_price' => 0,
                'wholesale_price' => 0,
            ]],
        ]);
        $request->setUserResolver(fn () => new User(['business_id' => 8]));

        $validator = Validator::make($request->all(), []);
        $request->withValidator($validator);

        $this->assertFalse($validator->fails());
    }
}
