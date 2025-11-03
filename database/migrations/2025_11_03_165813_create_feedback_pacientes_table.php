<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFeedbackPacientesTable extends Migration
{
    public function up()
    {
        Schema::create('feedback_pacientes', function (Blueprint $table) {
            $table->id();
            $table->string('os_numero', 100)->index();
            $table->tinyInteger('nota')->nullable(); // 1..5
            $table->text('comentario')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('feedback_pacientes');
    }
}
