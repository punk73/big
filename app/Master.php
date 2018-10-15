<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Master extends Model
{
    public function __construct(){
		$this->connection = env('DB_CONNECTION2', 'sqlsrv');
	}

    protected $table = 'masters';
}
