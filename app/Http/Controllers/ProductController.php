<?php

namespace App\Http\Controllers;

use App\Category;
use App\Http\Controllers\helpers\ProductHelper;
use App\Option;
use App\Product;
use App\ProductDescription;
use App\ProductToCategory;
use App\User;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private $helper;

    public function __construct()
    {
        $this->helper = new ProductHelper();
    }
    /**
     * funciton - fetch all products 1. grouped by category 2. with full details (choices,options)
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        # read input from request
        $language_id = $request->input('language_id', 2);
        $status = $request->input('product_status', 0);
        $search_string = $request->input('search_string', '');
        $calledFrom = $request->input('called_from', 'client');

        $sales_group_id = $calledFrom === 'client' ? $request->input('sales_group_id', 0) : 0;
        # read user_group_id from token
        $user_group_id = 0;
        $token = $request->bearerToken();
        $user = User::where("api_token", $token)->first();
        if ($user) {
            $user_group_id = $user->user_group_id;
        }
        # call function & create response Object
        $responseData = $this->helper->getProductsList($language_id, $status, $search_string, $user_group_id, $sales_group_id);

        # return response
        return response()->json($responseData, 200);
    }

    /**
     * function - create new product
     * @param Request
     * @return Response new product json just created
     */
    public function store(Request $request)
    {
        // Todo:: validate $request
        $errors = array();

        $status = 1;
        $product = json_decode(json_encode($request->product));

        if (count($errors) > 0) {
            return response()->json(compact('errors'), 422);
        }

        $category = json_decode(json_encode($request->category));
        $category_id = $category->category_id;

        //2. create oc_product
        $newProduct = Product::create([
            'price' => $product->price,
            'quantity' => isset($product->quantity) ? $product->quantity : 999,
            "sort_order" => $product->sort_order,
            'date_available' => isset($product->date_available) ? $product->date_available : '2018-12-12',
            'location' => isset($product->location_id) ? $product->location_id : 1,
        ]);

        if (isset($product->points)) {
            $newProduct->points = $product->points;
            $newProduct->save();
        }

        $product_id = $newProduct->product_id;

        if ($request->get("file")) {
            $image = $request->get("file");
            $name = "$product_id.jpeg";
            \Image::make($request->get('file'))->save(public_path('images/products/') . $name);
            $newProduct->image = "/images/products/$name";
        }
        // $path = public_path('images/products/');
        // return response()->json(compact("path"),200);
        $newProduct->save();

        //3. create oc_product_description [multiple descriptions should be created, as user may entry all names for different languages]

        $descriptionCn = ProductDescription::create(['product_id' => $product_id, 'language_id' => 2, 'name' => $product->chinese_name]);
        $descriptionEn = ProductDescription::create(['product_id' => $product_id, 'language_id' => 1, 'name' => $product->english_name]);

        ProductToCategory::create(['product_id' => $product_id, "category_id" => $category_id]);

        # prepare response object
        $search_string = isset($request->search_string) ? $request->search_string : "";
        $status = isset($request->status) ? $request->status : 0;
        $language_id = isset($request->language_id) ? $request->language_id : 2;
        $user_group_id = 2;

        $products = $this->helper->getProductsList($language_id, $status, $search_string, $user_group_id, 2, 0);
        return response()->json(compact("products"), 201);
    }

    /**
     * update product
     * @param Request $request body
     * @param Integer $product_id
     */
    public function update(Request $request, $product_id)
    {
        //1. validation

        $errors = array();
        // $errors = $this->validateRequest($request);
        $request->product = json_decode(json_encode($request->product));
        $search_string = isset($request->search_string) ? $request->search_string : "";

        $reqCategory = json_decode(json_encode($request->category));

        $productToCategory = ProductToCategory::where('product_id',$product_id)->first();
        $productToCategory->delete();
        ProductToCategory::create(['product_id'=>$product_id,'category_id'=>$reqCategory->category_id]);



        if (!is_numeric($product_id) || !is_integer($product_id + 0)) {
            $errors['product_id'] = ['The product id field is required.'];

        } else {
            $product = Product::find($product_id);
            if ($product === null) {
                $errors['product'] = ['The product is not found.'];
            }
        }
        if (count($errors) > 0) {
            return response()->json(compact('errors'), 422);
        }

        // update product and prepare the response body
        //2. update oc_product
        $product = Product::find($product_id);
        $product->price = $request->product->price;
        $product->sort_order = $request->product->sort_order;
        // $product->points = $request->product->points;
        // $product->location = $request->product->location;
        // $product->date_available = $request->product->date_available;

        if ($request->location_id) {
            $product->location = $request->location_id;
        }

        //How To:: upload image React && Laravel
        if ($request->get("file")) {
            $image = $request->get("file");
            $name = "$product_id.jpeg";
            \Image::make($request->get('file'))->save(public_path('images/products/') . $name);
            $product->image = "/images/products/$name";
        }

        $product->save();

        //3. update oc_product_description [multiple descriptions should be created, as user may update all names for different languages]
        $cn_des = ProductDescription::where("product_id", $product_id)->where("language_id", 2)->first();
        if ($cn_des === null) {} else {
            $cn_des->name = $request->product->chinese_name;
            $cn_des->save();
        }
        $en_des = ProductDescription::where("product_id", $product_id)->where("language_id", 1)->first();
        if ($en_des !== null) {
            $en_des->name = $request->product->english_name;
            $en_des->save();
        }

        $status = isset($request->status) ? $request->status : 0;
        $language_id = isset($request->language_id) ? $request->language_id : 2;
        $response_array = $this->helper->getProductsList($language_id, $status, $search_string, 2, 0);

        return response()->json($response_array, 200);

    }

    /**
     * show single product according to product_id
     * @param Integer Product_id
     * @return Response product with details
     */
    public function show(Request $request, $product_id)
    {
        $language_id = isset($request->language_id) ? $request->language_id : 2;

        $responseData = $this->helper->getSingleProduct($language_id, $product_id);
        //3. return response
        return response()->json($responseData, 200);
    }

    /**
     * function - partrial update product values
     *
     * @param Request $request
     * @param Integer $product_id
     * @return Response<Array<Product>> $response_array
     */
    public function patch(Request $request, $product_id)
    {
        # read input
        $language_id = isset($request->language_id) ? $request->language_id : 2;
        $search_string = isset($request->search_string) ? $request->search_string : "";
        $property = isset($request->property) ? $request->property : "status";
        $product = json_decode(json_encode($request->product));
        $status = $product->status === 1 ? 0 : 1;

        # call function
        switch ($property) {
            case 'status':
                $this->helper->updateProductStatus($request, $product_id);
                break;
            default:
                # code...
                break;
        }

        # make return response object
        $response_array = $this->helper->getProductsList($language_id, $status, $search_string, 2, 0);

        return response()->json($response_array, 200);
    }

}
