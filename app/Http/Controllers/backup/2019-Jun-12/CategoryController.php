<?php

namespace App\Http\Controllers;

use App\Category;
use App\CategoryDescription;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * return all categories with selected language - if no description name in this language use default language as alternative
     * @param Integer $language_id
     * @return Response all categories
     *
     */
    public function index(Request $request)
    {

        $language_id = isset($request->language_id) ? $request->language_id : 2;

        $response_array = self::getCategoryList($language_id);
        return response()->json(['categories' => $response_array], 200);

    }
    /**
     * return single category format ['category_id','name']
     * @param Integer $language_id
     * @param Integer $category_id
     * @return Response select category with name
     */
    public function show(Request $request, $category_id)
    {

        $language_id = isset($request->language_id) ? $request->language_id : 2;

        $category = Category::find($category_id);
        $description = $category->descriptions()->where('language_id', 2)->first();
        $category["name"] = $description->name;
        $description = $category->descriptions()->where('language_id', 1)->first();
        $category["other_name"] = $description->name;
        return response()->json($category, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $validatedData = $request->validate([
            'chinese_name' => 'required',
            'english_name' => 'required',
        ]);

        $language_id = isset($request->language_id) ? $request->language_id : 2;

        $categoryDescriptions = CategoryDescription::where('name', $request->name)->get();
        if (count($categoryDescriptions) > 0) {
            return response()->json(['errors' => ['message' => "This category is already exists"]], 422);
        }

        $category = Category::create();
        if (isset($request->sort_order)) {
            $category->sort_order = $request->sort_order;

            $category->save();
        }
        if ($request->get("file")) {
            $image = $request->get("file");
            $name = "$category->category_id.jpeg";
            \Image::make($request->get('file'))->save(public_path('images/categories/') . $name);
            $category->image = "/images/categories/$name";
        }

        $category->save();

        $categoryDescription1 = CategoryDescription::create(['category_id' => $category->category_id, 'name' => $request->chinese_name, 'language_id' => 2]);
        $categoryDescription2 = CategoryDescription::create(['category_id' => $category->category_id, 'name' => $request->english_name, 'language_id' => 1]);

        $response_array = self::getCategoryList($language_id);

        return response()->json($response_array, 201);

    }

    public function update(Request $request, $category_id)
    {
        // $validatedData = $request->validate([
        //     'name' => 'required',
        //     'language_id' => 'required|integer',
        // ]);
        $language_id = isset($request->language_id) ? $request->language_id : 2;

        $category = Category::find($category_id);

        if (!$category) {
            return response()->json(['errors' => ['Messages' => 'This category can not be found.']], 400);
        }

        if ($request->get("file")) {
            $image = $request->get("file");
            $name = "$category->category_id.jpeg";
            \Image::make($request->get('file'))->save(public_path('images/categories/') . $name);
            $category->image = "/images/categories/$name";
        }

        if ($request->sort_order) {
            $category->sort_order = $request->sort_order;
        }

        $category->save();

        $categoryDescription1 = CategoryDescription::where("category_id", $category_id)->where("language_id", 1)->first();
        $categoryDescription1->name = $request->english_name;
        $categoryDescription1->save();

        $categoryDescription2 = CategoryDescription::where("category_id", $category_id)->where("language_id", 2)->first();
        $categoryDescription2->name = $request->chinese_name;
        $categoryDescription2->save();

        $response_array = self::getCategoryList($language_id);
        return response()->json($response_array, 200);
    }

    public function patch(Request $request, $category_id)
    {
        $language_id = isset($request->language_id) ? $request->language_id : 2;
// Category::destroy($category_id);
        $category = Category::find($category_id);
        $category->status = 0;
        $category->save();
// CategoryDescription::where("category_id", $category_id)->delete();

        $categories = self::getCategoryList($language_id);

        return response()->json($categories, 200);

    }

    public function getCategoryList($language_id)
    {
        $response_array = array();
        $categories = Category::orderBy("sort_order", "desc")->get();

        foreach ($categories as $category) {
            $item = array();

            $description = $category->descriptions()->where('language_id', $language_id)->first();
            if ($description === null) {
                $description = $category->descriptions()->first();

            }

            $description2 = $category->descriptions()->where('language_id', '!=', $language_id)->first();

            $count = $category->products()->count();
            $item['category_id'] = $category->category_id;
            $item['name'] = $description->name;
            $item['other_name'] = $description2->name;
            $item["number_of_products"] = $count;
            $item["image"] = $category->image;
            $item["status"] = $category->status;

            array_push($response_array, $item);
        }

        return $response_array;

    }

    public function delete(Request $request, $category_id)
    {
        $language_id = isset($request->language_id) ? $request->language_id : 2;
        // Category::destroy($category_id);
        $category = Category::find($category_id);
        $category->status = 1;
        $category->save();
        // CategoryDescription::where("category_id", $category_id)->delete();

        $categories = self::getCategoryList($language_id);

        return response()->json($categories, 200);
    }

}
