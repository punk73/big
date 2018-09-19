<?php

namespace App\Api\V1\Controllers;
use App\Http\Controllers\Controller; //parent contoller
use Illuminate\Http\Request;
use App\Mastermodel;
use DB;
use Storage;
use File;
use App\Schedule;
use App\ScheduleDetail;

class ModelController extends Controller
{   
    public function index(Request $request){
    	$limit = (isset($request->limit) && $request->limit != '' ) ? $request->limit : 25 ;
    	$models = Mastermodel::select([
            'models.id',
            'name',
            'pwbno',
            'pwbname',
            'process',
            'cavity',
            // DB::raw('concat(models.code , model_details.code) as code'),
            'models.code',
            'models.ynumber',
            'side',
            'model_id',
            'prod_no',
        ])
        ->leftJoin('model_details', 'models.id', '=', 'model_details.model_id');    	
        /*Search Query*/
            if ($request->name != null && $request->name != '' ) {
                # code...
                $models = $models->where('name','like','%'.$request->name.'%');
            }

            if ($request->pwbno != null && $request->pwbno != '' ) {
                # code...
                $models = $models->where('pwbno','like','%'.$request->pwbno.'%');
            }

            if ($request->pwbname != null && $request->pwbname != '' ) {
                # code...
                $models = $models->where('pwbname','like','%'.$request->pwbname.'%');
            }

            if ($request->process != null && $request->process != '' ) {
                # code...
                $models = $models->where('process','like','%'.$request->process.'%');
            }

            if ($request->cavity != null && $request->cavity != '' ) {
                # code...
                $models = $models->where('cavity','like','%'.$request->cavity.'%');
            }

            if ($request->side != null && $request->side != '' ) {
                # code...
                $models = $models->where('side','like','%'.$request->side.'%');
            }

            if ($request->code != null && $request->code != '' ) {
                # code...
                $models = $models->where('models.code','like', $request->code.'%')
                ->orWhere('model_details.code', 'like', $request->code .'%');
            }
        /*End Search*/
    	$models = $models
        ->orderBy('models.id','desc')
        ->paginate($limit);
    	return $models;
    }

    public function store(Request $request){
    	$parameters = $this->getParameter($request);
    	
    	$model = new Mastermodel;
    	foreach ($parameters as $key => $parameter) {
    		$model->$key = $parameters[$key];	
    	}

    	$model->save();
    	
    	return [
    		'_meta' => [
    			'message' => 'OK'
    		],
    		'data'=> $model
    	];
    }

    public function update(Request $request, $id ){
    	$model = Mastermodel::find($id);
    	
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
        // delete generated txt file
        // this update schedule details;
        $this->updateScheduleDetails($id, $parameters );
        // $this->deleteGeneratedFile($id);

    	return [
    		'_meta' => [
    			'message' => 'OK'
    		],
    		'data'=> $model
    	];
    }

    public function delete(Request $request, $id){
    	$model = Mastermodel::find($id);

    	if ($model == null) {
    		return [
	    		'_meta' => [
	    			'message' => 'data not found!'
	    		],
	    		'data'=> $model
	    	];
    	}
        
        // delete generated txt file
        $this->deleteGeneratedFile($id);

        $model->delete();

    	return [
    		'_meta' => [
    			'message' => 'OK'
    		]
    	];
    }

