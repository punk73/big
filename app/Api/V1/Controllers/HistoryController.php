<?php

namespace App\Api\V1\Controllers;
use App\Http\Controllers\Controller; //parent contoller
use Illuminate\Http\Request;
use App\Schedule;
use App\ScheduleHistory;
use App\Mastermodel;
use App\modelDetail;

class HistoryController extends Controller
{
    public function index(Request $request){
    	$limit = (isset($request->limit) && $request->limit != '' ) ? $request->limit : 25 ;
    	$models = ScheduleHistory::select([
            'schedule_histories.id',
            'schedule_histories.schedule_id',
            'schedule_histories.lot_size',
            // 'schedule_histories.code',
            'schedule_histories.seq_start',
            'schedule_histories.seq_end',
            'schedule_histories.line',
            'schedule_histories.start_serial',
            'schedule_histories.prod_no',
            'schedule_histories.rev_date',

            'models.code as model_code',
            'models.name as model',
            'models.pwbname',
            'models.cavity',
            'models.pwbno',
            'models.process',

            'model_details.code as detail_code',
        ])
        ->leftJoin('models', function($join){
            $join->on('schedule_histories.model', '=', 'models.name');
            $join->on('schedule_histories.pwbno', '=', 'models.pwbno');
            $join->on('schedule_histories.pwbname', '=', 'models.pwbname');
            $join->on('schedule_histories.process', '=', 'models.process');

        })
        ->leftJoin('model_details', function($join){
            $join->on('model_details.model_id', '=', 'models.id');
            $join->on('model_details.prod_no', '=', 'schedule_histories.prod_no');

        })
        ->leftJoin('details', function ($join){
            $join->on('model_details.id','=','details.model_detail_id');
            $join->on('schedule_histories.start_serial','=','details.start_serial');
            $join->on('schedule_histories.lot_size','=','details.lot_size');
            $join->on( 'schedule_histories.qty','=', 'details.qty');
            $join->on( 'schedule_histories.start_serial','=', 'details.start_serial');
            $join->on( 'schedule_histories.seq_start','=', 'details.seq_start');
            $join->on( 'schedule_histories.seq_end','=', 'details.seq_end');
        });    	

        /*Search Query*/
            if ($request->name != null && $request->name != '' ) {
                # code...
                $models = $models->where('name','like','%'.$request->name.'%');
            }

            if ($request->pwbno != null && $request->pwbno != '' ) {
                # code...
                $models = $models->where('models.pwbno','like','%'.$request->pwbno.'%');
            }

            if ($request->pwbname != null && $request->pwbname != '' ) {
                # code...
                $models = $models->where('models.pwbname','like','%'.$request->pwbname.'%');
            }

            if ($request->process != null && $request->process != '' ) {
                # code...
                $models = $models->where('models.process','like','%'.$request->process.'%');
            }

            if ($request->code != null && $request->code != '' ) {
                # code...
                $models = $models->where('schedule_histories.code','like','%'.$request->code.'%');
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

            if ($request->release_date != null && $request->release_date != '' ) {
                # code...
                $models = $models->whereHas('schedule', function ($query) use ($request){
                    $query->where('release_date', '=', $request->release_date );
                });
            }

            if ($request->rev != null && $request->rev != '' ) {
                # code...
                $models = $models->whereHas('schedule', function ($query) use ($request){
                    $query->where('rev', '=', $request->rev );
                });
            }
        /*End Search*/

    	$models = $models->paginate($limit);
    	return $models;
    }
}
