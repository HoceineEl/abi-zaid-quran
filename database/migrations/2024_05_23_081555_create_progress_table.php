<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->string('comment')->nullable();
            $table->enum('status', ['memorized', 'absent']);
            $table->integer('page_id')->nullable();
            $table->integer('lines_from')->nullable();
            $table->integer('lines_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('progress');
    }
};
