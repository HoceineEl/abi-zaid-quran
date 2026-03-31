<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('memorizers', 'number')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('memorizers', function (Blueprint $table) {
                $table->dropUnique('memorizers_number_unique');
            });
        }

        Schema::table('memorizers', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('memorizers', 'number')) {
            return;
        }

        Schema::table('memorizers', function (Blueprint $table) {
            $table->string('number')->unique()->nullable()->after('birth_date');
        });
    }
};
