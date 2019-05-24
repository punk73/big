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
    	$models = $this->getMaster();    	
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

    public function getMaster(){
        return Mastermodel::select([
            'id', //'model_details.id',
            'name',
            'pwbno',
            'pwbname',
            'process',
            'cavity',
            // DB::raw('concat(models.code , model_details.code) as code'),
            'models.code',
            'models.ynumber',
            'side',
            // 'model_id',
            // 'prod_no',
        ]);
        // ->leftJoin('model_details', 'models.id', '=', 'model_details.model_id');
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
                    $ynumber = (isset($importedCsv[$i]['ynumber'])) ? $importedCsv[$i]['ynumber'] : null ;
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
                            'ynumber' => $ynumber
                        ];
                    }
                    //kalau array baru sudah 1000 index atau udah diujung, kirim ke db.
                    if (count($modelThatDoesnExist) == config('not_exists_model_qty', 100 )  || $i == (count($importedCsv)-1) ) {
                        Mastermodel::insert($modelThatDoesnExist);
                        // reset temp array
                        $modelThatDoesnExist = [];
                    }

                    // message: "SQLSTATE[42000]: [Microsoft][ODBC Driver 17 for SQL Server][SQL Server]The incoming request has too many parameters. The server supports a maximum of 2100 parameters. Reduce the number of parameters and resend the request. (SQL: insert into [models] ([cavity], [name], [process], [pwbname], [pwbno], [side], [ynumber]) values (2, CB-XJ1265A00N, DM1, MAIN, J7K-0386-00, A, YJ5-334A-06), (2, CB-XJ1265A00N, DM2, MAIN, J7K-0386-00, B, YJ5-334A-06), (2, CB-XJ1265A01N, DM1, MAIN, J7K-0386-00, A, XJL-334A-00), (2, CB-XJ1265A01N, DM2, MAIN, J7K-0386-00, B, XJL-334A-00), (2, CB-XJ1265A03N, DM1, MAIN, J7K-0386-00, A, XJL-334A-01), (2, CB-XJ1265A03N, DM2, MAIN, J7K-0386-00, B, XJL-334A-01), (2, CB-XJ1265A04N, DM1, MAIN, J7K-0386-00, A, XJL-334A-02), (2, CB-XJ1265A04N, DM2, MAIN, J7K-0386-00, B, XJL-334A-02), (2, CB-XJ1265A06N, DM1, MAIN, J7K-0386-00, A, YJ5-334A-04), (2, CB-XJ1265A06N, DM2, MAIN, J7K-0386-00, B, YJ5-334A-04), (2, CB-XJ1265A07N, DM1, MAIN, J7K-0386-00, A, YJ5-334A-05), (2, CB-XJ1265A07N, DM2, MAIN, J7K-0386-00, B, YJ5-334A-05), (2, CB-XJ1265R01N, DM1, MAIN, J7K-0386-00, A, YJ5-334R-01), (2, CB-XJ1265R01N, DM2, MAIN, J7K-0386-00, B, YJ5-334R-01), (2, CB-XJ1265R02N, DM1, MAIN, J7K-0386-00, A, YJ5-334R-02), (2, CB-XJ1265R02N, DM2, MAIN, J7K-0386-00, B, YJ5-334R-02), (1, CB-XJ6144A00N, DM1, SWITCH, J7J-0532-00, A, YJ5-335A-01), (1, CB-XJ6144A00N, DM2, SWITCH, J7J-0532-00, B, YJ5-335A-01), (1, CB-XJ6144A01N, DM1, SWITCH, J7J-0532-00, A, XJL-335A-00), (1, CB-XJ6144A01N, DM2, SWITCH, J7J-0532-00, B, XJL-335A-00), (1, CB-XJ6144M02N, DM1, SWITCH, J7J-0532-00, A, XJL-335M-01), (1, CB-XJ6144M02N, DM2, SWITCH, J7J-0532-00, B, XJL-335M-01), (1, DDXGT501RA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-224A-04), (1, DDXGT501RA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-224A-04), (2, DDXGT501RA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-224A-04), (2, DDXGT501RA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-224A-04), (1, DDXGT501RA9N, DM1, MAIN, J7J-0519-10, B, YJ5-224A-04), (1, DDXGT501RA9N, DM2, MAIN, J7J-0519-10, A, YJ5-224A-04), (1, DDXGT501RA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-224A-04), (1, DDXGT501RA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-224A-04), (1, DDXGT505LA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-235A-00), (1, DDXGT505LA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-235A-00), (2, DDXGT505LA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235A-00), (2, DDXGT505LA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235A-00), (1, DDXGT505LA9N, DM1, MAIN, J7J-0519-10, B, YJ5-235A-00), (1, DDXGT505LA9N, DM2, MAIN, J7J-0519-10, A, YJ5-235A-00), (1, DDXGT505LA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235A-00), (1, DDXGT505LA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235A-00), (1, DDXGT505LMN, DM1, AUDIO, J7J-0520-10, B, YJ5-235M-01), (1, DDXGT505LMN, DM2, AUDIO, J7J-0520-10, A, YJ5-235M-01), (2, DDXGT505LMN, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235M-01), (2, DDXGT505LMN, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235M-01), (1, DDXGT505LMN, DM1, MAIN, J7J-0519-10, B, YJ5-235M-01), (1, DDXGT505LMN, DM2, MAIN, J7J-0519-10, A, YJ5-235M-01), (1, DDXGT505LMN, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235M-01), (1, DDXGT505LMN, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235M-01), (1, DDXGT506LA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-235A-01), (1, DDXGT506LA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-235A-01), (2, DDXGT506LA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235A-01), (2, DDXGT506LA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235A-01), (1, DDXGT506LA9N, DM1, MAIN, J7J-0519-10, B, YJ5-235A-01), (1, DDXGT506LA9N, DM2, MAIN, J7J-0519-10, A, YJ5-235A-01), (1, DDXGT506LA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235A-01), (1, DDXGT506LA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235A-01), (1, DDXGT506LRN, DM1, AUDIO, J7J-0520-10, B, YJ5-235R-01), (1, DDXGT506LRN, DM2, AUDIO, J7J-0520-10, A, YJ5-235R-01), (2, DDXGT506LRN, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235R-01), (2, DDXGT506LRN, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235R-01), (1, DDXGT506LRN, DM1, MAIN, J7J-0519-10, B, YJ5-235R-01), (1, DDXGT506LRN, DM2, MAIN, J7J-0519-10, A, YJ5-235R-01), (1, DDXGT506LRN, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235R-01), (1, DDXGT506LRN, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235R-01), (1, DDXGT506RA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-235A-02), (1, DDXGT506RA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-235A-02), (2, DDXGT506RA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235A-02), (2, DDXGT506RA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235A-02), (1, DDXGT506RA9N, DM1, MAIN, J7J-0519-10, B, YJ5-235A-02), (1, DDXGT506RA9N, DM2, MAIN, J7J-0519-10, A, YJ5-235A-02), (1, DDXGT506RA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235A-02), (1, DDXGT506RA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235A-02), (1, DDXGT506RRN, DM1, AUDIO, J7J-0520-10, B, YJ5-235R-02), (1, DDXGT506RRN, DM2, AUDIO, J7J-0520-10, A, YJ5-235R-02), (2, DDXGT506RRN, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235R-02), (2, DDXGT506RRN, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235R-02), (1, DDXGT506RRN, DM1, MAIN, J7J-0519-10, B, YJ5-235R-02), (1, DDXGT506RRN, DM2, MAIN, J7J-0519-10, A, YJ5-235R-02), (1, DDXGT506RRN, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235R-02), (1, DDXGT506RRN, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235R-02), (1, DDXGT507LA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-235A-04), (1, DDXGT507LA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-235A-04), (2, DDXGT507LA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235A-04), (2, DDXGT507LA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235A-04), (1, DDXGT507LA9N, DM1, MAIN, J7J-0519-10, B, YJ5-235A-04), (1, DDXGT507LA9N, DM2, MAIN, J7J-0519-10, A, YJ5-235A-04), (1, DDXGT507LA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235A-04), (1, DDXGT507LA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235A-04), (1, DDXGT507LMN, DM1, AUDIO, J7J-0520-10, B, YJ5-235M-03), (1, DDXGT507LMN, DM2, AUDIO, J7J-0520-10, A, YJ5-235M-03), (2, DDXGT507LMN, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235M-03), (2, DDXGT507LMN, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235M-03), (1, DDXGT507LMN, DM1, MAIN, J7J-0519-10, B, YJ5-235M-03), (1, DDXGT507LMN, DM2, MAIN, J7J-0519-10, A, YJ5-235M-03), (1, DDXGT507LMN, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235M-03), (1, DDXGT507LMN, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235M-03), (1, DDXGT507RA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-235A-03), (1, DDXGT507RA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-235A-03), (2, DDXGT507RA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235A-03), (2, DDXGT507RA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235A-03), (1, DDXGT507RA9N, DM1, MAIN, J7J-0519-10, B, YJ5-235A-03), (1, DDXGT507RA9N, DM2, MAIN, J7J-0519-10, A, YJ5-235A-03), (1, DDXGT507RA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235A-03), (1, DDXGT507RA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235A-03), (1, DDXGT507RMN, DM1, AUDIO, J7J-0520-10, B, YJ5-235M-02), (1, DDXGT507RMN, DM2, AUDIO, J7J-0520-10, A, YJ5-235M-02), (2, DDXGT507RMN, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235M-02), (2, DDXGT507RMN, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235M-02), (1, DDXGT507RMN, DM1, MAIN, J7J-0519-10, B, YJ5-235M-02), (1, DDXGT507RMN, DM2, MAIN, J7J-0519-10, A, YJ5-235M-02), (1, DDXGT507RMN, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235M-02), (1, DDXGT507RMN, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235M-02), (1, DDXGT508LA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-235A-05), (1, DDXGT508LA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-235A-05), (2, DDXGT508LA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235A-05), (2, DDXGT508LA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235A-05), (1, DDXGT508LA9N, DM1, MAIN, J7J-0519-10, B, YJ5-235A-05), (1, DDXGT508LA9N, DM2, MAIN, J7J-0519-10, A, YJ5-235A-05), (1, DDXGT508LA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235A-05), (1, DDXGT508LA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235A-05), (1, DDXGT512LA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-235A-06), (1, DDXGT512LA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-235A-06), (2, DDXGT512LA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235A-06), (2, DDXGT512LA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235A-06), (1, DDXGT512LA9N, DM1, MAIN, J7J-0519-10, B, YJ5-235A-06), (1, DDXGT512LA9N, DM2, MAIN, J7J-0519-10, A, YJ5-235A-06), (1, DDXGT512LA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235A-06), (1, DDXGT512LA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235A-06), (1, DDXGT514RA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-235A-07), (1, DDXGT514RA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-235A-07), (2, DDXGT514RA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235A-07), (2, DDXGT514RA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235A-07), (1, DDXGT514RA9N, DM1, MAIN, J7J-0519-10, B, YJ5-235A-07), (1, DDXGT514RA9N, DM2, MAIN, J7J-0519-10, A, YJ5-235A-07), (1, DDXGT514RA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235A-07), (1, DDXGT514RA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235A-07), (1, DDXGT515LA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-235A-08), (1, DDXGT515LA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-235A-08), (2, DDXGT515LA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235A-08), (2, DDXGT515LA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235A-08), (1, DDXGT515LA9N, DM1, MAIN, J7J-0519-10, B, YJ5-235A-08), (1, DDXGT515LA9N, DM2, MAIN, J7J-0519-10, A, YJ5-235A-08), (1, DDXGT515LA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235A-08), (1, DDXGT515LA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235A-08), (1, DDXGT517RA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-235A-09), (1, DDXGT517RA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-235A-09), (2, DDXGT517RA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-235A-09), (2, DDXGT517RA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-235A-09), (1, DDXGT517RA9N, DM1, MAIN, J7J-0519-10, B, YJ5-235A-09), (1, DDXGT517RA9N, DM2, MAIN, J7J-0519-10, A, YJ5-235A-09), (1, DDXGT517RA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-235A-09), (1, DDXGT517RA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-235A-09), (1, DDXGT700R2A9N, DM1, AUDIO, J7J-0520-10, B, YJ5-224A-06), (1, DDXGT700R2A9N, DM2, AUDIO, J7J-0520-10, A, YJ5-224A-06), (2, DDXGT700R2A9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-224A-06), (2, DDXGT700R2A9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-224A-06), (1, DDXGT700R2A9N, DM1, MAIN, J7J-0519-10, B, YJ5-224A-06), (1, DDXGT700R2A9N, DM2, MAIN, J7J-0519-10, A, YJ5-224A-06), (1, DDXGT700R2A9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-224A-06), (1, DDXGT700R2A9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-224A-06), (1, DDXGT700R3A9N, DM1, AUDIO, J7J-0520-10, B, YJ5-224A-07), (1, DDXGT700R3A9N, DM2, AUDIO, J7J-0520-10, A, YJ5-224A-07), (2, DDXGT700R3A9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-224A-07), (2, DDXGT700R3A9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-224A-07), (1, DDXGT700R3A9N, DM1, MAIN, J7J-0519-10, B, YJ5-224A-07), (1, DDXGT700R3A9N, DM2, MAIN, J7J-0519-10, A, YJ5-224A-07), (1, DDXGT700R3A9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-224A-07), (1, DDXGT700R3A9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-224A-07), (1, DDXGT705LA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-224A-08), (1, DDXGT705LA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-224A-08), (2, DDXGT705LA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-224A-08), (2, DDXGT705LA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-224A-08), (1, DDXGT705LA9N, DM1, MAIN, J7J-0519-10, B, YJ5-224A-08), (1, DDXGT705LA9N, DM2, MAIN, J7J-0519-10, A, YJ5-224A-08), (1, DDXGT705LA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-224A-08), (1, DDXGT705LA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-224A-08), (1, DDXGT705LMN, DM1, AUDIO, J7J-0520-10, B, YJ5-224M-02), (1, DDXGT705LMN, DM2, AUDIO, J7J-0520-10, A, YJ5-224M-02), (2, DDXGT705LMN, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-224M-02), (2, DDXGT705LMN, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-224M-02), (1, DDXGT705LMN, DM1, MAIN, J7J-0519-10, B, YJ5-224M-02), (1, DDXGT705LMN, DM2, MAIN, J7J-0519-10, A, YJ5-224M-02), (1, DDXGT705LMN, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-224M-02), (1, DDXGT705LMN, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-224M-02), (1, DDXGT708LA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-224A-09), (1, DDXGT708LA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-224A-09), (2, DDXGT708LA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-224A-09), (2, DDXGT708LA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-224A-09), (1, DDXGT708LA9N, DM1, MAIN, J7J-0519-10, B, YJ5-224A-09), (1, DDXGT708LA9N, DM2, MAIN, J7J-0519-10, A, YJ5-224A-09), (1, DDXGT708LA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-224A-09), (1, DDXGT708LA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-224A-09), (1, DDXGT710RA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-224A-0A), (1, DDXGT710RA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-224A-0A), (2, DDXGT710RA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-224A-0A), (2, DDXGT710RA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-224A-0A), (1, DDXGT710RA9N, DM1, MAIN, J7J-0519-10, B, YJ5-224A-0A), (1, DDXGT710RA9N, DM2, MAIN, J7J-0519-10, A, YJ5-224A-0A), (1, DDXGT710RA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-224A-0A), (1, DDXGT710RA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-224A-0A), (1, DDXGT712LA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-224A-0B), (1, DDXGT712LA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-224A-0B), (2, DDXGT712LA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-224A-0B), (2, DDXGT712LA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-224A-0B), (1, DDXGT712LA9N, DM1, MAIN, J7J-0519-10, B, YJ5-224A-0B), (1, DDXGT712LA9N, DM2, MAIN, J7J-0519-10, A, YJ5-224A-0B), (1, DDXGT712LA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-224A-0B), (1, DDXGT712LA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-224A-0B), (1, DDXGT713RA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-224A-0C), (1, DDXGT713RA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-224A-0C), (2, DDXGT713RA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-224A-0C), (2, DDXGT713RA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-224A-0C), (1, DDXGT713RA9N, DM1, MAIN, J7J-0519-10, B, YJ5-224A-0C), (1, DDXGT713RA9N, DM2, MAIN, J7J-0519-10, A, YJ5-224A-0C), (1, DDXGT713RA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-224A-0C), (1, DDXGT713RA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-224A-0C), (1, DDXGT714RA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-224A-0D), (1, DDXGT714RA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-224A-0D), (2, DDXGT714RA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-224A-0D), (2, DDXGT714RA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-224A-0D), (1, DDXGT714RA9N, DM1, MAIN, J7J-0519-10, B, YJ5-224A-0D), (1, DDXGT714RA9N, DM2, MAIN, J7J-0519-10, A, YJ5-224A-0D), (1, DDXGT714RA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-224A-0D), (1, DDXGT714RA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-224A-0D), (1, DDXGT717RA9N, DM1, AUDIO, J7J-0520-10, B, YJ5-224A-0E), (1, DDXGT717RA9N, DM2, AUDIO, J7J-0520-10, A, YJ5-224A-0E), (2, DDXGT717RA9N, DM1, DIGITAL IO, J7J-0522-10, B, YJ5-224A-0E), (2, DDXGT717RA9N, DM2, DIGITAL IO, J7J-0522-10, A, YJ5-224A-0E), (1, DDXGT717RA9N, DM1, MAIN, J7J-0519-10, B, YJ5-224A-0E), (1, DDXGT717RA9N, DM2, MAIN, J7J-0519-10, A, YJ5-224A-0E), (1, DDXGT717RA9N, DM1, VIDEO UNIT, J7J-0521-10, A, YJ5-224A-0E), (1, DDXGT717RA9N, DM2, VIDEO UNIT, J7J-0521-10, B, YJ5-224A-0E), (2, DPXGH005MSMN, DM1, MAIN, J7J-0540-00, A, YJ5-226M-00), (2, DPXGH005MSMN, DM2, MAIN, J7J-0540-00, B, YJ5-226M-00), (1, DPXGH005MSMN, DM1, SWITCH, J7J-0539-00, B, YJ5-226M-00), (1, DPXGH005MSMN, DM2, SWITCH, J7J-0539-00, A, YJ5-226M-00), (2, DPXGH006MSMN, DM1, MAIN, J7J-0540-00, A, YJ5-226M-01), (2, DPXGH006MSMN, DM2, MAIN, J7J-0540-00, B, YJ5-226M-01), (1, DPXGH006MSMN, DM1, SWITCH, J7J-0539-00, B, YJ5-226M-01), (1, DPXGH006MSMN, DM2, SWITCH, J7J-0539-00, A, YJ5-226M-01), (2, DPXGT500RA9N, DM1, MAIN, J7K-0386-00, A, YJ5-215A-00), (2, DPXGT500RA9N, DM2, MAIN, J7K-0386-00, B, YJ5-215A-00), (1, DPXGT500RA9N, DM1, SWITCH, J7J-0532-00, A, YJ5-215A-00), (1, DPXGT500RA9N, DM2, SWITCH, J7J-0532-00, B, YJ5-215A-00), (8, DPXGT500RA9N, DM1, SWRC, , A, YJ5-215A-00), (8, DPXGT500RA9N, DM2, SWRC, , B, YJ5-215A-00), (2, DPXGT502LA9N, DM1, MAIN, J7K-0386-00, A, YJ5-215A-01), (2, DPXGT502LA9N, DM2, MAIN, J7K-0386-00, B, YJ5-215A-01), (1, DPXGT502LA9N, DM1, SWITCH, J7J-0532-00, A, YJ5-215A-01), (1, DPXGT502LA9N, DM2, SWITCH, J7J-0532-00, B, YJ5-215A-01), (8, DPXGT502LA9N, DM1, SWRC, , A, YJ5-215A-01), (8, DPXGT502LA9N, DM2, SWRC, , B, YJ5-215A-01), (2, DPXGT506LA9N, DM1, MAIN, J7K-0386-00, A, YJ5-215A-02), (2, DPXGT506LA9N, DM2, MAIN, J7K-0386-00, B, YJ5-215A-02), (1, DPXGT506LA9N, DM1, SWITCH, J7J-0532-00, A, YJ5-215A-02), (1, DPXGT506LA9N, DM2, SWITCH, J7J-0532-00, B, YJ5-215A-02), (8, DPXGT506LA9N, DM1, SWRC, , A, YJ5-215A-02), (8, DPXGT506LA9N, DM2, SWRC, , B, YJ5-215A-02), (2, DPXGT506RA9N, DM1, MAIN, J7K-0386-00, A, YJ5-215A-03), (2, DPXGT506RA9N, DM2, MAIN, J7K-0386-00, B, YJ5-215A-03), (1, DPXGT506RA9N, DM1, SWITCH, J7J-0532-00, A, YJ5-215A-03), (1, DPXGT506RA9N, DM2, SWITCH, J7J-0532-00, B, YJ5-215A-03), (8, DPXGT506RA9N, DM1, SWRC, , A, YJ5-215A-03), (8, DPXGT506RA9N, DM2, SWRC, , B, YJ5-215A-03), (8, DPXGT700RA9N, DM1, SWRC, , A, YJ5-214A-00), (8, DPXGT700RA9N, DM2, SWRC, , B, YJ5-214A-00), (8, DPXGT701RA9N, DM1, SWRC, , A, YJ5-214A-01), (8, DPXGT701RA9N, DM2, SWRC, , B, YJ5-214A-01), (2, DPXGT702LA9N, DM1, MAIN, J7K-0386-00, A, YJ5-214A-02), (2, DPXGT702LA9N, DM2, MAIN, J7K-0386-00, B, YJ5-214A-02), (1, DPXGT702LA9N, DM1, SWITCH, J7J-0532-00, A, YJ5-214A-02), (1, DPXGT702LA9N, DM2, SWITCH, J7J-0532-00, B, YJ5-214A-02), (8, DPXGT702LA9N, DM1, SWRC, , A, YJ5-214A-02), (8, DPXGT702LA9N, DM2, SWRC, , B, YJ5-214A-02), (2, DPXGT703L2A9N, DM1, MAIN, J7K-0386-00, A, YJ5-236A-01), (2, DPXGT703L2A9N, DM2, MAIN, J7K-0386-00, B, YJ5-236A-01), (1, DPXGT703L2A9N, DM1, SWITCH, J7J-0532-00, A, YJ5-236A-01), (1, DPXGT703L2A9N, DM2, SWITCH, J7J-0532-00, B, YJ5-236A-01), (8, DPXGT703L2A9N, DM1, SWRC, , A, YJ5-236A-01), (8, DPXGT703L2A9N, DM2, SWRC, , B, YJ5-236A-01), (8, DPXGT703LA9N, DM1, SWRC, , A, YJ5-214A-03), (8, DPXGT703LA9N, DM2, SWRC, , B, YJ5-214A-03), (2, DPXGT704RA9N, DM1, MAIN, J7K-0386-00, A, YJ5-214A-04), (2, DPXGT704RA9N, DM2, MAIN, J7K-0386-00, B, YJ5-214A-04), (1, DPXGT704RA9N, DM1, SWITCH, J7J-0532-00, A, YJ5-214A-04), (1, DPXGT704RA9N, DM2, SWITCH, J7J-0532-00, B, YJ5-214A-04), (8, DPXGT704RA9N, DM1, SWRC, , A, YJ5-214A-04), (8, DPXGT704RA9N, DM2, SWRC, , B, YJ5-214A-04), (8, DPXGT704RMN, DM1, SWRC, , A, YJ5-214M-06), (8, DPXGT704RMN, DM2, SWRC, , B, YJ5-214M-06), (2, DPXGT705LA9N, DM1, MAIN, J7K-0386-00, A, YJ5-214A-05), (2, DPXGT705LA9N, DM2, MAIN, J7K-0386-00, B, YJ5-214A-05), (1, DPXGT705LA9N, DM1, SWITCH, J7J-0532-00, A, YJ5-214A-05), (1, DPXGT705LA9N, DM2, SWITCH, J7J-0532-00, B, YJ5-214A-05), (8, DPXGT705LA9N, DM1, SWRC, , A, YJ5-214A-05), (8, DPXGT705LA9N, DM2, SWRC, , B, YJ5-214A-05), (8, DPXGT705LMN, DM1, SWRC, , A, YJ5-214M-07), (8, DPXGT705LMN, DM2, SWRC, , B, YJ5-214M-07), (2, DPXGT706LA9N, DM1, MAIN, J7K-0386-00, A, YJ5-236A-02), (2, DPXGT706LA9N, DM2, MAIN, J7K-0386-00, B, YJ5-236A-02), (1, DPXGT706LA9N, DM1, SWITCH, J7J-0532-00, A, YJ5-236A-02), (1, DPXGT706LA9N, DM2, SWITCH, J7J-0532-00, B, YJ5-236A-02))"


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
                        $masterModel->generateCode();
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
    		'code',
            'ynumber'
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
