<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('memorizers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('password')->default(12345678);
            $table->enum('sex', ['male', 'female'])->nullable();
            $table->string('city')->nullable();
            $table->foreignId('memo_group_id')->constrained()->cascadeOnDelete();
            $table->integer('order')->default(1);
            $table->boolean('exempt')->default(false);
            $table->string('photo')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memorizers');
    }
};
