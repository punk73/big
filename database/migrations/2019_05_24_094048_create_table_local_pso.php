<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableLocalPso extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pso_locals', function (Blueprint $table) {
            $table->increments('id');
            $table->string('line', 50);
            $table->string('model', 50);
            $table->string('prod_no', 50);
            $table->integer('start_serial');
            $table->integer('qty');
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
        Schema::dropIfExists('pso_locals');
    }
}
