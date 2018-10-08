<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ScheduleDetail extends Model
{
    protected $table = 'schedule_details';

    protected $fillable = [
    	'line',
    	'model',
    	'pwbno',
    	'pwbname',
    	'process',
    	'prod_no',
    	'start_serial',
    	'lot_size',
        'cavity',
        'side'
    ];
}
