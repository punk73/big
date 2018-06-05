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
    	'side',

    ];

    public function generateCode(){

    	if ($this->code == null ) {
    		# code...
	    	$prevCode = $this->select(['code'])
	        ->where('name', $this->name)
	        ->where('pwbname', $this->pwbname)
	        ->where('pwbno', $this->pwbno)
	        ->first();

	        //jumlah yg sudah di generate sebelumnya.
	      	// dengan begini akan banyak code yg terlewat, exp: 1,2,5,9 dst
            if ($prevCode['code'] != null) {
                $code = $prevCode->code;    
            }else{
            	$generated = $this->select(['name','pwbname','pwbno'])
		        ->where('code', '!=', null) //berarti yg sudah di generate
		        ->groupBy('name')
		        ->groupBy('pwbname')
		        ->groupBy('pwbno')
		        ->get();
		        $generated = count($generated)+1;
                $code=str_pad( dechex($generated) , 5, '0', STR_PAD_LEFT );
            }

            $this->code = $code;
           
    	}
    }
}
