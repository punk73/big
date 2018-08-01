<?php

namespace App\Functional\Api\V1\Controllers;

use Hash;
use App\User;
use App\Schedule;
use App\ScheduleDetail;
use App\Mastermodel;
use App\Detail;
use App\TestCase;
use Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ScheduleDetailControllerTest extends TestCase
{
    use DatabaseMigrations;

    protected $endpoint='api/schedule_details';

    private function prepareIndexTest(){
        $test = [   
            // "id"=> 2,
            "schedule_id"=> 1,
            "lot_size"=> "0.45K",
            "model_code"=> "00002",
            "prod_no_code"=> "001",
            "side"=> 'A',
            "cavity"=> 1,
            "seq_start"=> "1",
            "seq_end"=> "225",
            "line"=> "SMT01",
            "start_serial"=> "401",
            "model"=> "KXME503WSJN",
            "pwbname"=> "DIGITAL IO",
            "pwbno"=> "J7J-0317-10",
            "prod_no"=> "012A",
            "process"=> "DM1",
            "rev_date"=> "2018-06-09 00=>00=>00 +00=>00",
            "qty"=> "225",
            "created_at"=> null,
            "updated_at"=> "2018-06-09 07=>05=>01.000",
            "models_name"=> "KXME503WSJN",
            "models_pwbname"=> "DIGITAL IO",
            "models_pwbno"=> "J7J-0317-10",
            "models_process"=> "DM1",
            "models_cavity"=> "1",
            "models_side"=> null,
            "models_code"=> "00002",
            "model_details_code"=> "001",
            "model_details_prod_no"=> "012A",
            "details_start_serial"=> "401",
            "details_lot_size"=> "0.45K",
            "details_seq_start"=> "1",
            "details_seq_end"=> "225",
            "details_qty"=> "225",
        ];

        
        $model = new Mastermodel([
            'name' => $test['model'],
            'pwbname' => $test['pwbname'], 
            'process' => $test['process'], 
            'pwbno' => $test['pwbno'],   
            'cavity' => $test['cavity'],  
            'side' => $test['side'],
        ]);

        $schedule = new ScheduleDetail([
            'line' => $test['line'],    
            'model' => $test['model'],   
            'process' => $test['process'], 
            'pwbname' => $test['pwbname'], 
            'pwbno' => $test['pwbno'],   
            'prod_no' => $test['prod_no'], 
            'start_serial' => $test['start_serial'],    
            'lot_size' => $test['lot_size'],    
            'qty' => $test['qty'],
        ]);
    }

    public function testIndexMethod()
    {   
        $this->prepareIndexTest();

        $response = $this->get('api/schedule_details/', []);

        /*$response->assertJson([
            'data' => [   
                // "id"=> 2,
                "schedule_id"=> 1,
                "lot_size"=> "0.45K",
                "model_code"=> "00002",
                "prod_no_code"=> "001",
                "side"=> 'A',
                "cavity"=> 1,
                "seq_start"=> "1",
                "seq_end"=> "225",
                "line"=> "SMT01",
                "start_serial"=> "401",
                "model"=> "KXME503WSJN",
                "pwbname"=> "DIGITAL IO",
                "pwbno"=> "J7J-0317-10",
                "prod_no"=> "012A",
                "process"=> "DM1",
                "rev_date"=> "2018-06-09 00=>00=>00 +00=>00",
                "qty"=> "225",
                "created_at"=> null,
                "updated_at"=> "2018-06-09 07=>05=>01.000",
                "models_name"=> "KXME503WSJN",
                "models_pwbname"=> "DIGITAL IO",
                "models_pwbno"=> "J7J-0317-10",
                "models_process"=> "DM1",
                "models_cavity"=> "1",
                "models_side"=> null,
                "models_code"=> "00002",
                "model_details_code"=> "001",
                "model_details_prod_no"=> "012A",
                "details_start_serial"=> "401",
                "details_lot_size"=> "0.45K",
                "details_seq_start"=> "1",
                "details_seq_end"=> "225",
                "details_qty"=> "225",
            ]
        ])->assertJsonStructure([
            'data',
        ])->isOk();*/

        var_dump($response->getContent());
    }

    public function testUploadScheduleSuccess(){
        $path = storage_path('test');
        $based_name = 'schedule_format.csv';
        $name = str_random(8).'.csv';
        $basedFullPath = $path .'\\'. $based_name; 
        $fullpath = $path .'\\'. $name;

        // copy file from source to destinations;
        copy($basedFullPath, $fullpath);
        
        $this->assertFileExists($fullpath);

        $file = new UploadedFile($fullpath, $name, filesize($fullpath), 'csv', null, true);
        
        $data = [
            'file'=> $file
        ];

        $this->post($this->endpoint . '/upload', $data )
        ->assertJsonStructure([
            'success','message'
        ])->assertJson([
            'success' => true
        ]);

        $schedules = ScheduleDetail::all();

        // fwrite(STDOUT, count($schedules));
        // $schedules lebih besar dari 0;
        $this->assertGreaterThan(0, count($schedules) );

    }

    public function testUploadScheduleFailedBecauseFileNotSet(){
        $data = [
            'file'=> null
        ];

        $this->post($this->endpoint . '/upload', $data )
        ->assertJsonStructure([
            'success','message'
        ])->assertJson([
            'success' => false
        ]);        
    }


}
