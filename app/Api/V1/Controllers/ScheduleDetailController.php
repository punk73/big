<?php

namespace App\Api\V1\Controllers;
use App\Http\Controllers\Controller; //parent contoller
use Illuminate\Http\Request;
use App\Schedule;
use App\ScheduleDetail;
use App\Mastermodel;
use App\modelDetail;

class ScheduleDetailController extends Controller
{
    public function index(Request $request){
    	$limit = (isset($request->limit) && $request->limit != '' ) ? $request->limit : 25 ;
    	$models = ScheduleDetail::select([
            'schedule_details.id',
            'schedule_details.schedule_id',
            'schedule_details.lot_size',
            'schedule_details.code',
            'schedule_details.seq_start',
            'schedule_details.seq_end',
            'schedule_details.line',
            'schedule_details.start_serial',
            'schedule_details.prod_no',
            'schedule_details.rev_date',

            'models.code as model_code',
            'models.name as model_name',
            'models.pwbname',
            'models.cavity',
            'models.pwbno',
            'models.process',

            'model_details.code as detail_code',
        ])
        ->leftJoin('models', 'schedule_details.model', '=', 'models.name' )
        ->leftJoin('model_details', function($join){
            $join->on('model_details.model_id', '=', 'models.id');
            $join->on('model_details.prod_no', '=', 'schedule_details.prod_no');

        });    	
    	$models = $models->paginate($limit);
    	return $models;
    }

    public function process(Request $request){
    	$models = ScheduleDetail::
        leftJoin('models', 'schedule_details.model', '=', 'models.name' )
        ->leftJoin('model_details', function($join){
            $join->on('model_details.model_id', '=', 'models.id');
            $join->on('model_details.prod_no', '=', 'schedule_details.prod_no');
            $join->on('model_details.start_serial', '=', 'schedule_details.start_serial');
        })
        ->select([
            'schedule_details.id as id',
            'schedule_details.schedule_id',
            'schedule_details.lot_size',
            'schedule_details.code',
            'schedule_details.seq_start',
            'schedule_details.seq_end',
            'schedule_details.line',
            'schedule_details.prod_no as prod_no',
            'schedule_details.start_serial',

            'models.cavity as cavity',
            'models.id as model_id',
            'models.code as model_code',
            'models.name as model_name',
            'models.pwbname',
            'models.pwbno',
            'models.process',
            
            'model_details.id as model_detail_id',
            'model_details.code as detail_code',
        ]);     
        
        $models = $models->get();

        foreach ($models as $key => $model) {
            if ($model->detail_code == null) {
                // input ke model_details
                $modelDetail = modelDetail::firstOrNew([
                    'model_id' => $model->model_id ,
                    'prod_no' => $model->prod_no,
                    // karena nanti di cek dibawah
                ], [
                    'start_serial' => $model->start_serial, //ini ga boleh masuk ke where
                ]);

                //kalau pertama kesini
                if (!isset($modelDetail->id)) {
                    // return $model;
                    $modelDetail->counter = 1;
                    $modelDetail->start_serial = $model->start_serial;
                    $modelDetail->code = str_pad( 1 , 4, '0', STR_PAD_LEFT );
                    $modelDetail->save();

                }else{ //selain itu kesini

                    // disini harusnya di cek dulu start_serialnya sama atau engga. 
                    // kalau sama, ya jangan generate code lagi. kalau beda baru.
                    if ($model->start_serial !== $modelDetail->start_serial) {
                        # code...
                        $newModelDetail = new modelDetail;
                        $newModelDetail->counter = $modelDetail->counter++;
                        $newModelDetail->start_serial = $model->start_serial; //ini selalu ambil dari schedule
                        $newModelDetail->code = str_pad( $newModelDetail->counter , 4, '0', STR_PAD_LEFT );
                        $newModelDetail->save();
                    }

                    // cek modelDetail sudah punya code belum. kalau belum, ya isi.
                    if ($modelDetail->code == null) {
                        $modelDetail->start_serial = $model->start_serial; //ini selalu ambil dari schedule
                        $modelDetail->code = str_pad( $newModelDetail->counter , 4, '0', STR_PAD_LEFT );
                        $modelDetail->save(); 

                    }
                }

                if ($model->seq_start ==null) {
                    // return $model;
                    $schedule_details = ScheduleDetail::find($model->id);
                    $schedule_details->seq_start = $model->start_serial;
                    $schedule_details->seq_end = $model->start_serial + $model->lot_size;
                    $schedule_details->save();
                }
            }            
        }

        return [
            'count' => count($models),
            'data' => $models
        ];
    }

}
