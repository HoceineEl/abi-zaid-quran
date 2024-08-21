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
            $table->string('phone');
            $table->string('password')->default(12345678);
            $table->rememberToken();
            $table->enum('sex', ['male', 'female'])->default('male');
            $table->string('city')->nullable();
            $table->foreignId('memo_group_id')->constrained()->cascadeOnDelete();
            $table->integer('order')->default(1);
            $table->boolean('exempt')->default(false);
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
