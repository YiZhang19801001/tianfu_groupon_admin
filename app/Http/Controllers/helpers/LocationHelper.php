<?php
namespace App\Http\Controllers\helpers;

use App\Location;
use App\LocationDescription;
use App\PickupDate;

class LocationHelper
{
    public function getLocations($sales_group_id, $language_id)
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

        # resign location name
        foreach ($locations as $location) {
            $location->descriptions = $location->descriptions()->get();

            $locationDescription = LocationDescription::where('location_id', $location->location_id)->where('language_id', $language_id)->first();
            if ($locationDescription === null) {
                $locationDescription = LocationDescription::where('location_id', $location->location_id)->first();
            }

            if ($locationDescription !== null) {
                $location->name = $locationDescription->location_name;
            }
        }

        # return modified locations
        return $locations;
    }
}
