<?php

namespace App\Functional\Api\V1\Controllers;

use Hash;
use App\User;
use App\Schedule;
use App\ScheduleDetail;
use App\ScheduleHistory;
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

    public function testIndexMethodSuccess()
    {   
        $schedules = ScheduleDetail::all();
        $this->assertEquals(0, count($schedules), 'pre condition, schedule details must be empty');

        $this->simulateUploadFile();

        $response = $this->get('api/schedule_details/', []);

        $response
        ->assertJsonStructure([
            'data',
        ])->isOk();

        $schedules = ScheduleDetail::all();
        $this->assertGreaterThan(0, count($schedules), 'pre condition, schedule details must be empty');

    }

    // its helper function for testIndexMethodSuccess
    private function simulateUploadFile(){
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

        $this->post($this->endpoint . '/upload', $data );
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

    public function testProcessMethodWithoutProperParameter(){
        $response = $this->post($this->endpoint . '/process', [] );

        $response->assertJsonStructure([
            'success',
            'message',
            'errors' => [
                'release_date',
                'effective_date',
                'end_effective_date',
            ]
        ])
        ->isOk();
    }

    public function testProcessWithProperParameterButWithoutUploadSchedule(){
        $response = $this->post($this->endpoint . '/process', [
            'release_date' => '2018-07-01',
            'effective_date' => '2018-07-03',
            'end_effective_date' => '2018-07-25',
        ]);

        $response->assertJsonStructure([
            'success',
            'message',
            'errors' => [
                'schedule_code'
            ]
        ]);
    }

    public function testProcessSuccess(){
        $this->simulateUploadFile();

        $mastermodel = count( Mastermodel::all());
        $this->assertEquals(0, $mastermodel, 'pre condition. it should be empty' );


        $response = $this->post($this->endpoint . '/process', [
            'release_date' => '2018-07-01',
            'effective_date' => '2018-07-03',
            'end_effective_date' => '2018-07-25',
        ]);

        $response->assertJsonStructure([
            'success'
        ])->assertJson([
            'success' =>true
        ]);


        $schedule = count( ScheduleDetail::all() );
        $history = count( ScheduleHistory::all() );
        // cek semua content shcedule sudah tercopy ke history
        $this->assertEquals($schedule, $history );

        // cek tidak ada lagi schedule detail yg tidak memiliki code
        $mastermodel = count( Mastermodel::all());
        $this->assertGreaterThan(0, $mastermodel);

    }

    public function testGenerateCodeSuccess(){
        
    }

}
