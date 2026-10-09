<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memorizers', function (Blueprint $table) {
            $table->dropForeign(['memo_group_id']);
        });

        Schema::table('memorizers', function (Blueprint $table) {
            $table->foreignId('memo_group_id')->nullable()->change();
            $table->foreign('memo_group_id')->references('id')->on('memo_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('memorizers', function (Blueprint $table) {
            $table->dropForeign(['memo_group_id']);
        });

        Schema::table('memorizers', function (Blueprint $table) {
            $table->foreignId('memo_group_id')->nullable(false)->change();
            $table->foreign('memo_group_id')->references('id')->on('memo_groups')->cascadeOnDelete();
        });
    }
};
