<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlfaGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('alfa_groups', function (Blueprint $table) {
            $table->integer("id")->unique();
            $table->mediumText("name");
            $table->integer("level_id")->nullable(true);
            $table->integer("status_id")->nullable(true);
            $table->integer("limit")->nullable(true);
            $table->integer("customers")->nullable(true);
            $table->mediumText("note")->nullable(true);
            $table->date("b_date")->nullable(true);
            $table->date("e_date")->nullable(true);
            $table->string("age")->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('alfa_groups');
    }
}
