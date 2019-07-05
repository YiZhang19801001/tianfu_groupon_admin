<?php

namespace App\Http\Controllers;

use App\Http\Controllers\helpers\LocationHelper;
use App\PickupDate;
use Illuminate\Http\Request;

class PickupDateController extends Controller
{
    private $locationHelper;
    public function __construct()
    {
        $this->locationHelper = new LocationHelper();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        # read inputs
        $date = $request->input('date');
        $sales_group_id = $request->input('sales_group_id');
        $location_id = $request->input('location_id');
        # create row in DB
        PickupDate::create(compact('date', 'sales_group_id', 'location_id'));

        # prepare response
        $locations = $this->locationHelper->getLocations($sales_group_id);

        # return response
        return response()->json(compact('locations'), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        # read inputs
        $date = $request->input('date');
        $sales_group_id = $request->input('sales_group_id');
        // $location_id = $request->input('location_id');
        # update row in DB
        $pickupDate = PickupDate::find($id);
        $pickupDate->date = $date;
        $pickupDate->save();

        # prepare response
        $locations = $this->locationHelper->getLocations($sales_group_id);

        # return response
        return response()->json(compact('locations'), 200);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        PickupDate::destroy($id);
        $sales_group_id = $request->input('sales_group_id');
        # prepare response
        $locations = $this->locationHelper->getLocations($sales_group_id);

        # return response
        return response()->json(compact('locations'), 200);

    }
}
