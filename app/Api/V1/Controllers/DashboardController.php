<?php

namespace App\Api\V1\Controllers;
use App\Http\Controllers\Controller; //parent contoller
use Illuminate\Http\Request;
use App\Mastermodel;
use App\Schedule;
use App\ScheduleDetail;
use App\Detail;
use App\ScheduleHistory;
use App\modelDetail;
use DB;
use App\Api\V1\Helper\Dummy;


class DashboardController extends Controller
{   
    private $request;

    public function index(Request $request){
        $this->request = $request;
        $limit = (isset($request->limit))? $request->limit:10;
        
        $result = $this->getMasterModel();

        // where clause;
        if(isset($request->dummy)){
            $dummy = new Dummy($request->dummy);
            $boards = $dummy->getBoards();
            // return $boards;
            if (!is_null($boards)) {
                $request->code = $boards;
            }
        }

        if(isset($request->code)){
            // return $this->getCode();
            if(is_array($request->code)){
                foreach ($request->code as $key => $value) {
                    if($key === 0){
                      $result = $result->where( $this->getCode($value) );
                    }else{
                      $result = $result->orWhere( $this->getCode($value) );  
                    }
                }
            }else{
                $result = $result->where( $this->getCode() );
            }// return $result->toSql();
        }

        if ($request->modelname != null && $request->modelname != '' ) {
            $result = $result->where('models.name','like','%'.$request->modelname.'%');
        }

        $result = $result->paginate($limit);

        return $result;
    }

    private function getMasterModel(){
        return Mastermodel::select([
            'details.id',
            // 'models.id',
            'models.cavity',

            'models.name as model',
            'models.code as model_code',
            'models.process',
            // model_Details
            'model_details.prod_no',
            'model_details.code as prod_no_code',
            
            'models.pwbname',
            'models.pwbno',
            'models.ynumber',
            'models.side',
            'model_id',
            // details
            'details.start_serial',
            'details.lot_size',
            'details.seq_start',
            'details.seq_end',
            'details.qty',

            'schedule_histories.id as history_id',
            'schedule_histories.schedule_id',
            'schedule_histories.line',
            'schedule_histories.rev_date',
        ])->distinct()
        ->leftJoin('model_details', 'models.id', '=', 'model_details.model_id')
        ->leftJoin('details', function ($join){
            $join->on( 'model_details.id', '=', 'details.model_detail_id');
                /*$join->where('details.id', '=', Detail::where('details.model_detail_id', 'model_details.id')->max('id') );*/
                $join->where('details.id', DB::raw('(select MAX(id) from details where model_details.id = details.model_detail_id)'));
        })
        ->leftJoin('schedule_histories', function($join){
            $join->on( 'models.name', '=', 'schedule_histories.model');
            $join->on( 'models.pwbname', '=', 'schedule_histories.pwbname');
            $join->on( 'models.pwbno', '=', 'schedule_histories.pwbno');
            $join->on( 'models.process', '=', 'schedule_histories.process');
            $join->on( 'model_details.prod_no', '=', 'schedule_histories.prod_no');
            
            $join->where('schedule_histories.schedule_id', DB::raw('(select MAX(schedule_id) from schedule_histories where
                models.name = schedule_histories.model and
                models.pwbname = schedule_histories.pwbname and
                models.pwbno = schedule_histories.pwbno and
                models.process = schedule_histories.process and
                model_details.prod_no = schedule_histories.prod_no
            )') );

            /*$join->orWhere('schedule_histories.schedule_id', DB::raw('(select MAX(schedule_id) from schedule_histories where
                models.name = schedule_details.model and
                models.pwbname = schedule_details.pwbname and
                models.pwbno = schedule_details.pwbno and
                models.process = schedule_details.process and
                model_details.prod_no = schedule_details.prod_no
            )'));*/
        });
    }

    private function getCode($paramCode = null){
        if(!isset($this->request->code)){
            // do something to make sure code exists;
        }

        $code = (is_null($paramCode)) ? $this->request->code : $paramCode ;
        $modelCode = substr($code, 0, 11); 
        
        $countryCode = substr($code, 12, 1);
        
        $sideCode = substr($code, 13,1);
        
        $cavityCode = substr($code, 14, 2 );
        $cavityCode = (int) $cavityCode;
        $lotNo = substr($code, 16, 4);
        
        $seqNo = substr($code, 20,4);
        $seqNo = $seqNo;

        return [
            ['models.code', 'like', $modelCode.'%'],
            ['models.side', 'like', $sideCode.'%'],
            ['models.cavity', '>=', $cavityCode ],
            ['model_details.code','like', $lotNo.'%'],
            // seqNo is between seqStart and seqEnd
            ['details.seq_start', '<=', $seqNo ],
            ['details.seq_end', '>=', $seqNo ],
        ];
    }
}
