<?php
namespace App\Http\Controllers\helpers;

use App\Category;
use App\Product;
use App\ProductDiscount;
use App\ProductToCategory;
use App\SalesGroup;

class ProductHelper
{
    /**
     * helper function fetch all products list from DB
     *
     * @param integer $language_id
     * @param integer $status
     * @return Array
     */
    public function getProductsList($language_id, $status, $search_string, $user_group_id, $sales_group_id)
    {

        # find sales_group_id

        # fetch all productDiscounts.product_id, use for filter products
        if ($sales_group_id != 0) {
            $productIds = ProductDiscount::where('sales_group_id', $sales_group_id)->pluck('product_id')->toArray();
        }

        # fetch all categories, use for grouping products
        $categories = Category::where("status", 0)->orderBy("sort_order", 'desc')->get();

        $calledFrom = 'client'; # client or admin

        $responseData = [];

        foreach ($categories as $category) {
            $dto = [];
            $dto['category_id'] = $category->category_id;

            // find category for $language_id matching language
            $categoryDescription = $category->descriptions()->where('language_id', $language_id)->first();
            if ($categoryDescription === null) { // if no matching language record provide default validate value for it.
                $categoryDescription = $category->descriptions()->first();
            }
            $dto['name'] = $categoryDescription->name;

            $products = $category->products()->where("status", $status)->orderBy("sort_order", "desc")->with('discounts')->get();
            if ($sales_group_id != 0) {

                $products = $products->whereIn('product_id', $productIds);

            }

            foreach ($products as $product) {
                # deal with discount
                // Todo:: read $user_group_id from $reqeust;
                $user_group_id = 2;
                $discountInfo = self::makeDiscountInfo($product->discounts, $user_group_id);
                $product["discountPrice"] = $discountInfo["price"];
                $product["isDiscount"] = $discountInfo["status"];
                $product["discountQuantity"] = $discountInfo["quantity"];
                $product['product_discount_id'] = $discountInfo['id'];
                $product['discountMaxQuantity'] = $discountInfo['max_quantity'];

                // if (!$discountInfo["status"] && $sales_group_id != 0) {
                //     $products = $products->filter(function ($item) use ($product) {
                //         return $item->product_id !== $product->product_id;
                //     })->values();
                //     continue;
                // }

                # make product location
                $location = $product->location()->first();
                $product["store_name"] = $location->name;
                # make product name
                $productDescription = $product->descriptions()->where('language_id', $language_id)->first();
                if ($productDescription === null) {
                    $productDescription = $product->descriptions()->first();
                }
                $product['name'] = $productDescription->name;

                # make product image
                $image_path = config("app.baseurl") . $product["image"];

                if ($product["image"] === null || $product["image"] === "" || !file_exists($_SERVER['DOCUMENT_ROOT'] . $image_path)) {
                    $product["image"] = url('/') . '/images/products/default_product.jpeg';
                } else {

                    $product["image"] = url('/') . $product["image"];
                }

                if ($search_string !== "" && !(strpos($product['name'], $search_string) !== false)) {
                    $products = $products->filter(function ($item) use ($product) {
                        return $item->product_id !== $product->product_id;
                    })->values();
                    continue;
                }

                # bind options to product
                $options = array();
                $product['options'] = $options;
            }

            $dto['products'] = $products->values();
            array_push($responseData, $dto);
        }

        return $responseData;
    }

