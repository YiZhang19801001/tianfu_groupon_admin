<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LocationDescription extends Model
{
    protected $table = 'oc_location_description';
    protected $primaryKey = "location_description_id";

    protected $fillable = ['location_id','language_id','location_name'];

    public $timestamps = false;
}
