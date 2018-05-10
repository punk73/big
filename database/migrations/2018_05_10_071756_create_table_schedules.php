<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableSchedules extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('line', 35 );
            $table->string('prod_no', 35 );
            $table->integer('start_serial' );
            $table->integer('lot_size');
            $table->string('code', 10 ); //cuman dipakai 8
            $table->integer('seq_start');
            $table->integer('seq_end');
            $table->string('model', 35 ); //cuman dipakai 8
            $table->string('pwbno', 35 ); //cuman dipakai 8
            $table->string('pwbname', 35 ); //cuman dipakai 8
            $table->string('process', 35 ); //cuman dipakai 8
            $table->dateTimeTz('rev_date'); //date time with timez
            $table->timestamps();
        });

        Schema::create('schedules_backups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('line', 35 );
            $table->string('prod_no', 35 );
            $table->integer('start_serial' );
            $table->integer('lot_size');
            $table->string('code', 10 ); //cuman dipakai 8
            $table->integer('seq_start');
            $table->integer('seq_end');
            $table->string('model', 35 ); //cuman dipakai 8
            $table->string('pwbno', 35 ); //cuman dipakai 8
            $table->string('pwbname', 35 ); //cuman dipakai 8
            $table->string('process', 35 ); //cuman dipakai 8
            $table->dateTimeTz('rev_date'); //date time with timez
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
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('schedules_backups');

    }
}
