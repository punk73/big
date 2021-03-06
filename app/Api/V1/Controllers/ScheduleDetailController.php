<?php

namespace App\Api\V1\Controllers;
use App\Http\Controllers\Controller; //parent contoller
use Illuminate\Http\Request;
use App\Schedule;
use App\ScheduleDetail;
use App\Mastermodel;
use App\modelDetail;
use App\Detail;
use App\ScheduleHistory;
use App\Api\V1\Controllers\CsvController;
use App\Api\V1\Controllers\ScheduleController;
use Dingo\Api\Exception\ResourceException;
use Illuminate\Database\QueryException;
use Dingo\Api\Exception\UpdateResourceFailedException;
use App\Api\V1\Requests\ScheduleDetailRequest;
use App\Api\V1\Requests\ScheduleDetailProcessRequest;
use App\Subtype;
use Validator;
use File;
use Storage;
use App\User;
use DB;

class ScheduleDetailController extends Controller
{   
    /*NAME OF GENERATED FILE*/
    protected $board_filename = 'board_id_schedule_';
    protected $cavity_filename = 'cavity_id_schedule_';
    protected $schedule_filename = 'schedule_code_';
    protected $subtypeCode = '_';

    public function index(Request $request){
        $scheduleId = $this->getLatestScheduleId();

    	$limit = (isset($request->limit) && $request->limit != '' ) ? $request->limit : 25 ;
        $models = ScheduleDetail::where(function ($q) use($scheduleId) {
            $q->where('schedule_id', $scheduleId );
            $q->orWhere('schedule_id', null );
        });

        $allReq = $request->all();
        $allowedParam = $this->getTableColumns('schedule_details');
        
        foreach ($allReq as $key => $req) {
            if ($request->has($key) && in_array($key, $allowedParam )  ) {
                $models = $models->where($key,'like','%'.$req.'%');
            }
        }
        
         if ($request->has('code') ) {
                # code...
                $code = $request->code;

                //cek if code <= 5 character. search di model.code

                // substr(string, start, length )
                $modelCode = substr($code, 0, 11); //ambil dari index 0, sebanyak 5 karakter.
                
                $subtypeCode = substr($code, 11, 1); // _ or N or whatever;

                $cavityCode = substr($code, 12, 2 );
                $cavityCode = (int) $cavityCode;

                $sideCode = substr($code, 14,1);
                
                $countryCode = substr($code, 15, 1);
                
                $lotNo = substr($code, 16, 4);
                
                $seqNo = substr($code, 20,4);
                $seqNo = $seqNo;

                /*return [
                    'model_code' => $modelCode,
                    'subtypeCode' => $subtypeCode,
                    'country_code' => $countryCode,
                    'side_code' => $sideCode,
                    'cavity_code' => $cavityCode,
                    'lot_no' => $lotNo,
                    'seq_no' => $seqNo,
                ];*/

                if ($modelCode) {
                    $models = $models->where('model_code', 'like', $modelCode.'%' );
                }

                //country code diabaikan 

                if ($sideCode) {
                    $models = $models->where('side', 'like', $sideCode.'%' );
                }

                if ($cavityCode) {
                    
                    $models = $models->where('cavity', '>=', $cavityCode );
                    // return $models->toSql();
                }

                if ($lotNo) {
                    // ambil dari property si table schedule_details
                    $models = $models->where('prod_no', 'like','%'.$lotNo.'%' );
                }

                if ($seqNo) {
                    // ambil dari property si table schedule_details
                    // ambil yang seq start >= parameter && seq_end <= parameter
                    // kenapa? karena kita bandingkan column nya dengan value, bkn sebaliknya.

                    $models = $models
                    ->where('seq_start', '<=', $seqNo )
                    ->where('seq_end', '>=', $seqNo );

                }
                // $models = $models->where('rev_date','like','%'.$request->rev_date.'%');
            }  

    	$models = $models
        ->orderBy('id', 'desc')
        ->paginate($limit)
        ->appends( $request->query());;
    	return $models;
    }

