<?php

namespace App\Http\Controllers;

use App\Http\Controllers\helpers\LocationHelper;
use App\Location;
use App\LocationDescription;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    private $helper;
    public function __construct()
    {
        $this->helper = new LocationHelper();
    }
    /**
     * all locations(shops)
     * @param Integer $language_id
     * @return Response all shops
     */
    public function index(Request $request)
    {
        // validation
        $errors = array();
        // if (!is_numeric($language_id) || !is_integer($language_id)) {
        //     $errors['language_id'] = ['The language id is not valid'];
        // }
        if (count($errors) > 0) {
            return response()->json(compact('errors'), 422);
        }

        $language_id = $request->input('language_id', 2);

        // prepare data
        $locations = $this->helper->getLocations($request->input('sales_group_id', 0), $language_id);

        return response()->json(compact('locations'), 200);
    }

    public function show($location_id)
    {
        $shop = Location::find($location_id);
        $shop->pickupDate = $shop->pickupDate()->get();
        $shop->descriptions = $shop->descriptions()->get();
        return response()->json(compact("shop"), 200);
    }

    /**
     * create location
     * @param Request
     * @return Response new location created
     */
    public function create(Request $request)
    {

        $location_name_en = $request->input('en_name', "");
        $location_name_cn = $request->input('cn_name', "");

        //validation
        $validatedData = $request->validate([
            'name' => 'required',
            'address' => 'required',
            'telephone' => 'required',
        ]);
        $errors = array();
        // if (!isset($request->open) || !is_array($request->open)) {
        //     $errors['open'] = ['The open is not valid.'];
        // }
        if (count($errors) > 0) {
            return response()->json(compact('errors'), 422);
        }

        // create location
        $location = Location::create([
            'name' => $request->input('name', ""),
            'open' => json_encode($request->input('open', [])),
            'address' => $request->address,
            'telephone' => $request->telephone,
        ]);

        if (isset($request->status)) {
            $location->status = $request->status;
            $location->save();
        }

        LocationDescription::create(['location_id' => $location->location_id, 'language_id' => "1", "location_name" => $location_name_en]);
        LocationDescription::create(['location_id' => $location->location_id, 'language_id' => "2", "location_name" => $location_name_cn]);

        // prepare data
        $locations = $this->helper->getLocations(0);

        return response()->json(compact('locations'), 200);

    }
    /**
     * update location
     * @param Request
     * @param Integer $location_id
     * @return Response new location info
     */
    public function update(Request $request, $location_id)
    {
        // validation
        $errors = array();
        if (!is_numeric($location_id) || !is_integer($location_id + 0)) {
            $errors['language_id'] = ['The language id is not valid.'];
        }

        $location = Location::find($location_id);
        if ($location === null) {
            $errors['location'] = ['The location is not found.'];
        }
        // if (isset($request->open) && !is_array($request->open)) {
        //     $errors['open'] = ['The open is not valid.'];
        // }
        if (count($errors) > 0) {
            return response()->json(compact('errors'), 422);
        }

        // update
        $input = array();
        if (isset($request->address)) {
            $input['address'] = $request->address;
        }

        if (isset($request->name)) {
            $input['name'] = $request->name;
        }

        if (isset($request->telephone)) {
            $input['telephone'] = $request->telephone;
        }

        if (isset($request->open)) {
            $input['open'] = json_encode($request->open);
        }

        $location->update($input);

        if (isset($request->en_name)) {
            $locationDescription = LocationDescription::where('location_id', $location_id)->where('language_id', 1)->first();
            if ($locationDescription === null) {
                LocationDescription::create(['location_id' => $location_id, 'language_id' => 1, 'location_name' => $request->en_name]);
            } else {
                $locationDescription->location_name = $request->en_name;
                $locationDescription->save();
            }
        }

        if (isset($request->cn_name)) {
            $locationDescription = LocationDescription::where('location_id', $location_id)->where('language_id', 2)->first();
            if ($locationDescription === null) {
                LocationDescription::create(['location_id' => $location_id, 'language_id' => 2, 'location_name' => $request->cn_name]);
            } else {
                $locationDescription->location_name = $request->cn_name;
                $locationDescription->save();
            }
        }

        $locations = $this->helper->getLocations(0, 2);

        return response()->json(compact('locations'), 200);
    }
    public function delete(Request $request, $location_id)
    {
        $location = Location::find($location_id);

        $location->status = 1;

        $location->save();

        $locations = $this->helper->getLocations(0, $request->input('language_id', 2));

        return response()->json(compact("locations"), 200);
    }
    public function patch(Request $request, $location_id)
    {
        $location = Location::find($location_id);

        $location->status = 0;

        $location->save();

        $language_id = $request->input('language_id', 2);

        $locations = $this->helper->getLocations(0, $language_id);

        return response()->json(compact("locations"), 200);

    }
}
