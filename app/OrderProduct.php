<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    protected $table = "oc_order_product";
    protected $primaryKey = "order_product_id";
    public $timestamps = false;

    protected $fillable = ['order_id', 'product_id', 'quantity', 'price', 'total', 'name', 'product_discount_id'];

    protected $attributes = [
        "model" => "",
        "tax" => 0,
        'reward' => 0,
        'status' => 0,
    ];

    protected $hidden = [

        'model',
        "reward",
    ];

    public function getPriceAttribute($value)
    {
        return number_format($value, 2);

    }

    public function getTotalAttribute($value)
    {
        return number_format($value, 2);

    }
    public function category()
    {
        return $this->hasOne("App\ProductToCategory", "product_id", "product_id");
    }
}
