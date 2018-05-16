<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ScheduleHistory extends Model
{
    protected $table = 'schedule_histories';

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
    ];

    public function schedule (){
        return $this->belongsTo('App\Schedule');
    }
}