    public function process(ScheduleDetailProcessRequest $request){
        // cek apakah sudah di generate sebelumnya.
        if ( $this->isGenerated() ) {
            /*return [
                'message' => 'Schedule Code Already Generated or Schedule Not found!'
            ]; //sudah di generate semua.*/
            throw new UpdateResourceFailedException("Schedule Code Already Generated or Schedule Not found!", [
                'schedule_code' => 'Schedule Code Already Generated or Schedule Not found!',
            ] );
            
        }
        
        // jika tidak punya schedule id, maka masuk sini.
        if ($this->hasNoScheduleId() ) {
            // input into schedule header;
            $scheduleController = new ScheduleController;
            $schedule = $scheduleController->store($request);

            // update schedule_id into schedule_details;
            ScheduleDetail::select()->update([
                'schedule_id' => $schedule['data']['id']
            ]);

            // $scheduleDetail = ScheduleDetail::select()->get();
            // return $scheduleDetail;
        }
        
        $isSuccess = true;
        try {
            //code...
            $schedules = DB::select(' exec generate_bigs_code');
        } catch ( QueryException $th) {
            $isSuccess = false;
            $schedules = [];
            return [
                'success' => $isSuccess,
                'count' => count( $schedules),
                'data'  => $schedules,
            ];
        }

        return [
            'success' => $isSuccess,
            'count' => count( $schedules),
            'data'  => $schedules,
        ];

        // copy schedule_details into history
    }

    public function upload(ScheduleDetailRequest $request){
        if ($request->hasFile('file')) {

            # kalau bukan csv atau txt, return false;
            $dataType = $request->file('file')->getClientOriginalExtension();

            if ($dataType == 'csv' || $dataType == 'txt' ) {
                //yg boleh masuk csv & txt saja
            }else{
                return [
                    'error' =>[ 
                        'message' => 'you need to upload csv file!',
                        'data' => $request->file('file')->getClientOriginalExtension()
                    ]
                ];
            }

            $file = $request->file('file');
            $name = time() . '-' . $file->getClientOriginalName();
            $path = storage_path('schedules');
            
            $file->move($path, $name); //pindah ke file server;
            
            // return [$file, $path, $name ];
            $fullname = $path .'\\'. $name ;
            $csv = new CsvController;
            $importedCsv = $csv->csvToArray($fullname);
            // return [$fullname, $importedCsv, count($importedCsv)];
            if ($importedCsv) { //kalau something wrong ini bakal bernilai false
                
                // bersihkan schedule detail
                $directory = storage_path(). '\\public\\code\\';
                Storage::deleteDirectory($directory);
                // return $directory;
                ScheduleDetail::truncate(); //truncate table schedules
                
                $newSchedule = [];
                for ($i = 0; $i < count($importedCsv); $i ++)
                {
                    // first parameter is data to check, second is data to input
                    // it'll be deleted soon;
                    
                    // $line = $importedCsv[$i]['line'];
                    // $model = $importedCsv[$i]['model'];
                    // $pwbNo = $importedCsv[$i]['pwbno'];
                    // $pwbName = $importedCsv[$i]['pwbname'];
                    // $process = $importedCsv[$i]['process'];
                    // $prodNo = $importedCsv[$i]['prod_no'];
                    // $startSerial = $importedCsv[$i]['start_serial'];
                    // $lotSize = $importedCsv[$i]['lot_size'];
                    // $cavity = (isset($importedCsv[$i]['cavity'])) ? $importedCsv[$i]['lot_size'] : null ;
                    // $qty = (isset($importedCsv[$i]['qty'])) ? (int) $importedCsv[$i]['qty'] : $lotSize ;
                    
                    // add rev date;
                    $importedCsv[$i]['rev_date'] = date('Y-m-d');
                    // return $importedCsv[$i];
                    unset( $importedCsv[$i]['Plan date']);
                    // return $importedCsv[$i];
                    // kalau qty 0 gausah diinput ke schedule.
                    if ($importedCsv[$i]['qty'] > 0 ) {
                        # code...
                        $newSchedule[]=$importedCsv[$i];
                    }

                    if (count($newSchedule) == 5 || $i == (count($importedCsv)-1) ) {
                        
                        //insert into db
                        ScheduleDetail::insert($newSchedule);
                        // tiap 5, input database.
                        $newSchedule = []; //reset array
                    }
                }

            }

            return [
                'success' => true,
                'message' => 'Good!!'
            ];
            // return true;
        }
    }

