<?php
namespace App\Http\Controllers\helpers;

use App\Location;
use App\PickupDate;

class LocationHelper
{
    public function getLocations($sales_group_id)
    {

        if ($sales_group_id != 0) {
            # find all available pickup dates according to given $sales_group_id
            $sql = PickupDate::where('sales_group_id', $sales_group_id);

            $pickupDates = $sql->get();
            $location_ids = $sql->pluck('location_id')->toArray();

            # found available locations/stores for given sales group
            $locations = Location::whereIn('location_id', $location_ids)->with('pickupDate')->get();

        } else {
            $locations = Location::where('status', 0)->with('pickupDate')->get();
        }

        # return modified locations
        return $locations;
    }
}
