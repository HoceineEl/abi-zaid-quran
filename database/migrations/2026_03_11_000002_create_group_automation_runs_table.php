<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->date('run_date');
            $table->string('phase');
            $table->string('status')->default('running');
            $table->json('details')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'run_date', 'phase'], 'group_automation_runs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_automation_runs');
    }
};