    /**
     * function - fetch single product by $language_id and $product_id
     *
     * @param Integer $language_id
     * @param Integer $product_id
     * @return Object<Product> $responseData
     */
    public function getSingleProduct($language_id, $product_id)
    {
        $responseData = array();
        //1. fetch product
        $product = Product::find($product_id);
        $product->store_name = $product->location()->first()->name;
        $product->image = url('/') . $product->image;
        $responseData['product'] = $product;

        //2. add details
        //2.1 descriptions
        $responseData['descriptions'] = $product->descriptions()->get();
        //2.2 category

        $category = Category::find(ProductToCategory::where("product_id", $product_id)->first()->category_id);
        $categoryDescription = $category->descriptions()->where("language_id", $language_id)->first();
        if ($categoryDescription === null) {
            $categoryDescription = $category->descriptions()->first();
        }
        $category["name"] = $categoryDescription->name;
        $responseData['category'] = $category;
        $responseData['discounts'] = $product->discounts()->where('status', 0)->get();
        # extra: generate end_date and start_date for each product_discount
        foreach ($responseData['discounts'] as $productDiscount) {
            $sales_group_id = $productDiscount->sales_group_id;
            $salesGroup = SalesGroup::find($sales_group_id);
            $productDiscount->date_start = $salesGroup->start_date;
            $productDiscount->date_end = $salesGroup->end_date;
        }
        //2.3 options
        // $responseData['options'] = $product->options()->get();
        // foreach ($responseData['options'] as $value) {
        //     $valueDescription = $value->optionDescriptions()->where("language_id", $language_id)->first();
        //     if ($valueDescription === null) {
        //         $valueDescription = $value->optionDescriptions()->first();
        //     }
        //     //2.3.1 option name

        //     $value["option_name"] = $valueDescription->name;
        //     //2.3.2 option values
        //     $productOptionValues = $value->optionValues()->get();
        //     foreach ($productOptionValues as $productOptionValue) {
        //         $productOptionValueDescription = $value->optionDescriptions()->where("language_id", $language_id)->first();
        //         if ($productOptionValueDescription === null) {
        //             $productOptionValueDescription = $value->optionDescriptions()->first();
        //         }

        //         $productOptionValue["option_value_name"] = $productOptionValueDescription->name;

        //     }
        //     $value["values"] = $productOptionValues;
        // }
        return $responseData;
    }

    /**
     * validate $request body isset() 😲 datatype 😲
     * @param Request $request body
     * @return Array errors array
     */
    public function validateRequest($request)
    {
        $errors = array();
        //1. validate incorrect category
        $category = Category::find($request->category_id);

        if ($category === null) {
            $errors['category'] = ['The category is not found.'];
            return $errors;
        }

        //2. validate requiration layer 1
        if (!isset($request->product)) {
            $errors['product'] = ['The product filed is required.'];
            return $errors;
        }

        //3. validate requiration layer 2
        $request->product = json_decode(json_encode($request->product));
        if (!isset($request->product->price) || !isset($request->product->quantity) || !isset($request->product->sku)) {

            if (!isset($request->product->price)) {
                $errors['product.price'] = ['The product.price field is required.'];
            }
            if (!isset($request->product->quantity)) {
                $errors['product.quantity'] = ['The product.quantity field is required.'];
            }

            if (!isset($request->product->sku)) {
                $errors['product.sku'] = ['The product.sku field is required.'];
            }

            return $errors;
        }

    }

    /**
     * function - switch product status between inactive and active
     *
     * @param Request $request
     * @param Integer $product_id
     * @return Void
     */
    public function updateProductStatus($request, $product_id)
    {
        $product = Product::find($product_id);
        $requestProduct = json_decode(json_encode($request->product));
        $product->status = $requestProduct->status;
        $product->save();
    }

    # self helper functions
    public function makeDiscountInfo($dataCollections, $user_group_id)
    {
        $dt = new \DateTime("now", new \DateTimeZone('Australia/Sydney'));
        $today = $dt->format("Y-m-d");
        $user_group_id = 2;
        $sql = $dataCollections
            ->where('customer_group_id', $user_group_id)
            ->where('quantity', '>=', '0')
            ->where('date_start', '<=', $today)
            ->where('date_end', '>=', $today);

        $discounts = $sql->all();

        if (count($discounts) > 0) {
            $result = $sql
                ->sortByDesc('priority')
                ->first();
            return array(
                "price" => $result->price,
                "quantity" => $result->quantity,
                'max_quantity' => $result->max_quantity,
                "status" => true,
                'id' => $result->product_discount_id,
            );
        }

        // return array("price" => $product["price"], "quantity" => 0, "status" => false);
    }

    public function createDiscount($request, $product_id)
    {
        $product = json_decode(json_encode($request->product));
        ProductDiscount::create([
            'product_id' => $product_id,
            'quantity' => $product->stock_status_id,
            'price' => $product->discountPrice,
            'date_start' => isset($product->date_start) ? $product->date_start : '1900-2-2',
            'date_end' => isset($product->date_end) ? $product->date_end : '2200-2-2',
        ]);

    }

}