    public function runProcess($schedules, $self, $masterScheduleId){
        $arraySchedule = [];
        
        foreach ($schedules as $key => $schedule) {
            //cek model sudah ada belum,
            
            $name = $schedule->model;
            $pwbno = $schedule->pwbno;
            $pwbname = $schedule->pwbname;
            $process = $schedule->process;
            $ynumber = $schedule->ynumber;

            $masterModel = Mastermodel::firstOrNew([
                'name' => $name,
                'pwbno' => $pwbno,
                'pwbname' => $pwbname,
                'process' => $process,
                'ynumber' => $ynumber, 
            ]);

            if(isset($schedule->side)){
                $masterModel->side = $schedule->side;
            }

            if(isset($schedule->cavity)){
                $masterModel->cavity = $schedule->cavity;
            }

            if (!$masterModel->exists) {
                #kalau belum ada aja di save nya. gausah update.
                $masterModel->generateCode();
                $masterModel->save();
            }

            // update schedule 
            if($masterModel->code != null){
                $schedule->model_code = $masterModel->code;
            }
            
            // cek model details sudah ada apa belum
            $modelDetail = modelDetail::firstOrNew([
                'model_id'=> $masterModel->id,
                'prod_no' => $schedule->prod_no 
            ]);

            if(!$modelDetail->exists ){
                //model detail is new, not exists before
                // codingnya ada di class model nya
                $modelDetail->generateCode();
                //save model details
                $modelDetail->save();
            }

            // cek details sudah ada belum.
            $detail = Detail::orderBy('id', 'desc' )->firstOrNew([
                'model_detail_id' => $modelDetail->id,
                'start_serial' => $schedule->start_serial,
                'lot_size' => $schedule->lot_size,
                'schedule_id' => $masterScheduleId,
            ]);

            // cek apakah sudah ada sebelumnya, kalau belum ada, input. 
            if ($detail->seq_start == null) {
                # add new
                // kalau belum ada, ya tambah
                if ($schedule->qty != 0) {
                    # code...
                    $detail->qty = $schedule->qty;
                    $detail->seq_start = $self->toHexa(1);
                    // seq end dikurang satu karena hitungan pertama itu diitung. 
                    $seq_end = $self->toDecimal($detail->seq_start) + ($schedule->qty - 1);
                    $detail->seq_end = $self->toHexa($seq_end) ;

                    $detail->save();

                    // update value schedule
                    $schedule->seq_start = $detail->seq_start;
                    $schedule->seq_end = $detail->seq_end;
                    // $schedule->save();
                }
            }else {
                if ($schedule->qty != 0) {
                    //yg masuk kesini, artinya yang schedulenya dipecah. satu prod number, tp schedule 
                    //nya dipisah pisah. that's why seq start nya ambil dari seq end record sebelumnya.
                    
                    // harus cek dulu apakah ini value nya beda atau memang data yg sebelumnya.
                    // sudah ada sebelumnya. jadi seq_start nya harus tambah dari counter sebelumnya.
                    $newSeqStart = $self->toHexa( $self->toDecimal($detail->seq_end) + 1 );
                    $newSeqEnd = $self->toHexa( $self->toDecimal( $newSeqStart ) + ($schedule->qty - 1) );

                    $newDetail = Detail::orderBy('id', 'desc' )->firstOrNew([
                        'model_detail_id' => $modelDetail->id,
                        'start_serial' => $schedule->start_serial,
                        'lot_size' => $schedule->lot_size,
                        'qty' => $schedule->qty,
                        'seq_start' => $newSeqStart , //it's already hexadecimal
                        'seq_end' => $newSeqEnd,
                        'schedule_id' => $masterScheduleId,
                    ]);

                    $newDetail->save();

                    // update value schedule
                    $schedule->seq_start = $newDetail->seq_start;
                    $schedule->seq_end = $newDetail->seq_end;
                    
                }
            }

            // update every changes in schedule here.
            if ($masterModel->code != null) {
                # code...
                $schedule->model_code = $masterModel->code;
                $schedule->cavity = $masterModel->cavity;
                $schedule->side = $masterModel->side;
            }
            //assign model_detail code into schedule;
            if ($modelDetail->code!=null) {
                $schedule->prod_no_code = $modelDetail->code;
            }
            // save changes to schedule details table
            $schedule->save();

            // input schedule to history. parse object into array
            $newHistorySchedule = json_decode(json_encode($schedule), true);
            // filter schedule so that only contain shcedule data.
            $newHistorySchedule = $this->filterSchedule($newHistorySchedule);
            // assign into array schedule,
            // kalau belum ada, insert into database
            // ScheduleHistory::firstOrCreate($newHistorySchedule);
            
            $arraySchedule[] = $newHistorySchedule;
            // if array schedule contain 50 records, send it to db.
            if (count($arraySchedule) == 50 || $key == (count($schedules)-1) ) {
                # code...
                // insert into table history
                ScheduleHistory::insert($arraySchedule);
                //reset array schedule
                $arraySchedule = [];
            }
        }
    }

