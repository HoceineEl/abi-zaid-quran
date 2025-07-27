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
            $table->boolean('has_returned')->default(false)->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_disconnections', function (Blueprint $table) {
            $table->dropColumn('has_returned');
        });
    }
};
