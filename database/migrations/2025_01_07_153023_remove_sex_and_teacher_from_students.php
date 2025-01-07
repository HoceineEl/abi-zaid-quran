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
            if (Schema::hasColumn('memorizers', 'sex')) {
                $table->dropColumn('sex');
            }
            if (Schema::hasColumn('memorizers', 'teacher_id')) {
                $table->dropForeign(['teacher_id']);
                $table->dropColumn('teacher_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memorizers', function (Blueprint $table) {
            $table->enum('sex', ['male', 'female'])->default('male')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