    public function getUngeneratedCode(){
        return $this->getJoinedSchedule()
        ->where('models.code', '=', null ) //cek yang code nya masih null
        ->orWhere('model_details.code', null ) //cek yang prod_no code nya masih null
        ->orWhere('schedule_details.seq_start', null); //cek yg blm ada seq start & seq end nya.
    }

    // function ini dipakai di function process.
    private function isGenerated(){
        $ungenerated = ScheduleDetail::where(function($q){
            $q->whereNull('model_code');
            $q->orWhereNull('prod_no_code');
            $q->orWhereNull('seq_start');
            $q->orWhereNull('seq_end');
        })->exists();

        return !$ungenerated;
    }

    public function preprocess(){

        $isGenerated = $this->isGenerated();

        $message = ($isGenerated) ? 'Already Generated' : 'ready to process';

        return [
            'is_generated'=> $isGenerated,
            // 'count' => $this->getUngeneratedCode()->count('*'),
            'message'=> $message
        ];
    }

    // function ini dipakai di function process.
    private function hasNoScheduleId(){
        return ScheduleDetail::where('schedule_id','=', null )->exists();
    }

    //get latest schedule id
    private function getLatestScheduleId(){
        $masterSchedule = Schedule::select('id')->orderBy('id','desc')->first();
        if ($masterSchedule!=null) {
            return $masterSchedule->id;
        }else{
            return null;
        }
    }

    private function getJoinedSchedule(){
        

        $schedule = ScheduleDetail::select([
            'schedule_details.*',

            //models
            'models.id as models_id',
            'models.name as models_name',
            'models.pwbname as models_pwbname',
            'models.pwbno as models_pwbno',
            'models.process as models_process',
            'models.cavity as models_cavity',
            'models.side as models_side',
            'models.code as models_code',
            

            // model_details
            'model_details.code as model_details_code',
            'model_details.prod_no as model_details_prod_no',

            // details
            'details.start_serial as details_start_serial',
            'details.lot_size as details_lot_size',
            'details.seq_start as details_seq_start',
            'details.seq_end as details_seq_end',
            'details.qty as details_qty',
            'details.schedule_id as details_schedule_id',

        ])
        ->leftJoin('models', function($join){
            $join->on('schedule_details.model', '=', 'models.name');
            $join->on('schedule_details.pwbname', '=', 'models.pwbname');
            $join->on('schedule_details.pwbno', '=', 'models.pwbno');
            $join->on('schedule_details.process', '=', 'models.process');
        })
        ->leftJoin('model_details', function ($join){
            $join->on('models.id', '=', 'model_details.model_id');
            $join->on('schedule_details.prod_no', '=', 'model_details.prod_no');
        })
        ->leftJoin('details', function ($join){
            $join->on('model_details.id','=','details.model_detail_id');
            $join->on('schedule_details.start_serial','=','details.start_serial');
            $join->on('schedule_details.lot_size','=','details.lot_size');
            $join->on( 'schedule_details.qty','=', 'details.qty');
            $join->on( 'schedule_details.seq_start','=', 'details.seq_start');
            $join->on( 'schedule_details.seq_end','=', 'details.seq_end');
        });

        
        return $schedule;
    }

    // function ini dipakai di function process.
    private function filterSchedule(array $schedule){
        $allowed = [
            // 'id',
            'schedule_id',
            'lot_size',
            'model_code',
            'prod_no_code',
            'side',
            'cavity',
            'seq_start',
            'seq_end',
            'line',
            'start_serial',
            'model',
            'pwbname',
            'pwbno',
            'prod_no',
            'process',
            'rev_date',
            'qty',
            'ynumber',
        ];

        foreach ($schedule as $key => $value) {
            if (!in_array($key, $allowed)) {
                unset($schedule[$key]);    
            }
            // make sure semua integer, ttp jadi integer.
            if ($key == 'schedule_id' || $key == 'start_serial' || $key == 'qty') {
                $schedule[$key] = (int) $value;
            }
        }

        return $schedule;
    }

