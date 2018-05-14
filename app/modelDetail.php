<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class modelDetail extends Model
{
    protected $table = 'model_details';

    protected $fillable = [
    	'model_id',
    	'prod_no',
    	// 'code'
    ];
}
