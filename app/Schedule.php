<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
    	'release_date',
    	'effective_date',
    	'end_effective_date'
    ];
}
