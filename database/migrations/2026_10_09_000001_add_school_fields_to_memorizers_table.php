<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memorizers', function (Blueprint $table) {
            $table->string('school_level')->nullable()->after('birth_date');
            $table->unsignedTinyInteger('memorized_hizbs')->nullable()->after('school_level');
            $table->boolean('attended_summer_session')->default(false)->after('memorized_hizbs');
        });
    }

    public function down(): void
    {
        Schema::table('memorizers', function (Blueprint $table) {
            $table->dropColumn(['school_level', 'memorized_hizbs', 'attended_summer_session']);
        });
    }
};
