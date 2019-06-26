<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PsoLocal extends Model
{
    protected $fillable = [
        'line',
        'model',
        'prod_no',
        'start_serial',
        'qty',
    ];

}
