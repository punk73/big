<?php

namespace App\Api\V1\Controllers;
use App\Http\Controllers\Controller; //parent contoller
use Illuminate\Http\Request;
use App\Mastermodel;
use DB;

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
            DB::raw('concat(models.code , model_details.code) as code'),
            'side',
            'model_id',
            'counter',
            'start_serial',
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

            if ($request->pwbno != null && $request->pwbno != '' ) {
                # code...
                $models = $models->where('pwbno','like','%'.$request->pwbno.'%');
            }

            if ($request->process != null && $request->process != '' ) {
                # code...
                $models = $models->where('process','like','%'.$request->process.'%');
            }

            if ($request->code != null && $request->code != '' ) {
                # code...
                $models = $models->where('code','like','%'.$request->code.'%');
            }
        /*End Search*/
    	$models = $models->paginate($limit);
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

    public function process (){
        $models = Mastermodel::select()->where('code', '=', null )->get();
        foreach ($models as $key => $model) {
            if ($model->code == null) {
                $masterModel = Mastermodel::find($model->id);
                if ($masterModel != null) {
                    # code...
                    $code = str_pad( dechex($model->id) , 5, '0', STR_PAD_LEFT ) . 'i' . str_pad( $model->cavity , 2, '0', STR_PAD_LEFT );
                    $masterModel->code = $code;
                    $masterModel->save();
                }
            }
        }

        return [
            'success' => true,
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
