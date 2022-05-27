<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadLessonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lead_lessons', function (Blueprint $table) {
            $table->id();
            $table->integer('alfa_id');
            $table->integer('lead_id');
            $table->integer('subject_id');
            $table->integer('teacher_id');
            $table->integer('lesson_id')->nullable();
            $table->integer('group_id');
            $table->date('b_date')->nullable();
            $table->date('e_date')->nullable();
            $table->boolean('attend')->nullable();
            $table->string('status')->nullable();
            $table->timestamp("created_at")->useCurrent();
            $table->timestamp("updated_at")->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lead_lessons');
    }
}
