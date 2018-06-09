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
            $table->integer('schedule_id')->nullable(); //ini jadi nullable
            $table->string('lot_size')->nullable();
            $table->string('model_code', 6)->nullable(); //aktualnya si 5 digit
            $table->string('prod_no_code', 3 )->nullable();
            $table->string('side', 2)->nullable(); // A or B
            $table->integer('cavity')->nullable();
            $table->integer('seq_start')->nullable();
            $table->integer('seq_end')->nullable();
            $table->string('line', 35 );
            $table->integer('start_serial');
            $table->string('model', 35 ); 
            $table->string('pwbname', 35 ); 
            $table->string('pwbno', 35 ); 
            $table->string('prod_no', 35 );
            $table->string('process', 35 ); 
            $table->dateTimeTz('rev_date'); //date time with timezone
            $table->integer('qty'); //qty untuk yg prod_no yg dipisah
            $table->timestamps();
        });

        Schema::create('schedule_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('schedule_id');
            $table->string('lot_size')->nullable();
            $table->string('model_code', 6)->nullable(); //aktualnya si 5 digit
            $table->string('prod_no_code', 3 )->nullable();
            $table->string('side', 2)->nullable(); // A or B
            $table->integer('cavity')->nullable();
            $table->integer('seq_start')->nullable();
            $table->integer('seq_end')->nullable();
            $table->string('line', 35 );
            $table->integer('start_serial');
            $table->string('model', 35 ); 
            $table->string('pwbname', 35 ); 
            $table->string('pwbno', 35 ); 
            $table->string('prod_no', 35 );
            $table->string('process', 35 ); 
            $table->date('rev_date'); //date time with timezone
            $table->integer('qty'); //qty untuk yg prod_no yg dipisah
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
