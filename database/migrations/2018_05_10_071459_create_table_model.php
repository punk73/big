<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableModel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 35 );
            $table->string('pwbno', 35);
            $table->string('pwbname', 40);
            $table->string('process', 35);
            $table->integer('cavity')->default(1)->nullable();
            $table->string('code')->nullable(); //string, but increment from 0 to 99M
            $table->string('side')->nullable(); //string, but increment from 0 to 99M
            $table->string('ynumber'); //string, will be changes for code;
            
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
        Schema::dropIfExists('models');
    }
}
