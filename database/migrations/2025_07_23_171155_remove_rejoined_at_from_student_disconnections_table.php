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
        Schema::table('student_disconnections', function (Blueprint $table) {
            $table->dropColumn('rejoined_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_disconnections', function (Blueprint $table) {
            $table->timestamp('rejoined_at')->nullable();
        });
    }
};
