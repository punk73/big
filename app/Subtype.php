<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Mastermodel;

class Subtype extends Model
{
    public function model(){
    	return $this->belongsTo('App\Mastermodel');
    }

    public function withModelname(){
    	$modelname = Mastermodel::select('name')->find($this->model_id); 
    	$this->modelname = (isset( $modelname['name'] )) ? $modelname['name'] : 'modelname-temporary' ;
    }
}
