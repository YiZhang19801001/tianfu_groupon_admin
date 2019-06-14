<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductDiscount extends Model
{
    protected $table = "oc_product_discount";
    protected $primaryKey = "product_discount_id";
    protected $fillable = ["product_id", "price", 'quantity', "max_quantity", "sales_group_id"];
    protected $attributes = [
        "customer_group_id" => 2,
        "priority" => 1,
        "date_start" => '2019-06-06',
        "date_end" => '2019-07-02',
        "status" => 0,

    ];
    public $timestamps = false;
}