    public function upload(Request $request){
    	//get the
        ini_set('max_execution_time', 300);
        
    	if ($request->hasFile('file')) {

    	 	# kalau bukan csv, return false;
            $fileType = $request->file('file')->getClientOriginalExtension();
    	 	if ($fileType == 'txt' || $fileType == 'csv' ) {
    	 		// kalau type file nya txt atau csv, lanjut.
    	 	}else{
                return [
                    'success' => false,
                    'message' => 'you need to upload txt file!',
                    'data' => $request->file('file')->getClientOriginalExtension()
                ];
            }

    		$file = $request->file('file');
    		$name = time() . '-' . $file->getClientOriginalName();
    		$path = storage_path('models');
    		
    		$file->move($path, $name); //pindah ke file server;
    		
    		// return [$file, $path, $name ];
    		$fullname = $path .'\\'. $name ;
    		$importedCsv = $this->csvToArray($fullname);
    		// return [$fullname, $importedCsv];
    		if ($importedCsv) { //kalau something wrong ini bakal bernilai false
                $modelThatDoesnExist = [];
	    		for ($i = 0; $i < count($importedCsv); $i ++)
			    {
			    	// first parameter is data to check, second is data to input
                    $name = $importedCsv[$i]['name'];
                    $pwbno = $importedCsv[$i]['pwbno'];
                    $pwbname = $importedCsv[$i]['pwbname'];
                    $process = $importedCsv[$i]['process'];
                    $cavity = (isset($importedCsv[$i]['cavity'])) ? $importedCsv[$i]['cavity'] : null ;
                    $side = (isset($importedCsv[$i]['side'])) ? $importedCsv[$i]['side'] : null ;
                    // cek apakah data sudah ada
                    //kalau ada, lewat. kalau engga masukin ke array baru.
                    $masterModel = Mastermodel::where('name',$name)
                    ->where('pwbno',$pwbno)
                    ->where('pwbname',$pwbname)
                    ->where('process',$process)
                    ->exists();

                    if (!$masterModel) {
                        $modelThatDoesnExist[] = [
                            'name' => $name,
                            'pwbno' => $pwbno,
                            'pwbname' => $pwbname,
                            'process' => $process,
                            'cavity' =>$cavity,
                            'side' =>$side,
                        ];
                    }
                    //kalau array baru sudah 1000 index atau udah diujung, kirim ke db.
                    if (count($modelThatDoesnExist) == 300  || $i == (count($importedCsv)-1) ) {
                        Mastermodel::insert($modelThatDoesnExist);
                        // reset temp array
                        $modelThatDoesnExist = [];
                    }


			        /*Mastermodel::firstOrCreate([
                        'name' => $name,
                        'pwbno' => $pwbno,
                        'pwbname' => $pwbname,
                        'process' => $process,
                    ], [
                        'name' => $name,
                        'pwbno' => $pwbno,
                        'pwbname' => $pwbname,
                        'process' => $process,
                        'cavity' =>$cavity,
                        'side' =>$side,
                    ]);*/
			    }
    		}

		    return [
                'success' => true,
				'message' => 'Good!!'
			];
    	}

    	return [
            'success' => false,
    		'message' => 'no file found'
    	];
    }

    public function process(){
        ini_set('max_execution_time', 300); //in seconds
        
        $time_start = microtime(true);
        $generated = Mastermodel::select(['name','pwbname','pwbno'])
        ->where('code', '!=', null) //berarti yg sudah di generate
        ->groupBy('name')
        ->groupBy('pwbname')
        ->groupBy('pwbno')
        ->get();
        $generated = count($generated);
        $counter = 0;
        // return $generated;
        // &$generated = kita ambil outer scope variable, pass into the closure as reference,
        // jadi yang kita edit di dalam closure adalah variable yang sama.
        $models = Mastermodel::select()->where('code', '=', null )
        ->orderBy('id', 'asc')
        /*->paginate(100);
        return $models;*/
        ->chunk(100, function($models) use ($time_start, &$generated, &$counter ){
            foreach ($models as $key => $model) {
                if ($model->code == null) {
                    $masterModel = Mastermodel::find($model->id);
                    if ($masterModel != null) {
                        // code ga di input di program ini
                        // $code = str_pad( dechex($model->id) , 5, '0', STR_PAD_LEFT );

                        // get previous generated code
                        $prevCode = Mastermodel::select(['code'])
                        ->where('name', $model->name)
                        ->where('pwbname', $model->pwbname)
                        ->where('pwbno', $model->pwbno)
                        ->first();

                        // dengan begini akan banyak code yg terlewat, exp: 1,2,5,9 dst
                        if ($prevCode->code != null) {
                            $code = $prevCode->code;    
                        }else{
                            $generated++;
                            $counter++;
                            $code=str_pad( dechex($generated) , 5, '0', STR_PAD_LEFT );
                        }
                        $masterModel->code = $code;
                        $masterModel->save();
                    }
                }

                $end_time = microtime(true);


                if (($end_time - $time_start ) >= 290 ) {
                    return false; //escape from chunk
                }
            }
        });

        // jika models nge return false, maka proses didalem selesai
        $end_time = microtime(true);
        $remains = Mastermodel::select()->where('code', '=', null )->count();
        if (!$models) {
            return [
                'success' => true,
                'remains' => $remains,
                'generated' => $generated,
                'counter' => $counter,
                'time' => ($end_time - $time_start ),
                'message' => 'Code Generated!'
            ];
        }

        return [
            'success' => true,
            'remains' => $remains,
            'generated' => $generated,
            'counter' => $counter,
            'time' => ($end_time - $time_start ),
            'message' => 'Code Generated!'
        ];
    }

