<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'oc_location';
    protected $primaryKey = 'location_id';
    public $timestamps = false;

    protected $fillable = ['name', 'open', 'address', 'telephone'];

    protected $attributes = [
        'fax' => '',
        'geocode' => '',
        'image' => '',
        'comment' => '',
        'status' => 0,
    ];
    protected $hidden = [
        'fax',
        'geocode',
        'comment',
    ];

    public function pickupDate()
    {
        return $this->hasMany('App\PickupDate', 'location_id', 'location_id');
    }

    public function descriptions()
    {
        return $this->hasMany('App\LocationDescription', 'location_id', 'location_id');
    }
}
