<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pso extends Model
{
    public function __construct(){
        /* buat connect ke db_pso */
		$this->connection = env('DB_CONNECTION3', 'mysql3');
	}

    protected $table = 't_file';

    protected $primaryKey = 'create_time';

    public function getCurrent( $create_time = null ) {
        if($create_time == null) {
            $create_time = $this->getCurrentCreateTime();
        }

        return $this->where('create_time', $create_time );

    }

    public function getCurrentCreateTime() {
        $result = $this->select('create_time')
            ->orderBy('create_time', 'desc')
            ->first();
        
        if($result != false) {
            return $result['create_time'];
        }

        return null;
    }
}
