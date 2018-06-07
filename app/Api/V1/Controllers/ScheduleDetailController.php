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

class ScheduleDetailController extends Controller
{
    public function index(Request $request){
    	$limit = (isset($request->limit) && $request->limit != '' ) ? $request->limit : 25 ;
        $models = $this->getJoinedSchedule();    	

        /*Search Query*/
            if ($request->name != null && $request->name != '' ) {
                # code...
                $models = $models->where('model','like','%'.$request->name.'%');
            }

            if ($request->pwbno != null && $request->pwbno != '' ) {
                # code...
                $models = $models->where('pwbno','like','%'.$request->pwbno.'%');
            }

            if ($request->pwbname != null && $request->pwbname != '' ) {
                # code...
                $models = $models->where('pwbname','like', $request->pwbname.'%');
            }

            if ($request->process != null && $request->process != '' ) {
                # code...
                $models = $models->where('process','like','%'.$request->process.'%');
            }

            if ($request->lot_size != null && $request->lot_size != '' ) {
                # code...
                $models = $models->where('lot_size','like','%'.$request->lot_size.'%');
            }

            if ($request->seq_start != null && $request->seq_start != '' ) {
                # code...
                $models = $models->where('seq_start','like','%'.$request->seq_start.'%');
            }

            if ($request->seq_end != null && $request->seq_end != '' ) {
                # code...
                $models = $models->where('seq_end','like','%'.$request->seq_end.'%');
            }

            if ($request->line != null && $request->line != '' ) {
                # code...
                $models = $models->where('line','like','%'.$request->line.'%');
            }

            if ($request->rev_date != null && $request->rev_date != '' ) {
                # code...
                $models = $models->where('rev_date','like','%'.$request->rev_date.'%');
            }

            if ($request->code != null && $request->code != '' ) {
                # code...
                $code = $request->code;

                //cek if code <= 5 character. search di model.code

                // substr(string, start, length )
                $modelCode = substr($code, 0, 5); //ambil dari index 0, sebanyak 5 karakter.
                //char 6th must be i as country code
                $countryCode = substr($code, 5, 1);
                //7 must be A or B
                $sideCode = substr($code, 6,1);
                //char 8-9 cavity. if model  still has no cavity, then 
                $cavityCode = substr($code, 7, 2);
                $cavityCode = (int) $cavityCode;
                //10-12 lot number
                $lotNo = substr($code, 9, 3);
                //13-15 seq number
                $seqNo = substr($code, 12,3);
                $seqNo = (int) $seqNo;

                // return [
                //     'model_code' => $modelCode,
                //     'country_code' => $countryCode,
                //     'side_code' => $sideCode,
                //     'cavity_code' => $cavityCode,
                //     'lot_no' => $lotNo,
                //     'seq_no' => $seqNo,
                // ];

                if ($modelCode) {
                    $models = $models->where('model_code', 'like', $modelCode.'%' );
                }

                //country code diabaikan 

                if ($sideCode) {
                    $models = $models->where('models.side', 'like', $sideCode.'%' );
                }

                if ($cavityCode) {
                    
                    $models = $models->where('models.cavity', '=', $cavityCode );
                    // return $models->toSql();
                }

                if ($lotNo) {
                    // ambil dari property si table schedule_details
                    $models = $models->where('prod_no_code', '<=', $lotNo );
                }

                if ($seqNo) {
                    // ambil dari property si table schedule_details
                    // ambil yang seq start >= parameter && seq_end <= parameter
                    // kenapa? karena kita bandingkan column nya dengan value, bkn sebaliknya.

                    $models = $models
                    ->where('details.seq_start', '<=', $seqNo )
                    ->where('details.seq_end', '>=', $seqNo );

                }
                // $models = $models->where('rev_date','like','%'.$request->rev_date.'%');
            }            
        /*End Search*/

    	$models = $models->paginate($limit);
    	return $models;
    }

