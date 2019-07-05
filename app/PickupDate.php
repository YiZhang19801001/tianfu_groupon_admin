<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PickupDate extends Model
{
    protected $table = "oc_pickup_date";
    protected $primaryKey = "pickup_date_id";
    public $timestamps = false;

    protected $fillable = ['sales_group_id', 'location_id', 'date'];
}
