<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlfaLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('alfa_logs', function (Blueprint $table) {
            $table->id();
            $table->string("source");
            $table->string("message");
            $table->dateTime("created_at")->useCurrent();
            $table->json("data");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('alfa_logs');
    }
}
