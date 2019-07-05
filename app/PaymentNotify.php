<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentNotify extends Model
{
    protected $table = "oc_payment_notify";
    protected $primaryKey = 'payment_notify_id';
    public $timestamps = false;

    protected $fillable = ['date_received', 'message', 'payment_method', 'status'];
}