    public function download(Request $request, $id){
        $schedule = $this->getJoinedSchedule()
        ->where('schedule_details.id', $id )
        ->first();

        if ($schedule != null) {
            // rubah object jadi array
            $arraySchedule = json_decode(json_encode($schedule), true);
            // return $arraySchedule;
            $rules = [];
            // buat rule. semua data harus not null. kalau null, artinya belum di generate.
            foreach ($arraySchedule as $key => $value) {
                if ($key == 'created_at' || $key == 'side' || $key == 'cavity' || $key == 'regenerate' ) {
                    continue;
                }
                $rules[$key]= ['required'];
            }
            // buat validator dari rule tsb.
            $validator = Validator::make($arraySchedule, $rules );

            //cek disini apakah data sudah di generate atau belum. kalau belum, return false;
            if ($validator->fails() ) {
                throw new ResourceException("TOLONG KLIK TOMBOL PROSES DULU !!", $validator->errors() );
            }

            if ($request->regenerate != null && $request->regenerate == 'true') {
                $this->deleteGeneratedFile($id);

            }

            // make file here.
            $generatedType = $request->generated_type;

            if ($generatedType != null && $generatedType != '' ) {
                // cek generate type board_id or cavity id;
                // cek apakah file sudah ada. kalau ada, langsung ambil.
                $path = '\\public\\code\\';
                
                $filename = $arraySchedule['models_name'] .'_'. $arraySchedule['ynumber'] .'_'. $arraySchedule['models_pwbname'] .'_'. $arraySchedule['models_pwbno'] .'_'. $arraySchedule['models_process'] .'_'. $arraySchedule['models_side'] . '_';

                if ($generatedType == 'board_id') {
                    $filename .= $this->board_filename . $id . '.txt';
                    $fullpath = $path . $filename;

                    if (!Storage::exists($fullpath)){
                        // kalau belum, generate

                        //generate file
                        //generate board id nya aja. (cavity nya = 00)
                        $content = $this->generateCode($generatedType, $schedule);

                        //save to Storage
                        Storage::put($fullpath, $content );    
                        // return Storage::download($fullpath);
                    }
                }else if($generatedType == 'cavity_id'){
                    
                    //generate cavity id;
                    $filename .= $this->cavity_filename . $id . '.txt';
                    $fullpath = $path . $filename;

                    if (!Storage::exists($fullpath)) {
                        $content = $this->generateCode($generatedType, $schedule );
                        // save to storage;
                        Storage::put($fullpath, $content );    
                    }
                }else {

                    // ini yang all
                    $filename .= $this->schedule_filename .$id.'.txt';
                    $fullpath = $path.$filename;

                    if (!Storage::exists($fullpath)) {
                        
                        $content = $this->generateCode($generatedType, $schedule );
                        // save to storage;
                        Storage::put($fullpath, $content );    
                    }
                }

                $headers = [
                    'Content-type'=>'text/plain', 
                    'test'=>'YoYo', 
                    'Content-Disposition'=>sprintf('attachment; filename="%s"', $filename),
                    'X-BooYAH'=>'WorkyWorky',
                    'Content-Length'=>sizeof($arraySchedule)
                ];

                return response()->download(storage_path("app/".$path."/{$filename}"), $filename , $headers );

            }
        }
    }

