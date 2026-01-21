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
        Schema::table('memorizers', function (Blueprint $table) {
            $table->dropColumn('sex');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memorizers', function (Blueprint $table) {
            $table->enum('sex', ['male', 'female'])->nullable()->after('birth_date');
        });
    }
};