    public function getParameter(Request $request){
    	return $request->only(
    		'name',
    		'pwbname',
    		'pwbno',
    		'process',
            'cavity',
            'side',
    		'code'
    	); 
    }

    public function csvToArray($filename = '', $delimiter = ','){
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

    public function show(Request $request, $id ){
        $models = Mastermodel::find($id);

        return $models;
        $models = $models->leftJoin('model_details', 'models.id', '=', 'model_details.model_id');
    }

    protected $board_filename = 'board_id_schedule_';
    protected $cavity_filename = 'cavity_id_schedule_';
    protected $schedule_filename = 'schedule_code_';
    
    // parameternya schedule id bukan model id
    private function deleteGeneratedFile($id){
        $models = Mastermodel::find($id);
        $schedules = $models->schedules()->get();
        
        $schedule_id = [];
        foreach ($schedules as $key => $schedule ) {
            # code...
            $directory = '\\public\\code\\';
            $board = $this->board_filename . $schedule->id . '.txt';
            $cav = $this->cavity_filename . $schedule->id . '.txt';
            $schedulefile = $this->schedule_filename . $schedule->id . '.txt';
            
            $boardname = $directory . $board;
            $cavName = $directory . $cav;
            $schedulefilename = $directory .$schedulefile;

            Storage::delete([
                $boardname,
                $cavName,
                $schedulefilename,
            ]);
            
            // kumpulkan id schedule untuk dihapus
            $schedule_id[] = $schedule->id;
            
        }

        // hapus schedule yg sudah terkumpul
        ScheduleDetail::destroy($schedule_id);
    }

    private function updateScheduleDetails($id, Array $parameters ){
        $models = Mastermodel::find($id);
        $schedules = $models->schedules()->get();
        
        foreach ($schedules as $key => $schedule ) {
            /*foreach ($parameters as $key => $parameter) {
                $schedule->$key = (isset( $parameter) && $parameter != null ) ? $parameter : $schedule->$key ;
            }*/

            $schedule->model = (isset( $parameters['name']) && $parameters['name'] != null ) ? $parameters['name'] : $schedule->model ;
            $schedule->pwbname = (isset( $parameters['pwbname']) && $parameters['pwbname'] != null ) ? $parameters['pwbname'] : $schedule->pwbname ;
            $schedule->pwbno = (isset( $parameters['pwbno']) && $parameters['pwbno'] != null ) ? $parameters['pwbno'] : $schedule->pwbno;
            $schedule->process = (isset( $parameters['process']) && $parameters['process'] != null ) ? $parameters['process'] : $schedule->process ;

            $schedule->save();
        }
    }

    public function download(){
        $do = Mastermodel::get();
        // return $do;
        $fname = 'Mastermodel.csv';

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=$fname");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        $fp = fopen("php://output", "w");
        
        $headers = 'id,name,pwbno,pwbname,process,cavity,side,code,created_at,updated_at'."\n";

        fwrite($fp,$headers);

        foreach ($do as $key => $value) {
            # code...
            $row = [
                $value->id,
                $value->name,
                $value->pwbno,
                $value->pwbname,
                $value->process,
                $value->cavity,
                $value->side,
                $value->code,
                $value->created_at,
                $value->updated_at,   
            ];
            
            fputcsv($fp, $row);
        }

        fclose($fp);
    }

    public function test(){
        $model = Mastermodel::all();
        return $model;

    }

}