    public function generateCode($generatedType='board_id', $schedule ){

        $modelCode = $schedule->model_code;
        $countryCode = '7';
        $cavity = $schedule->models_cavity;
        $side = $schedule->models_side;
        $lotNo = $schedule->prod_no_code;
        $seqStart = $this->toDecimal($schedule->seq_start);
        $seqEnd = $this->toDecimal($schedule->seq_end);

        $content = '';
        if($generatedType == 'board_id'){
            $cavityCode='00';
            for ($i= $seqStart; $i <= $seqEnd; $i++) { 
              // code dibawah ini untuk padding. kalau $i == 1. jadi 001; dan seterusnya
              $seqNo = str_pad( $this->toHexa($i) , 3, '0', STR_PAD_LEFT );
              $content .= $modelCode . $this->subtypeCode . $cavityCode . $side . $countryCode   .  $lotNo . $seqNo.PHP_EOL;
            }
        
        }else if($generatedType == 'cavity_id'){
            // get subtypes
            $subtypes = Subtype::select(['name'])->where('model_id', $schedule['models_id'] );
            $subtypeIsExists = $subtypes->exists();
            $subtypes = $subtypes->get();
            
            for ($j=$seqStart; $j <= $seqEnd ; $j++) {     
                for ($i=1; $i <= $cavity ; $i++) { 
                    // kalau subtypes kosong
                    if(!$subtypeIsExists){
                        $cavityCode = str_pad( $i , 2, '0', STR_PAD_LEFT );
                        $seqNo = str_pad( $this->toHexa($j) , 3, '0', STR_PAD_LEFT );
                        $content .= $modelCode . $this->subtypeCode . $cavityCode . $side . $countryCode   .  $lotNo . $seqNo.PHP_EOL;
                    }else{
                        for ($i=0; $i < count($subtypes) ; $i++) {
                            $this->subtypeCode = $subtypes[$i]['name'];
                            $cavityCode = str_pad( $i , 2, '0', STR_PAD_LEFT );
                            $seqNo = str_pad( $this->toHexa($j) , 3, '0', STR_PAD_LEFT );
                            $content .= $modelCode . $this->subtypeCode . $cavityCode . $side . $countryCode   .  $lotNo . $seqNo.PHP_EOL;
                        }
                    }
                }
            }
        }
        else {
            // All;
            $subtypes = Subtype::select(['name'])->where('model_id', $schedule['models_id'] );
            $subtypeIsExists = $subtypes->exists();
            $subtypes = $subtypes->get();

            for ($i=$seqStart; $i <= $seqEnd ; $i++) { 
                for ($cav=0; $cav <= $cavity ; $cav++) { 
                    $cavityCode = str_pad( $cav , 2, '0', STR_PAD_LEFT );
                    $seqNo = str_pad( $this->toHexa($i) , 3, '0', STR_PAD_LEFT );
                    
                    if(!$subtypeIsExists){
                        $content .= $modelCode . $this->subtypeCode . $cavityCode . $side . $countryCode   .  $lotNo . $seqNo.PHP_EOL;
                    }else {
                        for ($l=0; $l < count($subtypes) ; $l++) { 
                            $this->subtypeCode = $subtypes[$l]['name'];
                            
                            if( $cav == '00' ){ //kalau cavity == 0; artinya parent, dan parent harus selalu pake "_"
                                $this->subtypeCode = '_';    
                            }

                            $content .= $modelCode . $this->subtypeCode . $cavityCode . $side . $countryCode   .  $lotNo . $seqNo.PHP_EOL;
                                
                        }
                    }

                }
            }

        }

        return $content;
    }

    /*
    * due to changing on requirement, toHexa method is no longer do what it does
    * it just changing int into string with 0 prefix
    * if $no == 1, then it would return '0001'
    */

    private function toHexa($no){
        return str_pad( $no , 4, '0', STR_PAD_LEFT );
    }

    /*
    * to decimal is converting string into decimal
    * exp: '0001' will return 1;
    *
    */

    private function toDecimal($string){
        return (int) $string;
    }

    // dipakai juga di modelController
    public function deleteGeneratedFile($id){
        $schedules = ScheduleDetail::find($id);
        $directory = '\\public\\code\\';
        
        $board = $this->board_filename . $id . '.txt';
        $cav = $this->cavity_filename . $id . '.txt';
        $schedulefile = $this->schedule_filename . $id . '.txt';
        
        $boardname = $directory . $board;
        $cavName = $directory . $cav;
        $schedulefilename = $directory .$schedulefile;

        Storage::delete([
            $boardname,
            $cavName,
            $schedulefilename,
        ]);
    }

    public function downloadSchedule(){
        $do = ScheduleDetail::get();
        
        // return $model;

        $fname = 'Schedule.csv';

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=$fname");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        $fp = fopen("php://output", "w");
        
        $headers = 'id,schedule_id,lot_size,model_code,prod_no_code,side,cavity,seq_start,seq_end,line,start_serial,model,pwbname,pwbno,prod_no,process,rev_date,qty,created_at,updated_at'."\n";

        fwrite($fp,$headers);

        foreach ($do as $key => $value) {
            # code...
            $row = [
                $value->id,
                $value->schedule_id,
                $value->lot_size,
                $value->model_code,
                $value->prod_no_code,
                $value->side,
                $value->cavity,
                $value->seq_start,
                $value->seq_end,
                $value->line,
                $value->start_serial,
                $value->model,
                $value->pwbname,
                $value->pwbno,
                $value->prod_no,
                $value->process,
                $value->rev_date,
                $value->qty,
                $value->created_at,
                $value->updated_at,
            ];
            
            fputcsv($fp, $row);
        }

        fclose($fp);
    }

    public function test(){
        $masterModel = Mastermodel::first();
        $masterModel->initCode();
        return $masterModel;
    }

    public function getTableColumns($table)
    {
        return DB::getSchemaBuilder()->getColumnListing($table);
    }

}
