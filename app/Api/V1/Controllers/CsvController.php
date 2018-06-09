<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use Dingo\Api\Routing\Helpers;


class CsvController extends Controller
{
    public function csvToArray($filename = '', $delimiter = ',')
	{
	    if (!file_exists($filename) || !is_readable($filename))
	        return false;

	    $header = null;
	    $data = array();
	    if (($handle = fopen($filename, 'r')) !== false)
	    {
	        while (($row = fgetcsv($handle, 1000, $delimiter)) !== false)
	        {
	            if (!$header)
	                $header = $row;
	            else
	                $data[] = array_combine($header, $row);
	        }
	        fclose($handle);
	    }

	    return $data;
	}
}
