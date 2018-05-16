<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableScheduleDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        

        Schema::create('schedule_details', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('schedule_id');
            $table->string('code', 25 )->nullable(); //cuman dipakai 8
            $table->integer('seq_start')->nullable();
            $table->integer('seq_end')->nullable();
            $table->integer('lot_size')->nullable();
            $table->integer('cavity')->default(1);
            $table->string('line', 35 );
            $table->integer('start_serial' );
            $table->string('prod_no', 35 );
            $table->string('model', 35 ); 
            $table->string('pwbno', 35 ); 
            $table->string('pwbname', 35 ); 
            $table->string('process', 35 ); 
            $table->dateTimeTz('rev_date'); //date time with timezone
            $table->timestamps();
        });

        Schema::create('schedule_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('schedule_id');
            $table->string('code', 10 )->nullable(); //cuman dipakai 8
            $table->integer('seq_start')->nullable();
            $table->integer('seq_end')->nullable();
            $table->integer('lot_size')->nullable();
            $table->integer('cavity')->default(1);
            $table->string('line', 35 );
            $table->integer('start_serial' );
            $table->string('prod_no', 35 );
            $table->string('model', 35 ); 
            $table->string('pwbno', 35 ); 
            $table->string('pwbname', 35 ); 
            $table->string('process', 35 ); 
            $table->dateTimeTz('rev_date'); //date time with timezone
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // 
        Schema::dropIfExists('schedule_details');
        Schema::dropIfExists('schedule_histories');
    }
}
