<?php

namespace App\Api\V1\Controllers;
use App\Http\Controllers\Controller; //parent contoller
use Illuminate\Http\Request;
use App\Schedule;
use App\ScheduleDetail;
use App\ScheduleHistory;
use App\Mastermodel;
use App\modelDetail;

class ScheduleController extends Controller
{
    public function index(Request $request){
    	$limit = (isset($request->limit) && $request->limit != '' ) ? $request->limit : 25 ;
    	$models = Schedule::select();
        /*Search Query*/
            if ($request->release_date != null && $request->release_date != '' ) {
                # code...
                $models = $models->where('release_date','=', $request->release_date );
            }
        /*End Search Query*/    	
    	$models = $models->orderBy('rev', 'desc')->get(); //->paginate($limit);
    	return [
            'count' => count($models),
            'data'=>    $models
        ];
    }

    public function store(Request $request){
        try { 
    	   $parameters = $this->getParameter($request);
        } catch (Exception $e) {
            $e->getMessage();
        }
    	
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
    	
    	return [
    		'_meta' => [
    			'message' => 'OK',
        	],
            'success' => true,
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

    	 	# kalau bukan csv atau txt, return false;
            $dataType = $request->file('file')->getClientOriginalExtension();
    	 	if ($dataType == 'csv' || $dataType == 'txt' ) {
                //yg boleh masuk csv & txt saja
    	 	}else{
                return [
                    'message' => 'you need to upload csv file!',
                    'data' => $request->file('file')->getClientOriginalExtension()
                ];
            }

    		$file = $request->file('file');
    		$name = time() . '-' . $file->getClientOriginalName();
    		$path = storage_path('schedules');
    		
    		$file->move($path, $name); //pindah ke file server;
    		
    		// return [$file, $path, $name ];
    		$fullname = $path .'\\'. $name ;
    		$importedCsv = $this->csvToArray($fullname);
    		// return [$fullname, $importedCsv, count($importedCsv)];
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
                    $cavity = (isset($importedCsv[$i]['cavity'])) ? $importedCsv[$i]['lot_size'] : null ;
                    // default value untuk qty adalah lot size
                    $qty = (isset($importedCsv[$i]['qty'])) ? $importedCsv[$i]['qty'] : $lotSize ;
                    // kalau belum ada buat, kalau udah ada, skip.
                    $masterModel = Mastermodel::firstOrNew([
                        'name'=> $model,
                        'pwbno'=> $pwbNo,
                        'pwbname'=> $pwbName,
                        'process' => $process,
                        'cavity' => $cavity
                    ]);
                    
                    if (!isset($masterModel->id)) { //kalau belum ada, di save dulu. kalau udah, gausah.
                        $masterModel->save();
                    }
                    // dechex untuk import decimal ke hexa.
                    //str_pad untuk kasih 0 di depan. // "i" adalah code country untuk indonesia //dan dua digit terakhir adalah cavity dalam decimal. kalau cavity 1 maka "01"
                    $masterModel->code = str_pad( dechex($masterModel->id) , 5, '0', STR_PAD_LEFT ) /*. 'I' . str_pad( $cavity , 2, '0', STR_PAD_LEFT )*/  ;
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
                    $scheduleDetail->cavity = $cavity;
                    $scheduleDetail->model = $model;
                    $scheduleDetail->pwbno = $pwbNo;
                    $scheduleDetail->pwbname = $pwbName;
                    $scheduleDetail->process = $process;
                    $scheduleDetail->prod_no = $prodNo;
                    $scheduleDetail->start_serial = $startSerial;
                    $scheduleDetail->lot_size = $lotSize;
                    $scheduleDetail->rev_date = date('Y-m-d'); //$revDate;
                    $scheduleDetail->qty = $qty;
                    
                    $scheduleDetail->save();

                    $ScheduleHistory = new ScheduleHistory;
                    $ScheduleHistory->schedule_id = $schedule->id;
                    $ScheduleHistory->line = $line;
                    $ScheduleHistory->cavity = $cavity;
                    $ScheduleHistory->model = $model;
                    $ScheduleHistory->pwbno = $pwbNo;
                    $ScheduleHistory->pwbname = $pwbName;
                    $ScheduleHistory->process = $process;
                    $ScheduleHistory->prod_no = $prodNo;
                    $ScheduleHistory->start_serial = $startSerial;
                    $ScheduleHistory->lot_size = $lotSize;
                    $ScheduleHistory->rev_date = date('Y-m-d'); //$revDate;
                    $ScheduleHistory->qty = $qty;
                    
                    $ScheduleHistory->save();
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
