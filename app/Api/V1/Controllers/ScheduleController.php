<?php

namespace App\Api\V1\Controllers;
use App\Http\Controllers\Controller; //parent contoller
use Illuminate\Http\Request;
use App\Schedule;
use App\ScheduleDetail;
use App\Mastermodel;
use App\modelDetail;

class ScheduleController extends Controller
{
    public function index(Request $request){
    	$limit = (isset($request->limit) && $request->limit != '' ) ? $request->limit : 25 ;
    	$models = Schedule::select();    	
    	$models = $models->paginate($limit);
    	return $models;
    }

    public function store(Request $request){
    	$parameters = $this->getParameter($request);
    	// return $parameters;
    	$model = Schedule::firstOrNew([
            'release_date'=> $parameters['release_date']
        ], $parameters );

        // return $model;

        if ($model->exists()) {
            // kalau sudah ada di tanggal ini, maka input dengan rev id yang baru.
            // ambil rev date terakhir
            $rev = Schedule::select('rev')
            ->where('release_date', $parameters['release_date'] )
            ->orderBy('id', 'desc')->first();
            $lastRevision = $rev['rev'] + 1;


            $model = new Schedule;
            foreach ($parameters as $key => $parameter) {
                if ($parameter != null) {
                    # code...
                    $model->$key = $parameter;
                }
            }
            $model->rev = $lastRevision;
            $model->save();

        } else {
            // kalau ga ada yang update
    	   $model->save();
        }
    	
        $uploadStatus = $this->upload($request, $model );

    	return [
    		'_meta' => [
    			'message' => 'OK',
                'upload_status' => $uploadStatus
    		],
    		'data'=> $model
    	];
    }

    /*public function update(Request $request, $id ){
    	$model = Schedule::find($id);
    	
    	if ($model == null) {
    		return [
	    		'_meta' => [
	    			'message' => 'data not found!'
	    		],
	    		'data'=> $model
	    	];
    	}

    	$parameters = $this->getParameter($request);
    
    	foreach ($parameters as $key => $parameter) {
    		$model->$key = (isset( $parameter) && $parameter != null ) ? $parameter : $model->$key ;
    	}

    	$model->save();

    	return [
    		'_meta' => [
    			'message' => 'OK'
    		],
    		'data'=> $model
    	];
    }

    public function delete(Request $request, $id){
    	$model = Schedule::find($id);

    	if ($model == null) {
    		return [
	    		'_meta' => [
	    			'message' => 'data not found!'
	    		],
	    		'data'=> $model
	    	];
    	}

    	$model->delete();

    	return [
    		'_meta' => [
    			'message' => 'OK'
    		]
    	];
    }*/

    public function upload(Request $request, Schedule $schedule ){
    	//get the
    	if ($request->hasFile('file')) {

    	 	# kalau bukan csv, return false;
    	 	if ($request->file('file')->getClientOriginalExtension() != 'csv' ) {
    	 		return [
    	 			'message' => 'you need to upload csv file!',
    	 			'data' => $request->file('file')->getClientOriginalExtension()
    	 		];
                // return false;
    	 	}

    		$file = $request->file('file');
    		$name = time() . '-' . $file->getClientOriginalName();
    		$path = storage_path('schedules');
    		
    		$file->move($path, $name); //pindah ke file server;
    		
    		// return [$file, $path, $name ];
    		$fullname = $path .'\\'. $name ;
    		$importedCsv = $this->csvToArray($fullname);
    		// return [$fullname, $importedCsv];
    		if ($importedCsv) { //kalau something wrong ini bakal bernilai false
                
                ScheduleDetail::truncate(); //truncate table schedules
                
                for ($i = 0; $i < count($importedCsv); $i ++)
                {
                    // first parameter is data to check, second is data to input
                    
                    $line = $importedCsv[$i]['line'];
                    $model = $importedCsv[$i]['model'];
                    $pwbNo = $importedCsv[$i]['pwbno'];
                    $pwbName = $importedCsv[$i]['pwb_name'];
                    $process = $importedCsv[$i]['process'];
                    $prodNo = $importedCsv[$i]['prod_no'];
                    $startSerial = $importedCsv[$i]['start_serial'];
                    $lotSize = $importedCsv[$i]['lot_size'];

                    // kalau belum ada buat, kalau udah ada, skip.
                    $masterModel = Mastermodel::firstOrNew([
                        'name'=> $model,
                        'pwbno'=> $pwbNo,
                        'pwbname'=> $pwbName,
                        'process' => $process
                    ]);
                    
                    if (!isset($masterModel->id)) { //kalau belum ada, di save dulu. kalau udah, gausah.
                        $masterModel->save();
                    }
                    // dechex untuk import decimal ke hexa.
                    //str_pad untuk kasih 0 di depan.
                    $masterModel->code = str_pad( dechex($masterModel->id) , 8, '0', STR_PAD_LEFT );
                    $masterModel->save();

                    // input ke model_details
                    /*$modelDetail = model_detail::firstOrNew([
                        'model_id' => $masterModel->id ,
                        'prod_no' => $prodNo,
                    ]);

                    if (!isset($modelDetail->id)) {
                        $modelDetail->save();
                    }

                    $modelDetail->code = '';
                    $modelDetail->save();*/
                    
                    $scheduleDetail = new ScheduleDetail;
                    $scheduleDetail->schedule_id = $schedule->id;
                    $scheduleDetail->line = $line;
                    $scheduleDetail->model = $model;
                    $scheduleDetail->pwbno = $pwbNo;
                    $scheduleDetail->pwbname = $pwbName;
                    $scheduleDetail->process = $process;
                    $scheduleDetail->prod_no = $prodNo;
                    $scheduleDetail->start_serial = $startSerial;
                    $scheduleDetail->lot_size = $lotSize;
                    $scheduleDetail->rev_date = date('Y-m-d'); //$revDate;
                    $scheduleDetail->save();

			    }
    		}

		    return [
				'message' => 'Good!!'
			];
            // return true;
    	}

    	return [
    		'message' => 'no file found'
    	];
        // return false;
    }

    public function getParameter(Request $request){
    	return $request->only(
    		'release_date',
            'effective_date',
            'end_effective_date',
            'is_processed',
            'is_active'
    	);
    }

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
