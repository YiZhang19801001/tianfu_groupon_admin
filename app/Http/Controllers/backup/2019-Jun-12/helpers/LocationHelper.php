<?php
namespace App\Http\Controllers\helpers;

use App\Location;

class LocationHelper
{
    public function getLocations()
    {
        $locations = Location::all();
        foreach ($locations as $location) {
            if ($location->open !== null) {
                $location->open = json_decode($location->open);
            } else {
                $location->open = json_decode("{}");
            }
        }

        return $locations;
    }
}
