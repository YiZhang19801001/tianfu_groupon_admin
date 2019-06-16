<?php
namespace App\Http\Controllers\helpers;

use App\Location;
use App\PickupDate;

class LocationHelper
{
    public function getLocations($sales_group_id)
    {

        # find all available pickup dates according to given $sales_group_id
        $sql = PickupDate::where('sales_group_id', $sales_group_id);

        $pickupDates = $sql->get();
        $location_ids = $sql->pluck('location_id')->toArray();

        # found available locations/stores for given sales group
        $locations = Location::whereIn('location_id', $location_ids)->get();

        foreach ($locations as $location) {
            $dates = array();
            # add available pickup dates to each location/store
            foreach ($pickupDates as $pickupDate) {
                if ($pickupDate->location_id === $location->location_id) {
                    array_push($dates, array('value'=>$pickupDate->date,'id'=>$pickupDate->pickup_date_id));
                }
            }

            $location['pickupDates'] = $dates;
        }

        # return modified locations
        return $locations;
    }
}
