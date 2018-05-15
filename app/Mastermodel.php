<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Mastermodel extends Model
{
    protected $table = 'models';
    protected $fillable = [
    	'name',
    	'pwbno',
    	'pwbname',
    	'process',
    	'cavity',
    ];
}
