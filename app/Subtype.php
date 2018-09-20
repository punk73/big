<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Subtype extends Model
{
    public function model(){
    	return $this->belongsTo('App\Mastermodel');
    }
}
