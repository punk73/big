<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Mastermodel extends Model
{
    protected $table = 'models';

    protected $casts = [
        'cavity',
    ];

    protected $fillable = [
    	'name',
    	'pwbno',
    	'pwbname',
    	'process',
    	'cavity',
    	'side',
        'ynumber',

    ];

    public function generateCode(){

    	if ($this->code == null ) {
            $this->code = $this->initCode();
    	}
    }

    public function initCode(){
        $ynumberCode = str_replace('-', '', $this->ynumber );
        $pwbnameCode = $this->pwbname[0] . $this->pwbname[ (strlen($this->pwbname) -1) ];
        $code = $ynumberCode . $pwbnameCode;
        return $code;
    }

    public function schedules(){
        return $this->select([
            'schedule_details.*'
        ])->join('schedule_details', function ($join){
            $join->on( 'models.name', '=', 'schedule_details.model');
            $join->on( 'models.pwbname', '=', 'schedule_details.pwbname');
            $join->on( 'models.pwbno', '=', 'schedule_details.pwbno');
            $join->on( 'models.process', '=', 'schedule_details.process');
        })->where('models.id', $this->id );
    }
}
