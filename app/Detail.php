<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Detail extends Model
{
    protected $table = 'details';
    protected $fillable = [
    	'model_detail_id',
    	'start_serial',
    	'lot_size',
    	'seq_start',
    	'seq_end',
    	'qty',
    ];
}
