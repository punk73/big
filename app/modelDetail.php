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

    public function generateCodeBackup($model_id){
    	// cek jumlah model detail dengan model id sperti berikut. 
        $modelDetailCount = $this->where('model_id', $model_id )->get();
        $modelDetailCount = count($modelDetailCount) + 1; //kalau null, maka no increment
        // pertama adalah 1.

        //generate incrementing code
        $code = str_pad( dechex($modelDetailCount) , 3, '0', STR_PAD_LEFT );
        // assign code into model details
        $this->code = $code;
    }

    public function generateCode($model_id){
        $code = $this->prod_no;
        $this->code = $code;
    }
}
