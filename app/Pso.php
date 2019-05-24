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
}