    public function process(Request $request){
        // cek apakah sudah di generate sebelumnya.
        if ( $this->isGenerated() ) {
            return [
                'message' => 'Schedule Code Already Generated or Schedule Not found!'
            ]; //sudah di generate semua.
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
        
        // generate code
        $scheduleDetail = $this->getJoinedSchedule()
        ->where('schedule_details.seq_start', null )
        ->where('schedule_details.qty', '>', 0 )
        
        // ->get();

        // $result = [];
        // foreach ($scheduleDetail as $key => $schedule) {
        //     $schedule = json_decode(json_encode($schedule), true);
        //     $result[] = $this->filterSchedule($schedule);
        // }

        // return $result;

        ->chunk(300, function ($schedules){
            // for each disini, isi table yg dibawah bawahnya.
            $arraySchedule = [];
            foreach ($schedules as $key => $schedule) {
                //cek model sudah ada belum,
                
                $name = $schedule->model;
                $pwbno = $schedule->pwbno;
                $pwbname = $schedule->pwbname;
                $process = $schedule->process;

                $masterModel = Mastermodel::firstOrNew([
                    'name' => $name,
                    'pwbno' => $pwbno,
                    'pwbname' => $pwbname,
                    'process' => $process,
                ]);  

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
                    $modelDetail->generateCode( $masterModel->id );
                    //save model details
                    $modelDetail->save();
                }

                // cek details sudah ada belum.
                $detail = Detail::orderBy('id', 'desc' )->firstOrNew([
                    'model_detail_id' => $modelDetail->id,
                    'start_serial' => $schedule->start_serial,
                    'lot_size' => $schedule->lot_size,
                ]);

                // cek apakah sudah ada sebelumnya, kalau belum ada, input. 
                if ($detail->seq_start == null) {
                    # add new
                    // kalau belum ada, ya tambah
                    if ($schedule->qty != 0) {
                        # code...
                        $detail->qty = $schedule->qty;
                        $detail->seq_start = 1;
                        $detail->seq_end = $detail->seq_start + ($schedule->qty - 1); 

                        $detail->save();

                        // update value schedule
                        $schedule->seq_start = $detail->seq_start;
                        $schedule->seq_end = $detail->seq_end;
                        // $schedule->save();
                    }
                }else {
                    // sudah ada sebelumnya. jadi seq_start nya harus tambah dari counter sebelumnya.
                    if ($schedule->qty != 0) {
                        //yg masuk kesini, artinya yang schedulenya dipecah. satu prod number, tp schedule 
                        //nya dipisah pisah. that's why seq start nya ambil dari seq end record sebelumnya.
                        $newDetail = Detail::firstOrNew([
                            'model_detail_id' => $modelDetail->id,
                            'start_serial' => $schedule->start_serial,
                            'lot_size' => $schedule->lot_size,
                            'qty' => $schedule->qty,
                            'seq_start' => $detail->seq_end,
                            'seq_end' => $detail->seq_end + ($schedule->qty - 1)
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

            // changes object to array;
        });

        return [
            'count' => count($scheduleDetail),
            'data'  => $scheduleDetail
        ];

        // copy schedule_details into history
    }

    public function upload(Request $request){
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

        return [
            'message' => 'no file found'
        ];
    }

    // function ini dipakai di function process.
    private function isGenerated(){
        // it'll return true or false, based on is there any schedule that has no code yet.

        $ungeneratedSchedule = $this->getJoinedSchedule()
        ->where('models.code', '=', null ) //cek yang code nya masih null
        ->orWhere('model_details.code', null ) //cek yang prod_no code nya masih null
        ->orWhere('schedule_details.seq_start', null) //cek yg blm ada seq start & seq end nya.
        ->exists();

        return !$ungeneratedSchedule; //kalau ini berisi, artinya masih ada yang belum di generate
        //artinya harusnya ini return null, atau sudah tidak ada.
    }

    public function preprocess(){
        $isGenerated = $this->isGenerated();

        $message = ($isGenerated) ? 'Already Generated' : 'ready to process';

        return [
            'is_generated'=> $isGenerated,
            'message'=> $message
        ];
    }

    // function ini dipakai di function process.
    private function hasNoScheduleId(){
        return ScheduleDetail::where('schedule_id','=', null )->exists();
    }

    private function getJoinedSchedule(){
        $schedule = ScheduleDetail::select([
            'schedule_details.*',

            //models
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
            $join->on( 'schedule_details.start_serial','=', 'details.start_serial');
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


}
